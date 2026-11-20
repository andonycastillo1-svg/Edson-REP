<?php

namespace App\Services;

use App\Models\AlertaReemplazo;
use App\Models\AsignacionInventario;
use App\Models\Colaborador;
use App\Models\EntregaInventario;
use App\Models\MotivoAsignacion;
use App\Models\User;
use App\Sistema\Auditoria;
use App\Sistema\CatalogoPermisos;
use App\Sistema\ValoresCampos;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RegistrarAsignaciones
{
    public function __construct(
        private InventarioStockService $stock,
        private AsignacionVidaUtilService $vida,
        private DetectarReemplazos $reemplazos,
        private ValoresCampos $campos,
    ) {}

    public function registrar(User $usuario, array $data, Collection $items, array $valores, ?callable $adjuntarFoto = null): EntregaInventario
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('El registro requiere una transacción.');
        }
        $grupo = (string) Str::uuid();
        $salida = isset($data['fecha']) ? Carbon::parse($data['fecha']) : now();
        $recibida = isset($data['recibida_en']) ? Carbon::parse($data['recibida_en']) : null;
        if ($recibida?->lt($salida)) {
            throw ValidationException::withMessages(['recibida_en' => 'La recepción no puede ser anterior a la salida.']);
        }
        if (isset($data['solicitud_id'])) {
            SolicitudInventarioService::registrar('asignacion', $data['solicitud_id']);
        }
        $colaborador = Colaborador::whereKey($data['colaborador_codigo'])->lockForUpdate()->firstOrFail();
        if ($colaborador->estado !== 'Activo') {
            throw ValidationException::withMessages(['colaborador_codigo' => 'El colaborador no está activo.']);
        }

        $anteriores = AsignacionInventario::custodiaActiva()->where('colaborador_codigo', $colaborador->codigo)
            ->whereIn('producto_codigo', $items->pluck('producto_codigo'))
            ->orderBy('producto_codigo')->orderBy('id')->lockForUpdate()->get()->groupBy('producto_codigo');
        $modalidad = $data['modalidad'] ?? 'directa';
        $directa = $modalidad === 'directa' && !array_key_exists('confirmar_recepcion', $data);
        $confirmada = $directa || (bool) ($data['confirmar_recepcion'] ?? false);
        $fechaReal = $directa ? $salida : $recibida;
        $entrega = EntregaInventario::create([
            'id' => $grupo,
            'modalidad' => $modalidad,
            'tercero' => $modalidad === 'tercero' ? ($data['tercero'] ?? null) : null,
            'salida_en' => $salida,
            'recibida_en' => $confirmada ? $fechaReal : null,
            'confirmada_en' => $confirmada ? now() : null,
            'confirmada_por' => $confirmada ? $usuario->id : null,
            'medio_confirmacion' => $directa ? 'presencial' : ($confirmada ? $data['medio_confirmacion'] : null),
        ]);

        $primera = null;
        $alertas = [];
        foreach ($items as $item) {
            $codigo = $item['producto_codigo'];
            $motivoNuevo = $item['motivo_inventario_nuevo'] ?? null;
            $tipo = $this->stock->tipoParaAsignar((int) $item['bodega_id'], $codigo, filled($motivoNuevo));
            $previas = $anteriores->get($codigo, collect());
            $anterior = $previas->last() ?? AsignacionInventario::where('colaborador_codigo', $colaborador->codigo)
                ->where('producto_codigo', $codigo)->latest('id')->lockForUpdate()->first();
            $motivo = empty($item['motivo_asignacion']) ? null
                : MotivoAsignacion::where('codigo', $item['motivo_asignacion'])->where('activo', true)->first();
            if (filled($item['motivo_asignacion'] ?? null) && !$motivo) {
                throw ValidationException::withMessages(['motivo_asignacion' => 'Selecciona un motivo de asignación vigente.']);
            }
            $asignacion = AsignacionInventario::create([
                'grupo_asignacion' => $grupo,
                'colaborador_codigo' => $colaborador->codigo,
                'producto_codigo' => $codigo,
                'bodega_id' => $item['bodega_id'],
                'cantidad_asignada' => 1,
                'fecha' => $entrega->salida_en->toDateString(),
                'costo_unitario' => $data['costo_unitario'] ?? null,
                'aprobado_por' => $item['aprobado_por'] ?? $data['aprobado_por'] ?? null,
                'medio_solicitud' => $item['medio_solicitud'] ?? $data['medio_solicitud'] ?? null,
                'observaciones' => $item['observaciones'] ?? $data['observaciones'] ?? null,
                'estado' => 'Activa',
                'stock_tipo' => $tipo,
                'tipo_entrega' => $anterior === null ? 'inicial' : 'reposicion',
                'asignacion_anterior_id' => $anterior?->id,
                'motivo_inventario_nuevo' => $motivoNuevo,
                'motivo_asignacion_id' => $motivo?->id,
                'motivo_registrado' => $motivo?->nombre,
                'user_id' => $usuario->id,
            ]);
            $asignacion->setRelation('entrega', $entrega);
            $primera ??= $asignacion->id;

            $this->stock->asignar($asignacion, $tipo);
            if ($entrega->recibida_en !== null) {
                $this->vida->registrarInicio($asignacion, $entrega->recibida_en);
            }
            $this->campos->guardar('asignaciones', $asignacion->id, $valores);
            $asignacion->movimientos()->create([
                'tipo' => 'Asignacion', 'cantidad' => 1, 'detalle' => 'Salida registrada.',
                'user_id' => $usuario->id, 'ocurrio_en' => $salida,
            ]);
            $this->vida->registrarEstado($asignacion, 'activo', 'Salida de bodega registrada.');
            app(Auditoria::class)->registrar('asignaciones', 'registrar', $asignacion->id, null,
                ['bodega_id' => $asignacion->bodega_id, 'cantidad' => 1, 'stock_tipo' => $tipo,
                    'motivo_inventario_nuevo' => $motivoNuevo, 'motivo_asignacion' => $motivo?->codigo]);

            $alertas = [...$alertas, ...$this->reemplazos->registrar($asignacion, $previas, $motivo)];
        }

        if ($adjuntarFoto !== null) {
            $adjuntarFoto($entrega, $primera);
        }
        if ($confirmada) {
            app(Auditoria::class)->registrar('asignaciones', 'confirmar_recepcion', $grupo, null,
                ['recibida_en' => $entrega->recibida_en?->toDateTimeString(), 'medio' => $entrega->medio_confirmacion]);
        }

        DB::afterCommit(function () use ($primera, $alertas, $usuario) {
            $notificaciones = app(NotificacionService::class);
            $prefijo = CatalogoPermisos::prefijo($usuario->rolOperativo());
            $notificaciones->safeAction(fn ($servicio) => $servicio->notificarNuevaAsignacion(
                AsignacionInventario::findOrFail($primera), $usuario, route($prefijo.'.asignaciones.index')
            ));
            foreach (AlertaReemplazo::whereKey($alertas)->get() as $alerta) {
                $notificaciones->safeAction(fn ($servicio) => $servicio->notificarAlertaRrhh($alerta, $usuario));
            }
        });

        return $entrega;
    }
}

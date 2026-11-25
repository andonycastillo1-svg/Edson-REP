<?php

namespace App\Services;

use App\Models\AlertaReemplazo;
use App\Models\AsignacionInventario;
use App\Models\Colaborador;
use App\Models\EntregaInventario;
use App\Models\MotivoAsignacion;
use App\Models\User;
use App\Sistema\Auditoria;
use App\Sistema\ValoresCampos;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RegistrarAsignaciones
{
    public const MAX_UNIDADES = 500;

    public const MEDIO_INTERNO = 'Requerimiento interno';

    public function __construct(
        private InventarioStockService $stock,
        private AsignacionVidaUtilService $vida,
        private DetectarReemplazos $reemplazos,
        private ValoresCampos $campos,
        private RequerimientosAsignacion $requerimientos,
        private AccesoCustodia $custodias,
        private AccesoInventarioOperativo $inventario,
        private ResolverCustodia $resolverCustodia,
    ) {}

    public function registrar(User $usuario, array $data, Collection $items, array $valores, ?callable $adjuntarFoto = null, string $permiso = 'asignaciones.crear'): EntregaInventario
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
        $bodegas = $items->pluck('bodega_id')->map(fn ($id) => (int) $id)->unique()->all();
        $regiones = [$colaborador->region_id];
        $alcance = $this->inventario->autorizar($usuario, $permiso, $bodegas, $regiones, bloquear: true);
        foreach ($items as $item) {
            if (filled($item['motivo_inventario_nuevo'] ?? null)) {
                $autorizado = $this->inventario->autorizar($usuario, 'asignaciones.entregar_nuevo', [(int) $item['bodega_id']], $regiones, bloquear: true);
                $this->inventario->registrar($autorizado, 'asignaciones.entregar_nuevo', $grupo);
            }
        }
        if (!empty($data['confirmar_recepcion'])) {
            $recepcion = $this->inventario->autorizar($usuario, 'asignaciones.confirmar_recepcion', $bodegas, $regiones, [$usuario->id], bloquear: true);
            $this->inventario->registrar($recepcion, 'asignaciones.confirmar_recepcion', $grupo);
        }
        $this->inventario->registrar($alcance, $permiso, $grupo);
        $requerimientos = isset($data['consolidado_id'])
            ? $this->requerimientos->vincular($usuario, (int) $data['consolidado_id'], $colaborador->codigo, $items, permiso: $permiso) : collect();

        $total = $items->sum(fn ($item) => (int) ($item['cantidad_asignada'] ?? $item['cantidad'] ?? 1));
        if ($total < 1 || $total > self::MAX_UNIDADES) {
            throw ValidationException::withMessages(['items' => 'Registra hasta '.self::MAX_UNIDADES.' unidades por operación.']);
        }
        $items = $items->flatMap(function ($item) {
            $cantidad = (int) ($item['cantidad_asignada'] ?? $item['cantidad'] ?? 1);
            if ($cantidad < 1 || $cantidad > self::MAX_UNIDADES || ($cantidad > 1 && filled($item['sustituir_asignacion_id'] ?? null))) {
                throw ValidationException::withMessages(['items' => 'Usa una unidad al sustituir una asignación específica.']);
            }

            return array_fill(0, $cantidad, $item);
        });
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
        $tiposEntrega = [];
        foreach ($items as $item) {
            $codigo = $item['producto_codigo'];
            $motivoNuevo = $item['motivo_inventario_nuevo'] ?? null;
            $tipo = $this->stock->tipoParaAsignar((int) $item['bodega_id'], $codigo, filled($motivoNuevo));
            $previas = $anteriores->get($codigo, collect());
            if ($previas->contains(fn ($previa) => $salida->lt($previa->entrega?->salida_en ?? $previa->fecha))) {
                throw ValidationException::withMessages(['fecha' => 'La salida debe ser posterior o igual a la custodia vigente.']);
            }
            $anterior = $previas->firstWhere('id', $item['sustituir_asignacion_id'] ?? null);
            $anteriorAutorizada = $anterior && $this->custodias->puedeVer($usuario, $anterior)
                && ($alcance->historico || $alcance->contiene([$anterior->bodega_id], $regiones));
            if (filled($item['sustituir_asignacion_id'] ?? null) && !$anteriorAutorizada) {
                throw ValidationException::withMessages(['items' => 'La asignación anterior debe corresponder a una custodia vigente del colaborador que tengas permiso de consultar.']);
            }
            $motivo = $anterior ? MotivoAsignacion::where('codigo', $item['motivo_asignacion'] ?? '')->where('activo', true)->first() : null;
            $restante = $anterior ? $this->vida->restante($anterior, $salida, 1) : null;
            if (($anterior && $restante !== 0 && !$motivo) || (filled($item['motivo_asignacion'] ?? null) && !$motivo)) {
                throw ValidationException::withMessages(['motivo_asignacion' => 'Selecciona un motivo vigente para justificar el reemplazo.']);
            }
            $condicionAnterior = array_filter($item['condicion_anterior'] ?? [], fn ($valor) => filled($valor)) ?: null;
            if ($condicionAnterior !== null && !$anterior) {
                throw ValidationException::withMessages(['items' => 'Selecciona la asignación anterior antes de registrar su condición.']);
            }
            if ($condicionAnterior !== null) {
                $resolucion = $this->inventario->autorizarCustodias($usuario, 'asignaciones.devolver', collect([$anterior]), bloquear: true);
                $this->inventario->registrar($resolucion, 'asignaciones.devolver', $grupo);
            }
            $tipoEntrega = $tiposEntrega[$codigo] ??= ($anterior ? 'reposicion' : ($previas->isNotEmpty() || AsignacionInventario::where('colaborador_codigo', $colaborador->codigo)
                ->where('producto_codigo', $codigo)->exists() ? 'adicional' : 'inicial'));
            $medio = isset($data['consolidado_id']) ? self::MEDIO_INTERNO : ($item['medio_solicitud'] ?? $data['medio_solicitud'] ?? null);
            if ($medio === self::MEDIO_INTERNO && !isset($data['consolidado_id'])) {
                throw ValidationException::withMessages(['medio_solicitud' => 'Vincula un consolidado para registrar el origen interno.']);
            }
            $asignacion = AsignacionInventario::create([
                'grupo_asignacion' => $grupo,
                'colaborador_codigo' => $colaborador->codigo,
                'requerimiento_id' => $requerimientos->get($codigo),
                'producto_codigo' => $codigo,
                'bodega_id' => $item['bodega_id'],
                'cantidad_asignada' => 1,
                'fecha' => $entrega->salida_en->toDateString(),
                'costo_unitario' => $data['costo_unitario'] ?? null,
                'aprobado_por' => $item['aprobado_por'] ?? $data['aprobado_por'] ?? null,
                'medio_solicitud' => $medio,
                'observaciones' => $item['observaciones'] ?? $data['observaciones'] ?? null,
                'estado' => 'Activa',
                'stock_tipo' => $tipo,
                'tipo_entrega' => $tipoEntrega,
                'asignacion_anterior_id' => $anterior?->id,
                'motivo_inventario_nuevo' => $motivoNuevo,
                'motivo_asignacion_id' => $motivo?->id,
                'motivo_registrado' => $motivo?->nombre ?? ($anterior ? 'Vencimiento de vida útil' : null),
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
                    'motivo_inventario_nuevo' => $motivoNuevo, 'motivo_asignacion' => $motivo?->codigo,
                    'requerimiento_id' => $asignacion->requerimiento_id]);

            if ($anterior) {
                $alertasReemplazo = $this->reemplazos->registrar($asignacion, collect([$anterior]), $motivo, $condicionAnterior !== null);
                $alertas = [...$alertas, ...$alertasReemplazo];
                if ($condicionAnterior !== null) {
                    $this->resolverCustodia->aplicar($anterior, 1, $condicionAnterior, $usuario, $grupo,
                        'reemplazo', alertaReemplazoId: $alertasReemplazo[0] ?? null, reemplazoId: $asignacion->id);
                }
            }
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
            $prefijo = AccesoInventarioOperativo::prefijo($usuario);
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

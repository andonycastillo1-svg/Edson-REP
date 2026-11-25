<?php

namespace App\Services;

use App\Models\AlertaReemplazo;
use App\Models\AsignacionInventario;
use App\Models\AsignacionMovimiento;
use App\Models\Colaborador;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DevolverCustodiaCompleta
{
    public const CONDICIONES = [
        'buen_estado' => 'Recibido en buen estado',
        'danado' => 'Recibido dañado',
        'sin_retorno' => 'No devuelto',
    ];

    public function __construct(
        private AccesoInventarioOperativo $inventario,
        private ResolverCustodia $resolver,
    ) {}

    public function puedeRegistrar(User $usuario, Colaborador $colaborador): bool
    {
        return $this->inventario->tiene($usuario, 'asignaciones.devolver')
            && $this->inventario->buscarCustodias($usuario, 'asignaciones.devolver', $colaborador->asignaciones()->custodiaAbierta()->get()) !== null;
    }

    public function cargar(User $usuario, Colaborador $colaborador): Collection
    {
        $asignaciones = $colaborador->asignaciones()->custodiaAbierta()->with('bodega', 'producto', 'entrega')
            ->orderBy('producto_codigo')->orderBy('bodega_id')->orderBy('id')->get();
        $this->inventario->autorizarCustodias($usuario, 'asignaciones.devolver', $asignaciones);

        return $asignaciones;
    }

    public function registrar(User $usuario, string $codigo, string $solicitud, array $cantidades): string
    {
        $grupo = (string) Str::uuid();
        $ruta = 'asignaciones/devoluciones/'.$grupo.'.pdf';
        $guardando = false;
        try {
            [$ids, $alertas] = DB::transaction(function () use ($usuario, $codigo, $solicitud, $cantidades, $grupo, $ruta, &$guardando) {
                SolicitudInventarioService::registrarDevolucion($solicitud);
                $colaborador = Colaborador::whereKey($codigo)->lockForUpdate()->firstOrFail();
                $filas = $colaborador->asignaciones()->custodiaAbierta()
                    ->orderBy('producto_codigo')->orderBy('id')->lockForUpdate()->get();
                if ($filas->isEmpty()) {
                    throw ValidationException::withMessages(['articulos' => 'El colaborador ya no tiene artículos pendientes.']);
                }
                $alcance = $this->inventario->autorizarCustodias($usuario, 'asignaciones.devolver', $filas, bloquear: true);
                if (array_diff(array_keys($cantidades), $filas->modelKeys()) !== []) {
                    throw ValidationException::withMessages(['articulos' => 'La custodia cambió. Revisa la lista completa antes de confirmar.']);
                }
                foreach ($filas as $fila) {
                    if (array_sum($cantidades[$fila->id] ?? []) !== $fila->cantidad_asignada) {
                        throw ValidationException::withMessages([
                            'articulos.'.$fila->id => 'La suma de las condiciones debe coincidir con las unidades pendientes.',
                        ]);
                    }
                }

                $this->inventario->registrar($alcance, 'asignaciones.devolver', $grupo);
                $fecha = now()->toDateTimeString();
                $alertas = [];
                foreach ($filas as $fila) {
                    foreach (self::CONDICIONES as $condicion => $etiqueta) {
                        $unidades = (int) ($cantidades[$fila->id][$condicion] ?? 0);
                        if ($unidades === 0) {
                            continue;
                        }
                        $datos = [
                            'estado_devolucion' => $condicion === 'sin_retorno' ? 'otra' : $condicion,
                            'resultado_otra' => $condicion,
                            'detalle_devolucion' => $etiqueta,
                            'fecha_incidente' => $fecha,
                        ];
                        $resolucion = $this->resolver->aplicar($fila, $unidades, $datos, $usuario, $grupo,
                            AsignacionMovimiento::DEVOLUCION_COMPLETA);
                        if ($resolucion['alerta'] !== null) {
                            $alertas[] = $resolucion['alerta'];
                        }
                    }
                }

                $guardando = true;
                HojaDevolucion::guardar($grupo, $ruta);

                return [$filas->modelKeys(), array_unique($alertas)];
            }, 3);
        } catch (\Throwable $e) {
            if ($guardando) {
                DocumentoPrivado::descartar($ruta);
            }
            throw $e;
        }

        $notificaciones = app(NotificacionService::class);
        foreach (AlertaReemplazo::whereKey($alertas)->get() as $alerta) {
            $notificaciones->safeAction(fn (NotificacionService $servicio) => $servicio->notificarAlertaRrhh($alerta, $usuario));
        }
        $notificaciones->safeAction(fn (NotificacionService $servicio) => $servicio->notificarCambioEstadoAsignacion(
            AsignacionInventario::whereKey($ids)->get(), $usuario, 'una devolución completa'
        ));

        return $grupo;
    }

    public function alcanceHoja(User $usuario, Collection $movimientos): ?AlcanceInventario
    {
        $asignaciones = $movimientos->pluck('asignacion')->unique('id');
        $alcance = $this->inventario->buscarCustodias($usuario, 'asignaciones.exportar', $asignaciones);
        if ($alcance !== null || !$movimientos->every(fn ($fila) => $fila->motivo_administrativo === AsignacionMovimiento::DEVOLUCION_COMPLETA)) {
            return $alcance;
        }

        return $this->inventario->buscarCustodias($usuario, 'asignaciones.devolver', $asignaciones);
    }

    public function hojas(User $usuario, Colaborador $colaborador)
    {
        if (!$this->inventario->tiene($usuario, 'asignaciones.exportar') && !$this->inventario->tiene($usuario, 'asignaciones.devolver')) {
            return null;
        }
        $visibles = AsignacionInventario::where('colaborador_codigo', $colaborador->codigo)
            ->where(function ($q) use ($usuario) {
                $this->inventario->limitarAsignaciones($q, $usuario, 'asignaciones.exportar');
                $q->orWhere(fn ($rama) => $this->inventario->limitarAsignaciones($rama, $usuario, 'asignaciones.devolver'));
            });
        $grupos = AsignacionMovimiento::conHojaDevolucion()->whereNotNull('grupo_devolucion')
            ->whereIn('asignacion_inventario_id', $visibles->select('id'))
            ->select('grupo_devolucion')->selectRaw('MAX(created_at) AS fecha')->groupBy('grupo_devolucion')
            ->orderByDesc('fecha')->orderBy('grupo_devolucion')->simplePaginate(10, pageName: 'hojas')->withQueryString();
        $movimientos = AsignacionMovimiento::conHojaDevolucion()->with('asignacion')
            ->whereIn('grupo_devolucion', $grupos->getCollection()->pluck('grupo_devolucion'))->get()->groupBy('grupo_devolucion');
        $grupos->setCollection($grupos->getCollection()->filter(fn ($fila) =>
            $this->alcanceHoja($usuario, $movimientos->get($fila->grupo_devolucion)) !== null)->values());

        return $grupos;
    }
}

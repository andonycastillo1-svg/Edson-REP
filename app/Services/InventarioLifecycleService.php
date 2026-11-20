<?php

namespace App\Services;

use App\Models\AsignacionInventario;
use App\Models\AsignacionPeriodo;
use App\Models\InventarioExistencia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventarioLifecycleService
{
    public const CONDICIONES_DISPONIBLES = ['nuevo', 'usado'];
    public const ESTADOS_RETORNO = ['buen_estado', 'danado', 'perdido', 'robado', 'baja', 'sin_retorno'];
    public const SIN_RETORNO = ['perdido', 'robado', 'sin_retorno'];

    public function crearNuevas(string $productoCodigo, int $bodegaId, int $cantidad, ?float $valor = null, ?Carbon $ingreso = null): void
    {
        $producto = DB::table('productos')->where('codigo', $productoCodigo)->first();
        $vida = $producto?->vida_util_meses === null ? null : max(0, (int) $producto->vida_util_meses) * 30 * 86400;

        $ingreso ??= now();
        for ($i = 0; $i < $cantidad; $i++) {
            InventarioExistencia::create([
                'producto_codigo' => $productoCodigo,
                'bodega_id' => $bodegaId,
                'condicion' => 'nuevo',
                'vida_util_inicial_segundos' => $vida,
                'vida_util_restante_segundos' => $vida,
                'valor_referencia' => $valor,
                'disponible_desde' => $ingreso,
                'ingresado_en' => $ingreso,
                'creado_por_user_id' => auth()->id(),
            ]);
        }
    }

    public function reservar(
        AsignacionInventario $asignacion,
        string $tipo,
        int $cantidad,
        Carbon $asignadoEn
    ): Collection {
        if (!in_array($tipo, self::CONDICIONES_DISPONIBLES, true)) {
            throw ValidationException::withMessages(['stock_tipo' => 'El tipo de inventario no es válido.']);
        }

        $query = InventarioExistencia::query()
            ->where('producto_codigo', $asignacion->producto_codigo)
            ->where('bodega_id', $asignacion->bodega_id)
            ->where('condicion', $tipo)
            ->lockForUpdate();

        $this->ordenSalida($query, $tipo);

        $existencias = $query->limit($cantidad)->get();
        if ($existencias->count() !== $cantidad) {
            throw ValidationException::withMessages([
                'cantidad_asignada' => "Stock {$tipo} insuficiente. Disponible: {$existencias->count()}, solicitado: {$cantidad}.",
            ]);
        }

        foreach ($existencias as $existencia) {
            $vida = $existencia->vida_util_restante_segundos;
            AsignacionPeriodo::create([
                'asignacion_inventario_id' => $asignacion->id,
                'inventario_existencia_id' => $existencia->id,
                'tipo_inventario' => $tipo,
                'estado_entrega' => 'buen_estado',
                'asignado_en' => $asignadoEn,
                'vida_util_al_asignar_segundos' => $vida,
                'bodega_origen_id' => $asignacion->bodega_id,
                'asignado_por_user_id' => auth()->id(),
                'valor_referencia' => $asignacion->costo_unitario,
            ]);
            $existencia->update(['condicion' => 'asignado', 'bodega_id' => null, 'disponible_desde' => null]);
        }

        return $existencias;
    }

    public function ordenSalida(Builder $consulta, string $tipo): Builder
    {
        if ($tipo === 'usado') {
            $consulta->orderByRaw('vida_util_restante_segundos IS NULL')->orderBy('vida_util_restante_segundos');
        }

        return $consulta->orderByRaw('ingresado_en IS NOT NULL')->orderBy('ingresado_en')->orderBy('id');
    }

    public function devolver(
        AsignacionInventario $asignacion,
        int $cantidad,
        string $estado,
        int $bodegaRetornoId,
        string $motivo,
        ?string $observaciones = null,
        ?Carbon $devueltoEn = null
    ): Collection {
        if (!in_array($estado, self::ESTADOS_RETORNO, true)) {
            throw ValidationException::withMessages(['estado_devolucion' => 'El estado de devolución no es válido.']);
        }

        $devueltoEn ??= now();
        $periodosActivos = AsignacionPeriodo::where('asignacion_inventario_id', $asignacion->id)
            ->whereNull('devuelto_en')->lockForUpdate()->pluck('id')->count();
        if ($periodosActivos !== (int) $asignacion->cantidad_asignada) {
            throw ValidationException::withMessages([
                'stock' => 'Los períodos activos de esta asignación requieren conciliación.',
            ]);
        }

        $periodos = AsignacionPeriodo::query()
            ->where('asignacion_inventario_id', $asignacion->id)
            ->whereNull('devuelto_en')
            ->lockForUpdate()
            ->orderBy('id')
            ->limit($cantidad)
            ->get();

        if ($periodos->count() !== $cantidad) {
            throw ValidationException::withMessages([
                'cantidad_devuelta' => 'La cantidad supera las unidades activas o ya fueron devueltas.',
            ]);
        }

        $unidades = InventarioExistencia::whereKey($periodos->pluck('inventario_existencia_id'))
            ->where('producto_codigo', $asignacion->producto_codigo)
            ->where('condicion', 'asignado')->whereNull('bodega_id')
            ->lockForUpdate()->get()->keyBy('id');
        if ($unidades->count() !== $cantidad) {
            throw ValidationException::withMessages(['stock' => 'Las unidades asignadas requieren conciliación.']);
        }

        foreach ($periodos as $periodo) {
            if ($devueltoEn->lt($periodo->asignado_en)) {
                throw ValidationException::withMessages([
                    'fecha_devolucion' => 'La fecha del servidor no puede ser anterior a la asignación.',
                ]);
            }
            $inicio = $periodo->inicio_uso_en;
            $consumido = $inicio === null || $devueltoEn->lt($inicio) ? null : (int) $inicio->diffInSeconds($devueltoEn);
            $vidaAlAsignar = $periodo->vida_util_al_asignar_segundos;
            $restante = match (true) {
                $vidaAlAsignar === 0 => 0,
                $vidaAlAsignar === null || $consumido === null => null,
                default => max(0, (int) $vidaAlAsignar - $consumido),
            };
            $condicion = $estado === 'buen_estado' ? 'usado' : $estado;
            $reutilizable = $estado === 'buen_estado';
            $sinRetorno = in_array($estado, self::SIN_RETORNO, true);

            $periodo->update([
                'devuelto_en' => $devueltoEn,
                'tiempo_consumido_segundos' => $consumido,
                'vida_util_restante_segundos' => $restante,
                'estado_devolucion' => $estado,
                'motivo_devolucion' => $motivo,
                'observaciones_devolucion' => $observaciones,
                'reutilizable' => $reutilizable,
                'bodega_retorno_id' => $sinRetorno ? null : $bodegaRetornoId,
                'devuelto_por_user_id' => auth()->id(),
                'evidencia_calculo' => [
                    'vida_al_asignar_segundos' => $periodo->vida_util_al_asignar_segundos,
                    'tiempo_consumido_segundos' => $consumido,
                    'vida_restante_segundos' => $restante,
                    'inicio_uso_en' => $inicio?->toDateTimeString(),
                ],
            ]);

            $unidades->get($periodo->inventario_existencia_id)->update([
                'condicion' => $condicion,
                'bodega_id' => $sinRetorno ? null : $bodegaRetornoId,
                'vida_util_restante_segundos' => $restante,
                'disponible_desde' => $reutilizable ? $devueltoEn : null,
            ]);
        }

        return $periodos;
    }
}

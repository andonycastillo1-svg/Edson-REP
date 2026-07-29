<?php

namespace App\Services;

use App\Models\AsignacionInventario;
use App\Models\AsignacionPeriodo;
use App\Models\InventarioExistencia;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventarioLifecycleService
{
    public const CONDICIONES_DISPONIBLES = ['nuevo', 'usado'];
    public const ESTADOS_RETORNO = ['buen_estado', 'danado', 'perdido', 'baja'];

    public function crearNuevas(string $productoCodigo, int $bodegaId, int $cantidad, ?float $valor = null): void
    {
        $producto = DB::table('productos')->where('codigo', $productoCodigo)->first();
        $vida = $producto?->vida_util_meses === null ? null : max(0, (int) $producto->vida_util_meses) * 30 * 86400;

        for ($i = 0; $i < $cantidad; $i++) {
            InventarioExistencia::create([
                'producto_codigo' => $productoCodigo,
                'bodega_id' => $bodegaId,
                'condicion' => 'nuevo',
                'vida_util_inicial_segundos' => $vida,
                'vida_util_restante_segundos' => $vida,
                'valor_referencia' => $valor,
                'disponible_desde' => now(),
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

        if ($tipo === 'usado') {
            // NULL (sin vida útil) se conserva separado; entre vidas aplicables se usa primero la menor.
            $query->orderByRaw('vida_util_restante_segundos IS NULL')
                ->orderBy('vida_util_restante_segundos');
        } else {
            $query->orderBy('id');
        }

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

        $this->sincronizarResumen($asignacion->producto_codigo, (int) $asignacion->bodega_id);
        return $existencias;
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
            ->whereNull('devuelto_en')->count();
        if ($periodosActivos === 0 && !AsignacionPeriodo::where('asignacion_inventario_id', $asignacion->id)->exists()) {
            // Compatibilidad: se conserva el dato legado disponible sin reconstruir consumos desconocidos.
            $vidaLegada = $asignacion->vida_util_restante_meses === null
                ? null
                : max(0, (int) $asignacion->vida_util_restante_meses) * 30 * 86400;
            for ($i = 0; $i < (int) $asignacion->cantidad_asignada; $i++) {
                $existencia = InventarioExistencia::create([
                    'producto_codigo' => $asignacion->producto_codigo,
                    'bodega_id' => null,
                    'condicion' => 'asignado',
                    'vida_util_inicial_segundos' => $asignacion->vida_util_original_meses === null ? null : (int) $asignacion->vida_util_original_meses * 30 * 86400,
                    'vida_util_restante_segundos' => $vidaLegada,
                    'valor_referencia' => $asignacion->costo_unitario,
                ]);
                AsignacionPeriodo::create([
                    'asignacion_inventario_id' => $asignacion->id,
                    'inventario_existencia_id' => $existencia->id,
                    'tipo_inventario' => $asignacion->stock_tipo ?? 'nuevo',
                    'asignado_en' => $asignacion->fecha,
                    'vida_util_al_asignar_segundos' => $vidaLegada,
                    'bodega_origen_id' => $asignacion->bodega_id,
                    'asignado_por_user_id' => $asignacion->user_id,
                    'valor_referencia' => $asignacion->costo_unitario,
                ]);
            }
        }
        $periodos = AsignacionPeriodo::query()
            ->where('asignacion_inventario_id', $asignacion->id)
            ->whereNull('devuelto_en')
            ->with('existencia')
            ->lockForUpdate()
            ->limit($cantidad)
            ->get();

        if ($periodos->count() !== $cantidad) {
            throw ValidationException::withMessages([
                'cantidad_devuelta' => 'La cantidad supera las unidades activas o ya fueron devueltas.',
            ]);
        }

        foreach ($periodos as $periodo) {
            if ($devueltoEn->lt($periodo->asignado_en)) {
                throw ValidationException::withMessages([
                    'fecha_devolucion' => 'La fecha del servidor no puede ser anterior a la asignación.',
                ]);
            }
            $consumido = max(0, $periodo->asignado_en->diffInSeconds($devueltoEn, false));
            $restante = $periodo->vida_util_al_asignar_segundos === null
                ? null
                : max(0, (int) $periodo->vida_util_al_asignar_segundos - $consumido);
            $condicion = $estado === 'buen_estado' ? 'usado' : $estado;
            $reutilizable = $estado === 'buen_estado';

            // No se inventa depreciación ni cobro: solo queda candidato si comenzó con vida positiva y fue dañado.
            $estadoCobro = $estado === 'danado' && (int) $periodo->vida_util_al_asignar_segundos > 0
                ? 'pendiente_revision'
                : 'no_aplica';

            $periodo->update([
                'devuelto_en' => $devueltoEn,
                'tiempo_consumido_segundos' => $consumido,
                'vida_util_restante_segundos' => $restante,
                'estado_devolucion' => $estado,
                'motivo_devolucion' => $motivo,
                'observaciones_devolucion' => $observaciones,
                'reutilizable' => $reutilizable,
                'bodega_retorno_id' => $bodegaRetornoId,
                'devuelto_por_user_id' => auth()->id(),
                'estado_cobro' => $estadoCobro,
                'evidencia_calculo' => [
                    'vida_al_asignar_segundos' => $periodo->vida_util_al_asignar_segundos,
                    'tiempo_consumido_segundos' => $consumido,
                    'vida_restante_segundos' => $restante,
                    'valor_referencia' => $periodo->valor_referencia,
                    'regla' => $estadoCobro === 'pendiente_revision' ? 'revision_sin_formula_automatica' : 'no_aplica',
                ],
            ]);

            $periodo->existencia->update([
                'condicion' => $condicion,
                'bodega_id' => $estado === 'perdido' ? null : $bodegaRetornoId,
                'vida_util_restante_segundos' => $restante,
                'disponible_desde' => $reutilizable ? $devueltoEn : null,
            ]);
        }

        $this->sincronizarResumen($asignacion->producto_codigo, (int) $asignacion->bodega_id);
        if ($bodegaRetornoId !== (int) $asignacion->bodega_id) {
            $this->sincronizarResumen($asignacion->producto_codigo, $bodegaRetornoId);
        }
        return $periodos;
    }

    public function sincronizarResumen(string $productoCodigo, int $bodegaId): void
    {
        foreach (['nuevo', 'usado', 'danado', 'perdido', 'baja'] as $tipo) {
            $query = InventarioExistencia::where('producto_codigo', $productoCodigo)
                ->where('bodega_id', $bodegaId)->where('condicion', $tipo);
            $cantidad = (clone $query)->count();
            $vidaMinima = $tipo === 'usado' ? (clone $query)->min('vida_util_restante_segundos') : null;
            DB::table('inventarios')->updateOrInsert(
                ['producto_codigo' => $productoCodigo, 'bodega_id' => $bodegaId, 'stock_tipo' => $tipo],
                ['cantidad' => $cantidad, 'vida_util_restante_meses' => $vidaMinima === null ? null : intdiv((int) $vidaMinima, 30 * 86400), 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }
}

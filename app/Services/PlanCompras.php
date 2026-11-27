<?php

namespace App\Services;

use App\Models\Consolidado;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlanCompras
{
    public function aprobados(array $ids, bool $bloquear = false): Collection
    {
        $consulta = Consolidado::whereKey($ids)->where('estado', 'aprobado')->orderBy('id');
        $filas = ($bloquear ? $consulta->lockForUpdate() : $consulta)->get();
        if ($filas->count() !== count($ids)) {
            throw ValidationException::withMessages(['consolidados' => 'Selecciona consolidados aprobados y vigentes.']);
        }

        return $filas;
    }

    public function avance(Collection $consolidados, bool $bloquear = false): Collection
    {
        $lineas = DB::table('consolidado_lineas')->whereIn('consolidado_id', $consolidados->pluck('id'))
            ->join('productos', 'productos.codigo', '=', 'consolidado_lineas.producto_codigo')
            ->orderBy('consolidado_id')->orderBy('consolidado_lineas.id')
            ->get(['consolidado_lineas.*', 'productos.nombre']);
        $planes = DB::table('consolidado_planes_compra')->whereIn('consolidado_linea_id', $lineas->pluck('id'))
            ->orderByDesc('constancia_id');
        $recibidas = DB::table('compra_detalle_consolidado')->whereIn('consolidado_linea_id', $lineas->pluck('id'));

        // Tras bloquear el consolidado, MySQL debe leer las distribuciones y los planes ya confirmados.
        $planes = ($bloquear ? $planes->lockForUpdate() : $planes)->get()->unique('consolidado_linea_id')->keyBy('consolidado_linea_id');
        $recibidas = ($bloquear ? $recibidas->lockForUpdate() : $recibidas)->get()->groupBy('consolidado_linea_id');

        $planesInternos = $consolidados->keyBy('id')->map->plan_compra;

        return $lineas->map(function ($fila) use ($planes, $recibidas, $planesInternos) {
            $anterior = $planes->get($fila->id);
            $plan = $planesInternos->get($fila->consolidado_id)[$fila->id] ?? null;
            $comprada = (int) $recibidas->get($fila->id, collect())->sum('cantidad');
            $decidida = $plan['compra'] ?? ($anterior ? (int) $anterior->cantidad_decidida : null);
            $considerada = $plan['existencia'] ?? ($anterior ? (int) $anterior->existencia_considerada : null);

            return [
                'id' => $fila->id, 'consolidado_id' => $fila->consolidado_id,
                'codigo' => $fila->producto_codigo, 'producto' => $fila->nombre, 'aprobada' => (int) $fila->cantidad,
                'comprada' => $comprada, 'considerada' => $considerada, 'decidida' => $decidida,
                'pendiente' => max(0, ($decidida ?? $fila->cantidad) - $comprada),
                'sin_cobertura' => $decidida !== null ? max(0, $fila->cantidad - $considerada - $decidida) : 0,
            ];
        });
    }

    public function facturas(Collection $consolidados): Collection
    {
        return DB::table('compra_consolidado')->whereIn('consolidado_id', $consolidados->pluck('id'))
            ->join('compras', 'compras.id', '=', 'compra_consolidado.compra_id')
            ->join('proveedores', 'proveedores.id', '=', 'compras.proveedor_id')
            ->orderByDesc('compras.fecha_compra')->orderByDesc('compras.id')
            ->get(['compra_consolidado.consolidado_id', 'compras.id', 'compras.no_factura', 'compras.fecha_compra',
                'compras.distribucion_confirmada_en', 'compras.autorizado_por', 'proveedores.nombre as proveedor'])->groupBy('consolidado_id');
    }

    public function conciliacionesPendientes(Collection $consolidados, bool $bloquear = false): bool
    {
        $consulta = DB::table('compra_consolidado')->whereIn('consolidado_id', $consolidados->pluck('id'))
            ->join('compras', 'compras.id', '=', 'compra_consolidado.compra_id')
            ->whereNull('compras.distribucion_confirmada_en');

        return ($bloquear ? $consulta->lockForUpdate() : $consulta)->first(['compras.id']) !== null;
    }

    public function puedeCerrar(Consolidado $consolidado, bool $bloquear = false): bool
    {
        $seleccion = collect([$consolidado]);

        return !$this->conciliacionesPendientes($seleccion, $bloquear)
            && !$this->avance($seleccion, $bloquear)->contains(fn ($fila) => $fila['pendiente'] > 0 || $fila['sin_cobertura'] > 0);
    }

    public function saldoCentral(Collection $lineas, int $bodega): Collection
    {
        return DB::table('inventarios')->where('bodega_id', $bodega)->whereIn('stock_tipo', ['nuevo', 'usado'])
            ->whereIn('producto_codigo', $lineas->pluck('codigo')->unique())->groupBy('producto_codigo')
            ->select('producto_codigo')->selectRaw('SUM(cantidad) AS disponible')->pluck('disponible', 'producto_codigo');
    }

    public function sugerencias(Collection $seleccion, Collection $activas, Collection $saldo, bool $estimarStock = true): Collection
    {
        $libre = $estimarStock ? $this->saldoParaPlan($activas, $saldo)->all() : [];

        return $seleccion->map(function ($fila) use ($saldo, &$libre) {
            $codigo = $fila['codigo'];
            $considerada = $fila['considerada'];
            if ($considerada === null) {
                $considerada = min($fila['pendiente'], $libre[$codigo] ?? 0);
                $libre[$codigo] = ($libre[$codigo] ?? 0) - $considerada;
            }

            return $fila + [
                'central' => (int) $saldo->get($codigo, 0),
                'considerada_sugerida' => $considerada,
                'sugerida' => $fila['decidida'] === null ? $fila['pendiente'] - $considerada : $fila['pendiente'],
            ];
        });
    }

    public function resumen(Collection $lineas, Collection $facturas, ?Collection $saldo): Collection
    {
        $facturasPorId = $facturas->collapse()->keyBy('id');
        $reparto = DB::table('compra_detalle_consolidado as reparto')
            ->join('compra_detalles as detalle', 'detalle.id', '=', 'reparto.compra_detalle_id')
            ->whereIn('reparto.consolidado_linea_id', $lineas->pluck('id'))
            ->get(['reparto.consolidado_linea_id', 'detalle.compra_id', 'reparto.cantidad'])->groupBy('consolidado_linea_id');

        return $lineas->groupBy('codigo')->map(function ($filas, $codigo) use ($saldo, $facturasPorId, $reparto) {
            $comprada = (int) $filas->sum('comprada');
            $pendiente = $saldo !== null ? (int) $filas->sum('sugerida') : null;
            $facturadas = $filas->flatMap(fn ($fila) => $reparto->get($fila['id'], collect()))->groupBy('compra_id');

            return [
                'codigo' => $codigo, 'producto' => $filas->first()['producto'],
                'aprobada' => (int) $filas->sum('aprobada'), 'central' => $saldo !== null ? (int) $saldo->get($codigo, 0) : null,
                'considerada' => $filas->contains(fn ($fila) => $fila['considerada'] !== null) ? (int) $filas->sum('considerada') : null,
                'compra_total' => $pendiente !== null ? $comprada + $pendiente : null,
                'comprada' => $comprada, 'pendiente' => $pendiente, 'sin_cobertura' => $filas->sum('sin_cobertura'),
                'facturas' => $facturadas->map(fn ($grupo, $id) => [
                    'factura' => $facturasPorId->get($id), 'cantidad' => $grupo->sum('cantidad'),
                ])->filter(fn ($registro) => $registro['factura'] !== null)->values(),
            ];
        })->sortBy('producto', SORT_NATURAL | SORT_FLAG_CASE)->values();
    }

    private function saldoParaPlan(Collection $activas, Collection $saldo): Collection
    {
        // El saldo agregado puede incluir recepciones ya atribuidas. El cálculo es conservador y no reserva inventario.
        $cobertura = $activas->groupBy('codigo')->map(fn ($grupo) => $grupo->sum('comprada') + $grupo->sum('considerada'));

        return $saldo->map(fn ($cantidad, $codigo) => max(0, $cantidad - $cobertura->get($codigo, 0)));
    }

    public function validarPlan(Collection $seleccion, Collection $activas, Collection $saldo, array $propuestas): Collection
    {
        if ($seleccion->isEmpty() || array_diff($seleccion->pluck('id')->all(), array_keys($propuestas))
            || array_diff(array_keys($propuestas), $seleccion->pluck('id')->all())) {
            throw ValidationException::withMessages(['propuestas' => 'Completa el plan de cada producto del consolidado vigente.']);
        }

        $filas = $seleccion->map(function ($fila) use ($propuestas) {
            $considerada = (int) $propuestas[$fila['id']]['existencia'];
            $decidida = (int) $propuestas[$fila['id']]['compra'];
            if ($decidida < $fila['comprada'] || $considerada + $decidida > $fila['aprobada']) {
                throw ValidationException::withMessages(['propuestas' => "Revisa {$fila['codigo']}. Conserva las unidades recibidas y no superes la cantidad aprobada."]);
            }

            return $fila + ['existencia' => $considerada, 'propuesta' => $decidida];
        });
        $libre = $this->saldoParaPlan($activas, $saldo);
        foreach ($filas->groupBy('codigo') as $codigo => $grupo) {
            if ($grupo->sum('existencia') - $grupo->sum('considerada') > $libre->get($codigo, 0)) {
                throw ValidationException::withMessages(['propuestas' => "El saldo central de {$codigo} no alcanza para las nuevas unidades consideradas. Actualiza las necesidades."]);
            }
        }

        return $filas;
    }
}

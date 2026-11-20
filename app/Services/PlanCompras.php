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

    public function cantidades(Collection $consolidados, int $bodega): Collection
    {
        $aprobadas = DB::table('consolidado_lineas')->whereIn('consolidado_id', $consolidados->modelKeys())
            ->join('productos', 'productos.codigo', '=', 'consolidado_lineas.producto_codigo')
            ->select('producto_codigo', 'productos.nombre')->selectRaw('SUM(cantidad) AS aprobada')
            ->groupBy('producto_codigo', 'productos.nombre')->orderBy('productos.nombre')->get();
        $saldo = DB::table('inventarios')->where('bodega_id', $bodega)->whereIn('stock_tipo', ['nuevo', 'usado'])
            ->whereIn('producto_codigo', $aprobadas->pluck('producto_codigo'))->groupBy('producto_codigo')
            ->select('producto_codigo')->selectRaw('SUM(cantidad) AS disponible')->pluck('disponible', 'producto_codigo');

        return $aprobadas->map(function ($fila) use ($saldo) {
            $central = (int) $saldo->get($fila->producto_codigo, 0);

            return [
                'codigo' => $fila->producto_codigo, 'producto' => $fila->nombre, 'aprobada' => (int) $fila->aprobada,
                'central' => $central, 'sugerida' => max(0, $fila->aprobada - $central),
            ];
        });
    }
}

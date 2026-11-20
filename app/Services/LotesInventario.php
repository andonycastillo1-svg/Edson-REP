<?php

namespace App\Services;

use App\Models\InventarioLoteOperativo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class LotesInventario
{
    public function saldo(int $bodega, string $codigo, string $tipo): Builder
    {
        return InventarioLoteOperativo::where('bodega_id', $bodega)
            ->where('producto_codigo', $codigo)->where('stock_tipo', $tipo)
            ->where('cantidad', '>', 0);
    }

    public function registrar(int $bodega, string $codigo, string $tipo, int $cantidad, array $datos = []): void
    {
        InventarioLoteOperativo::create($datos + [
            'bodega_id' => $bodega, 'producto_codigo' => $codigo, 'stock_tipo' => $tipo, 'cantidad' => $cantidad,
        ]);
    }

    public function retirar(int $bodega, string $codigo, string $tipo, int $cantidad): Collection
    {
        $consulta = $this->saldo($bodega, $codigo, $tipo);
        if ($tipo === 'usado') {
            $consulta->orderByRaw('vida_restante_segundos IS NULL')->orderBy('vida_restante_segundos');
        }
        // El saldo histórico sin fecha se consume primero dentro de su condición.
        $lotes = $consulta->orderByRaw('ingresado_en IS NOT NULL')->orderBy('ingresado_en')->orderBy('id')
            ->limit($cantidad)->lockForUpdate()->get();

        $retirados = collect();
        foreach ($lotes as $lote) {
            $tomadas = min($cantidad, $lote->cantidad);
            $lote->decrement('cantidad', $tomadas);
            $retirados->push(['lote' => $lote, 'cantidad' => $tomadas]);
            $cantidad -= $tomadas;
            if ($cantidad === 0) {
                break;
            }
        }
        if ($cantidad !== 0) {
            throw new \LogicException('La salida debe coincidir con el saldo de sus lotes.');
        }

        return $retirados;
    }
}

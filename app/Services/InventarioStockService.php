<?php

namespace App\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventarioStockService
{
    public function incrementar(int $bodegaId, string $productoCodigo, int $cantidad): void
    {
        if ($cantidad <= 0) {
            return;
        }

        $inventario = DB::table('inventarios')
            ->where('bodega_id', $bodegaId)
            ->where('producto_codigo', $productoCodigo)
            ->lockForUpdate()
            ->first();

        if ($inventario) {
            DB::table('inventarios')
                ->where('id', $inventario->id)
                ->update([
                    'cantidad' => (int) $inventario->cantidad + $cantidad,
                    'updated_at' => now(),
                ]);

            return;
        }

        try {
            DB::table('inventarios')->insert([
                'bodega_id' => $bodegaId,
                'producto_codigo' => $productoCodigo,
                'cantidad' => $cantidad,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (QueryException $e) {
            // Si otra transaccion creo la fila entre la lectura y el insert, bloqueamos y sumamos.
            $inventario = DB::table('inventarios')
                ->where('bodega_id', $bodegaId)
                ->where('producto_codigo', $productoCodigo)
                ->lockForUpdate()
                ->first();

            if (!$inventario) {
                throw $e;
            }

            DB::table('inventarios')
                ->where('id', $inventario->id)
                ->update([
                    'cantidad' => (int) $inventario->cantidad + $cantidad,
                    'updated_at' => now(),
                ]);
        }
    }

    public function descontar(int $bodegaId, string $productoCodigo, int $cantidad): void
    {
        if ($cantidad <= 0) {
            return;
        }

        $inventario = DB::table('inventarios')
            ->where('bodega_id', $bodegaId)
            ->where('producto_codigo', $productoCodigo)
            ->lockForUpdate()
            ->first();

        $disponible = (int) ($inventario->cantidad ?? 0);

        if (!$inventario || $disponible < $cantidad) {
            throw ValidationException::withMessages([
                'stock' => "Stock insuficiente para {$productoCodigo}. Disponible: {$disponible}, solicitado: {$cantidad}.",
            ]);
        }

        DB::table('inventarios')
            ->where('id', $inventario->id)
            ->update([
                'cantidad' => $disponible - $cantidad,
                'updated_at' => now(),
            ]);
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class UpdateUniqueIndexInInventariosForStockTipo extends Migration
{
    public function up(): void
    {
        $indexes = collect(Schema::getIndexes('inventarios'))
            ->pluck('name')
            ->unique()
            ->values()
            ->toArray();

        if (in_array('uq_producto_bodega', $indexes)) {
            Schema::table('inventarios', function (Blueprint $table) {
                $table->dropUnique('uq_producto_bodega');
            });
        }

        if (!in_array('uq_producto_bodega_stock_tipo', $indexes)) {
            Schema::table('inventarios', function (Blueprint $table) {
                $table->unique(
                    ['producto_codigo', 'bodega_id', 'stock_tipo'],
                    'uq_producto_bodega_stock_tipo'
                );
            });
        }
    }

    public function down(): void
    {
        $indexes = collect(Schema::getIndexes('inventarios'))
            ->pluck('name')
            ->unique()
            ->values()
            ->toArray();

        if (in_array('uq_producto_bodega_stock_tipo', $indexes)) {
            Schema::table('inventarios', function (Blueprint $table) {
                $table->dropUnique('uq_producto_bodega_stock_tipo');
            });
        }

        if (!in_array('uq_producto_bodega', $indexes)) {
            Schema::table('inventarios', function (Blueprint $table) {
                $table->unique(
                    ['producto_codigo', 'bodega_id'],
                    'uq_producto_bodega'
                );
            });
        }
    }
}

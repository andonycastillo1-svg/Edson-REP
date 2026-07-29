<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $indexes = collect(Schema::getIndexes('inventarios'))->pluck('name');
        if ($indexes->contains('uq_producto_bodega')) {
            Schema::table('inventarios', fn (Blueprint $table) => $table->dropUnique('uq_producto_bodega'));
        }
    }

    public function down(): void
    {
        $indexes = collect(Schema::getIndexes('inventarios'))->pluck('name');
        if (!$indexes->contains('uq_producto_bodega')) {
            Schema::table('inventarios', fn (Blueprint $table) => $table->unique(['producto_codigo', 'bodega_id'], 'uq_producto_bodega'));
        }
    }
};

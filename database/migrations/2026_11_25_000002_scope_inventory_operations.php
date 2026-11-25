<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bodega_usuario_operativa', function (Blueprint $table) {
            $table->boolean('opera_inventario')->default(false);
        });

        Schema::table('compras', function (Blueprint $table) {
            $table->foreignId('bodega_id')->nullable()->constrained('bodegas')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('compras')->whereNotNull('bodega_id')->exists()
            || DB::table('bodega_usuario_operativa')->where('opera_inventario', true)->exists()) {
            throw new LogicException('Conserva la migración para preservar el alcance operativo y el origen de las compras.');
        }

        Schema::table('compras', fn (Blueprint $table) => $table->dropConstrainedForeignId('bodega_id'));
        Schema::table('bodega_usuario_operativa', fn (Blueprint $table) => $table->dropColumn('opera_inventario'));
    }
};

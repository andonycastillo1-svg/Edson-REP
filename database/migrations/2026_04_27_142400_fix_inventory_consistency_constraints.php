<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('asignacion_movimientos')) {
            $connection = Schema::getConnection()->getDriverName();

            if ($connection === 'mysql') {
                DB::statement("ALTER TABLE asignacion_movimientos MODIFY tipo ENUM('Asignacion', 'Devolucion', 'Reemplazo') NOT NULL");
            }
        }

        $connection = Schema::getConnection()->getDriverName();
        if ($connection !== 'sqlite' && Schema::hasTable('operacion_detalles') && Schema::hasTable('productos')) {
            $existingForeignKeys = collect(Schema::getForeignKeys('operacion_detalles'))
                ->pluck('name')
                ->all();

            if (in_array('operacion_detalles_producto_codigo_foreign', $existingForeignKeys, true)) {
                return;
            }

            if (DB::table('operacion_detalles as od')
                ->leftJoin('productos as p', 'p.codigo', '=', 'od.producto_codigo')
                ->whereNull('p.codigo')
                ->exists()) {
                throw new RuntimeException('No se puede agregar la llave foranea: existen detalles de traslado con productos inexistentes.');
            }

            Schema::table('operacion_detalles', function (Blueprint $table) {
                $table->foreign('producto_codigo', 'operacion_detalles_producto_codigo_foreign')
                    ->references('codigo')
                    ->on('productos')
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        $connection = Schema::getConnection()->getDriverName();
        if ($connection !== 'sqlite' && Schema::hasTable('operacion_detalles')) {
            Schema::table('operacion_detalles', function (Blueprint $table) {
                $table->dropForeign('operacion_detalles_producto_codigo_foreign');
            });
        }

        if (Schema::hasTable('asignacion_movimientos')) {
            $connection = Schema::getConnection()->getDriverName();

            if ($connection === 'mysql') {
                DB::statement("ALTER TABLE asignacion_movimientos MODIFY tipo ENUM('Asignacion', 'Devolucion') NOT NULL");
            }
        }
    }
};

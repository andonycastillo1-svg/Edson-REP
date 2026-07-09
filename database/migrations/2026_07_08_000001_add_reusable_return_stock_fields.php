<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventarios', function (Blueprint $table) {
            if (! Schema::hasColumn('inventarios', 'stock_tipo')) {
                $table->string('stock_tipo', 30)->default('nuevo')->after('cantidad')->index();
            }
            if (! Schema::hasColumn('inventarios', 'vida_util_restante_meses')) {
                $table->integer('vida_util_restante_meses')->nullable()->after('stock_tipo');
            }
        });

        try {
            Schema::table('inventarios', fn (Blueprint $table) => $table->dropUnique('uq_producto_bodega'));
        } catch (Throwable $e) {
            // El índice puede no existir en instalaciones antiguas.
        }

        try {
            Schema::table('inventarios', fn (Blueprint $table) => $table->unique(['producto_codigo', 'bodega_id', 'stock_tipo'], 'uq_producto_bodega_stock_tipo'));
        } catch (Throwable $e) {
            // Evita romper despliegues donde el índice ya fue creado manualmente.
        }

        Schema::table('asignaciones_inventarios', function (Blueprint $table) {
            if (! Schema::hasColumn('asignaciones_inventarios', 'stock_tipo')) {
                $table->string('stock_tipo', 30)->default('nuevo')->after('estado');
            }
            if (! Schema::hasColumn('asignaciones_inventarios', 'vida_util_original_meses')) {
                $table->unsignedSmallInteger('vida_util_original_meses')->nullable()->after('fecha_vencimiento');
            }
            if (! Schema::hasColumn('asignaciones_inventarios', 'vida_util_restante_meses')) {
                $table->integer('vida_util_restante_meses')->nullable()->after('vida_util_original_meses');
            }
        });

        Schema::table('asignacion_movimientos', function (Blueprint $table) {
            if (! Schema::hasColumn('asignacion_movimientos', 'estado_devolucion')) {
                $table->string('estado_devolucion', 30)->nullable()->after('grupo_devolucion')->index();
            }
            if (! Schema::hasColumn('asignacion_movimientos', 'stock_tipo_resultante')) {
                $table->string('stock_tipo_resultante', 30)->nullable()->after('estado_devolucion');
            }
            if (! Schema::hasColumn('asignacion_movimientos', 'vida_util_original_meses')) {
                $table->unsignedSmallInteger('vida_util_original_meses')->nullable()->after('stock_tipo_resultante');
            }
            if (! Schema::hasColumn('asignacion_movimientos', 'vida_util_consumida_meses')) {
                $table->integer('vida_util_consumida_meses')->nullable()->after('vida_util_original_meses');
            }
            if (! Schema::hasColumn('asignacion_movimientos', 'vida_util_restante_meses')) {
                $table->integer('vida_util_restante_meses')->nullable()->after('vida_util_consumida_meses');
            }
            if (! Schema::hasColumn('asignacion_movimientos', 'bodega_retorno_id')) {
                $table->foreignId('bodega_retorno_id')->nullable()->after('vida_util_restante_meses')->constrained('bodegas')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('asignacion_movimientos', function (Blueprint $table) {
            foreach (['bodega_retorno_id', 'vida_util_restante_meses', 'vida_util_consumida_meses', 'vida_util_original_meses', 'stock_tipo_resultante', 'estado_devolucion'] as $column) {
                if (Schema::hasColumn('asignacion_movimientos', $column)) {
                    if ($column === 'bodega_retorno_id') {
                        $table->dropConstrainedForeignId($column);
                    } else {
                        $table->dropColumn($column);
                    }
                }
            }
        });

        Schema::table('asignaciones_inventarios', function (Blueprint $table) {
            foreach (['vida_util_restante_meses', 'vida_util_original_meses', 'stock_tipo'] as $column) {
                if (Schema::hasColumn('asignaciones_inventarios', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        try { Schema::table('inventarios', fn (Blueprint $table) => $table->dropUnique('uq_producto_bodega_stock_tipo')); } catch (Throwable $e) {}
        Schema::table('inventarios', function (Blueprint $table) {
            if (Schema::hasColumn('inventarios', 'vida_util_restante_meses')) {
                $table->dropColumn('vida_util_restante_meses');
            }
            if (Schema::hasColumn('inventarios', 'stock_tipo')) {
                $table->dropColumn('stock_tipo');
            }
        });
        try { Schema::table('inventarios', fn (Blueprint $table) => $table->unique(['producto_codigo', 'bodega_id'], 'uq_producto_bodega')); } catch (Throwable $e) {}
    }
};

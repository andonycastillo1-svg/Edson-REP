<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventario_existencias', function (Blueprint $table) {
            $table->dateTime('ingresado_en')->nullable();
        });

        Schema::table('inventarios', function (Blueprint $table) {
            $table->boolean('lotes_inicializados')->default(false);
        });

        Schema::create('inventario_lotes_operativos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bodega_id')->constrained('bodegas')->cascadeOnDelete();
            $table->string('producto_codigo');
            $table->foreign('producto_codigo', 'lote_operativo_producto_fk')
                ->references('codigo')->on('productos')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('stock_tipo', 10);
            $table->unsignedInteger('cantidad');
            $table->dateTime('ingresado_en')->nullable();
            $table->unsignedInteger('vida_original_meses')->nullable();
            $table->unsignedBigInteger('vida_restante_segundos')->nullable();
            $table->decimal('valor_referencia', 10, 2)->nullable();
            $table->foreignId('retornado_desde_id')->nullable()
                ->constrained('asignaciones_inventarios', indexName: 'lote_asig_origen_fk')->restrictOnDelete();
            $table->index(['bodega_id', 'producto_codigo', 'stock_tipo'], 'lote_operativo_saldo_idx');
        });

        Schema::table('asignaciones_inventarios', function (Blueprint $table) {
            $table->foreignId('sustituida_por_id')->nullable()
                ->constrained('asignaciones_inventarios', indexName: 'asig_sustituida_por_fk')->restrictOnDelete();
            $table->foreignId('lote_operativo_id')->nullable()
                ->constrained('inventario_lotes_operativos', indexName: 'asig_lote_operativo_fk')->restrictOnDelete();
            $table->string('motivo_inventario_nuevo', 500)->nullable();
        });

        Schema::table('asignacion_movimientos', function (Blueprint $table) {
            $table->string('motivo_administrativo', 15)->nullable();
            $table->dateTime('ocurrio_en')->nullable();
            $table->unsignedBigInteger('vida_restante_segundos')->nullable();
        });

        Schema::table('alertas_reemplazos_rrhh', function (Blueprint $table) {
            $table->string('motivo_revision', 40)->nullable();
            $table->unsignedBigInteger('vida_restante_segundos')->nullable();
        });

        Schema::table('alerta_rrhh_actuaciones', function (Blueprint $table) {
            $table->string('accion', 30)->nullable();
        });
    }

    public function down(): void
    {
        throw new LogicException('Las relaciones de custodia, los lotes y las actuaciones deben conservarse al revertir el código.');
    }
};

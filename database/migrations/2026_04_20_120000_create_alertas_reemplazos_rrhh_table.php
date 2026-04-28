<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('alertas_reemplazos_rrhh', function (Blueprint $table) {
            $table->id();
            $table->string('colaborador_codigo', 20);
            $table->string('producto_codigo', 50);
            $table->foreignId('asignacion_anterior_id')->nullable()->constrained('asignaciones_inventarios')->nullOnDelete();
            $table->foreignId('asignacion_nueva_id')->nullable()->constrained('asignaciones_inventarios')->nullOnDelete();
            $table->dateTime('fecha_asignacion_anterior');
            $table->dateTime('fecha_dano_reemplazo');
            $table->unsignedSmallInteger('vida_util_meses')->default(0);
            $table->integer('meses_restantes')->default(0);
            $table->boolean('descuento_aplicable')->default(true);
            $table->string('estado', 30)->default('pendiente');
            $table->text('detalle')->nullable();
            $table->timestamps();

            $table->index(['colaborador_codigo', 'producto_codigo'], 'idx_alerta_colab_producto');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alertas_reemplazos_rrhh');
    }
};

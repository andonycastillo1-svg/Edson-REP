<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            $table->timestamp('distribucion_confirmada_en')->nullable();
        });

        Schema::create('consolidado_planes_compra', function (Blueprint $table) {
            $table->foreignId('consolidado_linea_id')->constrained('consolidado_lineas')->restrictOnDelete();
            $table->foreignId('constancia_id')->constrained('constancias_gerencia')->restrictOnDelete();
            $table->unsignedInteger('existencia_considerada');
            $table->unsignedInteger('cantidad_decidida');
            $table->primary(['consolidado_linea_id', 'constancia_id'], 'consolidado_planes_compra_pk');
        });

        Schema::create('compra_detalle_consolidado', function (Blueprint $table) {
            $table->foreignId('consolidado_linea_id')->constrained('consolidado_lineas')->restrictOnDelete();
            $table->foreignId('compra_detalle_id')->constrained('compra_detalles')->restrictOnDelete();
            $table->unsignedInteger('cantidad');
            $table->primary(['consolidado_linea_id', 'compra_detalle_id'], 'compra_detalle_consolidado_pk');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compra_detalle_consolidado');
        Schema::dropIfExists('consolidado_planes_compra');
        Schema::table('compras', function (Blueprint $table) {
            $table->dropColumn('distribucion_confirmada_en');
        });
    }
};

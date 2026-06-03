<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehiculo_producto_archivos', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('vehiculo_producto_asignacion_id');

            $table->string('tipo_documento', 50);
            $table->string('archivo_path');
            $table->string('archivo_nombre_original');
            $table->unsignedBigInteger('subido_por')->nullable();
            $table->timestamp('subido_en')->nullable();

            $table->timestamps();

            $table->index('vehiculo_producto_asignacion_id', 'vpa_archivos_asig_idx');
            $table->index('tipo_documento', 'vpa_archivos_tipo_idx');
            $table->index('subido_por', 'vpa_archivos_user_idx');

            $table->foreign('vehiculo_producto_asignacion_id', 'vpa_archivos_asig_fk')
                ->references('id')
                ->on('vehiculo_producto_asignaciones')
                ->onDelete('cascade');

            $table->foreign('subido_por', 'vpa_archivos_user_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vehiculo_producto_archivos', function (Blueprint $table) {
            $table->dropForeign('vpa_archivos_asig_fk');
            $table->dropForeign('vpa_archivos_user_fk');
        });

        Schema::dropIfExists('vehiculo_producto_archivos');
    }
};
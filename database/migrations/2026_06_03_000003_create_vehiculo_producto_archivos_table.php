<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vehiculo_producto_archivos')) {
            return;
        }

        Schema::create('vehiculo_producto_archivos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehiculo_producto_asignacion_id')->constrained('vehiculo_producto_asignaciones')->cascadeOnDelete();
            $table->string('tipo_documento', 50);
            $table->string('ruta');
            $table->string('nombre_original')->nullable();
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('tamano')->nullable();
            $table->foreignId('subido_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['vehiculo_producto_asignacion_id', 'tipo_documento'], 'vpa_asig_tipo_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehiculo_producto_archivos');
    }
};

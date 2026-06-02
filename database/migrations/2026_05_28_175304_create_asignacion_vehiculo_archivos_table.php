<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asignacion_vehiculo_archivos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('asignacion_vehiculo_id')
                ->constrained('asignaciones_vehiculos')
                ->cascadeOnDelete();

            $table->string('tipo_documento', 50);
            $table->string('ruta');
            $table->string('nombre_original')->nullable();
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('tamano')->nullable();

            $table->foreignId('subido_por_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['asignacion_vehiculo_id', 'tipo_documento'], 'ava_asig_tipo_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asignacion_vehiculo_archivos');
    }
};
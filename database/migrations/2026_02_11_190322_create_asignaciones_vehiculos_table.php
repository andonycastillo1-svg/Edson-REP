<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('asignaciones_vehiculos', function (Blueprint $table) {
    $table->id();

    $table->string('vehiculo_vin', 50);
    $table->foreign('vehiculo_vin')->references('vin')->on('vehiculos')
        ->cascadeOnUpdate()
        ->restrictOnDelete();

    $table->string('colaborador_codigo', 20);
    $table->foreign('colaborador_codigo')->references('codigo')->on('colaboradores')
        ->cascadeOnUpdate()
        ->restrictOnDelete();

    $table->dateTime('fecha_inicio')->useCurrent();
    $table->dateTime('fecha_fin')->nullable();

    $table->timestamps();
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asignaciones_vehiculos');
    }
};

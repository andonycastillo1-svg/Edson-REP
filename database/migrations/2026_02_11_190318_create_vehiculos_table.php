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
       Schema::create('vehiculos', function (Blueprint $table) {
    $table->string('vin', 50)->primary();
    $table->string('placa', 20)->unique();
    $table->string('marca', 50)->nullable();
    $table->string('modelo', 50)->nullable();
    $table->enum('estado', ['Disponible','En uso','Mantenimiento'])->default('Disponible');
    $table->timestamps();
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehiculos');
    }
};

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
        Schema::create('colaboradores', function (Blueprint $table) {
    $table->string('codigo', 20)->primary();
    $table->string('nombre', 100);
    $table->string('telefono', 20)->nullable();
    $table->string('puesto', 80)->nullable();
    $table->enum('estado', ['Activo','Inactivo'])->default('Activo');
    $table->timestamps();
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('colaboradores');
    }
};

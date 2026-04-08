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
       Schema::create('asignaciones_inventarios', function (Blueprint $table) {
    $table->id();

    $table->string('colaborador_codigo', 20);
    $table->foreign('colaborador_codigo')->references('codigo')->on('colaboradores')
        ->cascadeOnUpdate()
        ->restrictOnDelete();

    $table->string('producto_codigo', 50);
    $table->foreign('producto_codigo')->references('codigo')->on('productos')
        ->cascadeOnUpdate()
        ->restrictOnDelete();

    $table->foreignId('bodega_id')->constrained('bodegas')
        ->cascadeOnUpdate()
        ->restrictOnDelete();

    $table->unsignedInteger('cantidad_asignada');
    $table->dateTime('fecha')->useCurrent();

    $table->timestamps();
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asignaciones_inventarios');
    }
};

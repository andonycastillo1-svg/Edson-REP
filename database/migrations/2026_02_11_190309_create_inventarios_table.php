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
        Schema::create('inventarios', function (Blueprint $table) {
    $table->id();

    $table->string('producto_codigo', 50);
    $table->foreign('producto_codigo')->references('codigo')->on('productos')
        ->cascadeOnUpdate()
        ->restrictOnDelete();

    $table->foreignId('bodega_id')->constrained('bodegas')
        ->cascadeOnUpdate()
        ->restrictOnDelete();

    $table->unsignedInteger('cantidad')->default(0);

    $table->unique(['producto_codigo', 'bodega_id'], 'uq_producto_bodega');
    $table->timestamps();
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventarios');
    }
};

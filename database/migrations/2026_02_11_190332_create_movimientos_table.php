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
       Schema::create('movimientos', function (Blueprint $table) {
    $table->id();

    $table->string('producto_codigo', 50);
    $table->foreign('producto_codigo')->references('codigo')->on('productos')
        ->cascadeOnUpdate()
        ->restrictOnDelete();

    $table->foreignId('bodega_origen_id')->nullable()->constrained('bodegas')
        ->nullOnDelete();

    $table->foreignId('bodega_destino_id')->nullable()->constrained('bodegas')
        ->nullOnDelete();

    $table->enum('tipo_movimiento', ['Entrada','Salida','Traslado']);
    $table->unsignedInteger('cantidad');

    $table->dateTime('fecha')->useCurrent();

    // IMPORTANTE: aquí usamos users (Breeze), no usuarios
    $table->foreignId('user_id')->nullable()->constrained('users')
        ->nullOnDelete();

    $table->string('vehiculo_vin', 50)->nullable();
    $table->foreign('vehiculo_vin')->references('vin')->on('vehiculos')
        ->cascadeOnUpdate()
        ->nullOnDelete();

    $table->timestamps();
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movimientos');
    }
};

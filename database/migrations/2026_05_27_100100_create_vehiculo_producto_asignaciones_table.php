<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehiculo_producto_asignaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asignacion_vehiculo_id')->constrained('asignaciones_vehiculos')->cascadeOnDelete();
            $table->string('vehiculo_vin', 50);
            $table->foreign('vehiculo_vin')->references('vin')->on('vehiculos')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('producto_codigo', 50);
            $table->foreign('producto_codigo')->references('codigo')->on('productos')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('bodega_id')->constrained('bodegas')->restrictOnDelete();
            $table->unsignedInteger('cantidad');
            $table->enum('tipo_control', ['unidad', 'cantidad'])->default('cantidad');
            $table->string('serial', 120)->nullable();
            $table->date('fecha');
            $table->foreignId('asignado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('observaciones')->nullable();
            $table->boolean('activa')->default(true);
            $table->timestamps();

            $table->index(['vehiculo_vin', 'activa']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehiculo_producto_asignaciones');
    }
};

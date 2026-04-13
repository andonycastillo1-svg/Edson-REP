<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asignacion_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asignacion_inventario_id')->constrained('asignaciones_inventarios')->cascadeOnDelete();
            $table->enum('tipo', ['Asignacion', 'Devolucion']);
            $table->unsignedInteger('cantidad');
            $table->text('detalle')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asignacion_movimientos');
    }
};

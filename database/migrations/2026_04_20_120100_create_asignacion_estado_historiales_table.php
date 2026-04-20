<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('asignacion_estado_historiales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asignacion_inventario_id')->constrained('asignaciones_inventarios')->cascadeOnDelete();
            $table->string('estado', 30); // activo, danado, depreciado, reutilizado
            $table->dateTime('fecha_evento')->useCurrent();
            $table->text('detalle')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['asignacion_inventario_id', 'estado'], 'idx_hist_asig_estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asignacion_estado_historiales');
    }
};

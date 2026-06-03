<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('asignacion_inventario_archivos')) {
            return;
        }

        Schema::create('asignacion_inventario_archivos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asignacion_inventario_id')->nullable()->constrained('asignaciones_inventarios')->cascadeOnDelete();
            $table->uuid('grupo_devolucion')->nullable();
            $table->string('tipo_documento', 50);
            $table->string('ruta');
            $table->string('nombre_original')->nullable();
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('tamano')->nullable();
            $table->foreignId('subido_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['asignacion_inventario_id', 'tipo_documento'], 'aia_asig_tipo_idx');
            $table->index(['grupo_devolucion', 'tipo_documento'], 'aia_grupo_tipo_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asignacion_inventario_archivos');
    }
};

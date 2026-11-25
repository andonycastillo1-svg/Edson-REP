<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bajas_laborales', function (Blueprint $table) {
            $table->id();
            $table->string('colaborador_codigo', 20);
            $table->foreign('colaborador_codigo')->references('codigo')->on('colaboradores')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('registrada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motivo', 12);
            $table->date('fecha_efectiva');
            $table->text('observaciones')->nullable();
            $table->timestamp('cerrada_en')->nullable();
            $table->timestamps();
            $table->index(['colaborador_codigo', 'cerrada_en']);
        });

        foreach (['asignacion_movimientos', 'incidencias_inventario'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->foreignId('baja_laboral_id')->nullable()->constrained('bajas_laborales')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (DB::table('bajas_laborales')->exists()) {
            throw new RuntimeException('El esquema conserva bajas laborales registradas y sus referencias.');
        }

        foreach (['asignacion_movimientos', 'incidencias_inventario'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->dropConstrainedForeignId('baja_laboral_id');
            });
        }

        Schema::dropIfExists('bajas_laborales');
    }
};

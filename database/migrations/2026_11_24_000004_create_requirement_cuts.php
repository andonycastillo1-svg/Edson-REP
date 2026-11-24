<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cortes_requerimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operador_id')->constrained('users')->restrictOnDelete();
            $table->string('nombre', 160);
            $table->date('desde');
            $table->date('hasta');
            $table->string('estado', 20)->default('borrador');
            $table->timestamps();
            $table->index(['estado', 'operador_id']);
        });

        Schema::create('corte_inspeccion', function (Blueprint $table) {
            $table->foreignId('corte_id')->constrained('cortes_requerimientos')->restrictOnDelete();
            $table->foreignId('inspeccion_id')->constrained('inspecciones')->restrictOnDelete();
            $table->boolean('anterior');
            $table->primary(['corte_id', 'inspeccion_id']);
        });

        Schema::table('consolidados', function (Blueprint $table) {
            $table->foreignId('corte_id')->nullable()->constrained('cortes_requerimientos')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('cortes_requerimientos')->exists()) {
            throw new LogicException('Conserva la migración para preservar el historial de cortes.');
        }

        Schema::table('consolidados', fn (Blueprint $table) => $table->dropConstrainedForeignId('corte_id'));
        Schema::dropIfExists('corte_inspeccion');
        Schema::dropIfExists('cortes_requerimientos');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alertas_reemplazos_rrhh', fn (Blueprint $tabla) => $tabla->string('politica_motivo', 30)->nullable());
        foreach (['cambio_por_dano' => ['Cambio por daño', 'proporcional'], 'cambio_por_energuate' => ['Cambio por Energuate', 'sin_propuesta']] as $codigo => [$nombre, $politica]) {
            DB::table('motivos_asignacion')->insertOrIgnore(['codigo' => $codigo, 'nombre' => $nombre, 'politica' => $politica]);
        }
    }

    public function down(): void
    {
        throw new LogicException('Conserva la política histórica de los reemplazos y los motivos configurados.');
    }
};

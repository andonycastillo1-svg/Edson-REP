<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Conserva la relación más antigua si existieran datos previos con varios
        // almacenistas para el mismo supervisor antes de aplicar la restricción.
        $supervisoresDuplicados = DB::table('almacenista_supervisores')
            ->select('supervisor_id')
            ->groupBy('supervisor_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('supervisor_id');

        foreach ($supervisoresDuplicados as $supervisorId) {
            $relacionesSobrantes = DB::table('almacenista_supervisores')
                ->where('supervisor_id', $supervisorId)
                ->orderBy('id')
                ->pluck('id')
                ->slice(1);

            if ($relacionesSobrantes->isNotEmpty()) {
                DB::table('almacenista_supervisores')
                    ->whereIn('id', $relacionesSobrantes)
                    ->delete();
            }
        }

        Schema::table('almacenista_supervisores', function (Blueprint $table) {
            $table->unique('supervisor_id', 'alm_sup_sup_unique');
        });
    }

    public function down(): void
    {
        Schema::table('almacenista_supervisores', function (Blueprint $table) {
            $table->dropUnique('alm_sup_sup_unique');
        });
    }
};

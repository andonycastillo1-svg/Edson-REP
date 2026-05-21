<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asignaciones_inventarios', function (Blueprint $table) {
            if (!Schema::hasColumn('asignaciones_inventarios', 'grupo_asignacion')) {
                $table->string('grupo_asignacion', 80)->nullable()->after('id')->index();
            }
        });

        DB::table('asignaciones_inventarios')
            ->whereNull('grupo_asignacion')
            ->orderBy('id')
            ->chunkById(100, function ($asignaciones) {
                foreach ($asignaciones as $asignacion) {
                    DB::table('asignaciones_inventarios')
                        ->where('id', $asignacion->id)
                        ->update([
                            'grupo_asignacion' => 'legacy-' . $asignacion->id,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('asignaciones_inventarios', function (Blueprint $table) {
            if (Schema::hasColumn('asignaciones_inventarios', 'grupo_asignacion')) {
                $table->dropIndex(['grupo_asignacion']);
                $table->dropColumn('grupo_asignacion');
            }
        });
    }
};
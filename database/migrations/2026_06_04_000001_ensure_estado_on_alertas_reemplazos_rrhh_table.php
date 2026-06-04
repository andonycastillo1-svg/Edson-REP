<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('alertas_reemplazos_rrhh')) {
            return;
        }

        if (!Schema::hasColumn('alertas_reemplazos_rrhh', 'estado')) {
            Schema::table('alertas_reemplazos_rrhh', function (Blueprint $table) {
                $table->string('estado', 30)->default('pendiente')->after('descuento_aplicable');
            });
        }

        DB::table('alertas_reemplazos_rrhh')
            ->whereNull('estado')
            ->update(['estado' => 'pendiente']);
    }

    public function down(): void
    {
        // No se elimina la columna porque puede existir desde la migración original
        // y contiene el estado operativo de las alertas existentes.
    }
};

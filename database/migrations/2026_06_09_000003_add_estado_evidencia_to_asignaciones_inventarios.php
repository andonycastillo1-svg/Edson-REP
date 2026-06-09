<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asignaciones_inventarios', function (Blueprint $table) {
            // Los históricos se consideran completos para no alterar su comportamiento actual.
            // Las asignaciones nuevas se crean explícitamente como pendientes desde el controlador.
            $table->string('estado_evidencia', 20)
                ->default('completa')
                ->after('estado');
        });
    }

    public function down(): void
    {
        Schema::table('asignaciones_inventarios', function (Blueprint $table) {
            $table->dropColumn('estado_evidencia');
        });
    }
};

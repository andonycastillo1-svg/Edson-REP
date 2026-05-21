<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asignacion_movimientos', function (Blueprint $table) {
            if (!Schema::hasColumn('asignacion_movimientos', 'grupo_devolucion')) {
                $table->uuid('grupo_devolucion')->nullable()->after('detalle')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('asignacion_movimientos', function (Blueprint $table) {
            if (Schema::hasColumn('asignacion_movimientos', 'grupo_devolucion')) {
                $table->dropColumn('grupo_devolucion');
            }
        });
    }
};
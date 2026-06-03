<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operaciones', function (Blueprint $table) {
            if (!Schema::hasColumn('operaciones', 'archivo_excel_path')) {
                $table->string('archivo_excel_path')->nullable()->after('observacion');
            }

            if (!Schema::hasColumn('operaciones', 'archivo_excel_nombre')) {
                $table->string('archivo_excel_nombre')->nullable()->after('archivo_excel_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('operaciones', function (Blueprint $table) {
            $columnas = array_filter([
                Schema::hasColumn('operaciones', 'archivo_excel_path') ? 'archivo_excel_path' : null,
                Schema::hasColumn('operaciones', 'archivo_excel_nombre') ? 'archivo_excel_nombre' : null,
            ]);

            if (!empty($columnas)) {
                $table->dropColumn($columnas);
            }
        });
    }
};

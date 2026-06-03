<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operaciones', function (Blueprint $table) {
            $table->string('archivo_excel_path')->nullable()->after('observacion');
            $table->string('archivo_excel_nombre')->nullable()->after('archivo_excel_path');
        });
    }

    public function down(): void
    {
        Schema::table('operaciones', function (Blueprint $table) {
            $table->dropColumn(['archivo_excel_path', 'archivo_excel_nombre']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asignaciones_inventarios', function (Blueprint $table) {
            $table->string('pdf_firmado')->nullable()->after('imagen');
        });
    }

    public function down(): void
    {
        Schema::table('asignaciones_inventarios', function (Blueprint $table) {
            $table->dropColumn('pdf_firmado');
        });
    }
};
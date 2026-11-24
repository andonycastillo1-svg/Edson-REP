<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspeccion_lineas', function (Blueprint $table) {
            $table->unsignedBigInteger('cantidad')->change();
        });
        Schema::table('consolidado_lineas', function (Blueprint $table) {
            $table->unsignedBigInteger('original')->change();
            $table->unsignedBigInteger('cantidad')->change();
        });
    }

    public function down(): void
    {
        if (DB::table('inspeccion_lineas')->where('cantidad', '>', 65535)->exists()
            || DB::table('consolidado_lineas')->where('original', '>', 4294967295)->orWhere('cantidad', '>', 4294967295)->exists()) {
            throw new RuntimeException('No se puede revertir sin perder cantidades que superan la capacidad de las columnas anteriores.');
        }

        Schema::table('consolidado_lineas', function (Blueprint $table) {
            $table->unsignedInteger('original')->change();
            $table->unsignedInteger('cantidad')->change();
        });
        Schema::table('inspeccion_lineas', function (Blueprint $table) {
            $table->unsignedSmallInteger('cantidad')->change();
        });
    }
};

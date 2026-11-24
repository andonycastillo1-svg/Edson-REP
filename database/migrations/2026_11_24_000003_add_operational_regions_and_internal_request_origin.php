<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('region_usuario_operativa', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('region_id')->constrained('regiones')->restrictOnDelete();
            $table->primary(['user_id', 'region_id']);
        });
        Schema::table('importaciones_asignacion', function (Blueprint $table) {
            $table->string('huella_operativa', 64)->nullable()->unique();
        });
        Schema::table('asignaciones_inventarios', function (Blueprint $table) {
            $table->enum('medio_solicitud', ['WhatsApp', 'Correo', 'Requerimiento interno'])->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('region_usuario_operativa')->exists()
            || DB::table('asignaciones_inventarios')->where('medio_solicitud', 'Requerimiento interno')->exists()
            || DB::table('importaciones_asignacion')->whereNotNull('huella_operativa')->exists()) {
            throw new RuntimeException('Conserva la migración para preservar las regiones y el origen de las asignaciones.');
        }
        Schema::table('asignaciones_inventarios', fn (Blueprint $table) =>
            $table->enum('medio_solicitud', ['WhatsApp', 'Correo'])->nullable()->change());
        Schema::table('importaciones_asignacion', function (Blueprint $table) {
            $table->dropUnique(['huella_operativa']);
            $table->dropColumn('huella_operativa');
        });
        Schema::dropIfExists('region_usuario_operativa');
    }
};

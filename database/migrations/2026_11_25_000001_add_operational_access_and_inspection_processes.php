<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procesos_inspeccion', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120)->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
        DB::table('procesos_inspeccion')->insert([
            ['nombre' => 'Cortes y Reconexión', 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Nuevos Suministros', 'created_at' => now(), 'updated_at' => now()],
        ]);
        Schema::table('inspecciones', function (Blueprint $table) {
            $table->foreignId('proceso_id')->nullable()->constrained('procesos_inspeccion')->restrictOnDelete();
            $table->string('proceso_nombre', 120)->nullable();
            $table->index(['proceso_id', 'fecha']);
        });
        Schema::create('bodega_usuario_operativa', function (Blueprint $table) {
            $table->foreignId('bodega_id')->constrained('bodegas')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->boolean('opera_requerimientos')->default(false);
            $table->primary(['bodega_id', 'user_id']);
        });
        Schema::create('region_coordinador_operativo', function (Blueprint $table) {
            $table->foreignId('region_id')->constrained('regiones')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->primary(['region_id', 'user_id']);
        });
        Schema::table('cortes_requerimientos', function (Blueprint $table) {
            $table->json('coordinadores_elegidos')->nullable();
        });
        Schema::create('delegaciones_operativas', function (Blueprint $table) {
            $table->id();
            foreach (['titular_id', 'sustituto_id', 'autorizada_por', 'revocada_por'] as $campo) {
                $table->foreignId($campo)->nullable($campo === 'revocada_por')->constrained('users')->restrictOnDelete();
            }
            $table->json('permisos');
            $table->dateTime('vigente_desde');
            $table->dateTime('vigente_hasta');
            $table->string('motivo', 500);
            $table->dateTime('revocada_en')->nullable();
            $table->timestamps();
            $table->index(['sustituto_id', 'revocada_en', 'vigente_desde', 'vigente_hasta'], 'delegacion_sustituto_vigencia');
            $table->index(['titular_id', 'vigente_hasta']);
        });
        foreach (['region' => 'regiones', 'bodega' => 'bodegas'] as $ambito => $tabla) {
            Schema::create("delegacion_$ambito", function (Blueprint $table) use ($ambito, $tabla) {
                $table->foreignId('delegacion_id')->constrained('delegaciones_operativas')->restrictOnDelete();
                $table->foreignId($ambito.'_id')->constrained($tabla)->restrictOnDelete();
                $table->primary(['delegacion_id', $ambito.'_id']);
            });
        }
    }

    public function down(): void
    {
        if (DB::table('inspecciones')->whereNotNull('proceso_id')->exists()
            || DB::table('bodega_usuario_operativa')->exists() || DB::table('delegaciones_operativas')->exists() || DB::table('region_coordinador_operativo')->exists()
            || DB::table('cortes_requerimientos')->whereNotNull('coordinadores_elegidos')->exists()
            || DB::table('procesos_inspeccion')->count() !== 2
            || DB::table('procesos_inspeccion')->whereColumn('created_at', '<>', 'updated_at')->exists()) {
            throw new LogicException('Conserva la migración para preservar las relaciones y el historial operativo.');
        }
        Schema::drop('delegacion_bodega');
        Schema::drop('delegacion_region');
        Schema::drop('delegaciones_operativas');
        Schema::table('cortes_requerimientos', fn (Blueprint $table) => $table->dropColumn('coordinadores_elegidos'));
        Schema::drop('region_coordinador_operativo');
        Schema::drop('bodega_usuario_operativa');
        Schema::table('inspecciones', function (Blueprint $table) {
            $table->dropIndex(['proceso_id', 'fecha']);
            $table->dropConstrainedForeignId('proceso_id');
            $table->dropColumn('proceso_nombre');
        });
        Schema::drop('procesos_inspeccion');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regiones', function (Blueprint $table) {
            $table->foreignId('coordinador_id')->nullable()->constrained('users')->restrictOnDelete();
        });
        Schema::create('region_coordinador_historial', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->constrained('regiones')->restrictOnDelete();
            $table->foreignId('anterior_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('coordinador_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');
        });
        Schema::table('inspecciones', function (Blueprint $table) {
            $table->unsignedBigInteger('coordinador_id')->nullable()->change();
            $table->text('observaciones')->nullable();
            $table->boolean('captura_regional')->default(false);
        });
        Schema::table('consolidados', function (Blueprint $table) {
            $table->boolean('detalle_individual')->default(false);
        });
        Schema::create('consolidado_colaboradores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consolidado_id')->constrained('consolidados')->restrictOnDelete();
            $table->string('colaborador_codigo');
            $table->foreign('colaborador_codigo', 'requerimiento_colaborador_fk')->references('codigo')->on('colaboradores')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('region_id')->constrained('regiones')->restrictOnDelete();
            $table->foreignId('agregado_por')->nullable()->constrained('users')->restrictOnDelete();
            $table->unique(['consolidado_id', 'colaborador_codigo'], 'consolidado_colaborador_unico');
        });
        Schema::create('consolidado_necesidades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consolidado_colaborador_id')->constrained('consolidado_colaboradores')->restrictOnDelete();
            $table->string('producto_codigo');
            $table->foreign('producto_codigo', 'requerimiento_producto_fk')->references('codigo')->on('productos')->cascadeOnUpdate()->restrictOnDelete();
            $table->unsignedBigInteger('original');
            $table->unsignedBigInteger('cantidad');
            $table->unique(['consolidado_colaborador_id', 'producto_codigo'], 'consolidado_necesidad_unica');
        });
        Schema::table('asignaciones_inventarios', function (Blueprint $table) {
            $table->foreignId('requerimiento_id')->nullable()->constrained('consolidado_necesidades')->restrictOnDelete();
        });
        Schema::table('importaciones_asignacion', function (Blueprint $table) {
            $table->foreignId('consolidado_id')->nullable()->constrained('consolidados')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('region_coordinador_historial')->exists() || DB::table('consolidado_colaboradores')->exists()
            || DB::table('inspecciones')->where('captura_regional', true)->exists()) {
            throw new RuntimeException('Hay datos regionales registrados. Conserva la migración para preservar su historial.');
        }

        Schema::table('importaciones_asignacion', fn (Blueprint $table) => $table->dropConstrainedForeignId('consolidado_id'));
        Schema::table('asignaciones_inventarios', fn (Blueprint $table) => $table->dropConstrainedForeignId('requerimiento_id'));
        Schema::dropIfExists('consolidado_necesidades');
        Schema::dropIfExists('consolidado_colaboradores');
        Schema::table('consolidados', fn (Blueprint $table) => $table->dropColumn('detalle_individual'));
        Schema::table('inspecciones', function (Blueprint $table) {
            $table->dropColumn(['observaciones', 'captura_regional']);
            $table->unsignedBigInteger('coordinador_id')->nullable(false)->change();
        });
        Schema::dropIfExists('region_coordinador_historial');
        Schema::table('regiones', fn (Blueprint $table) => $table->dropConstrainedForeignId('coordinador_id'));
    }
};

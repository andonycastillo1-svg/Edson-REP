<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventario_existencias', function (Blueprint $table) {
            $table->id();
            $table->string('producto_codigo', 50);
            $table->foreignId('bodega_id')->nullable();
            $table->string('condicion', 30)->default('nuevo');
            $table->unsignedBigInteger('vida_util_inicial_segundos')->nullable();
            $table->unsignedBigInteger('vida_util_restante_segundos')->nullable();
            $table->decimal('valor_referencia', 12, 2)->nullable();
            $table->timestamp('disponible_desde')->nullable();
            $table->foreignId('creado_por_user_id')->nullable();
            $table->timestamps();

            $table->foreign('producto_codigo')->references('codigo')->on('productos')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('bodega_id')->references('id')->on('bodegas')->nullOnDelete();
            $table->foreign('creado_por_user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['producto_codigo', 'bodega_id', 'condicion'], 'inv_exist_disp_idx');
            $table->index(['producto_codigo', 'vida_util_restante_segundos'], 'inv_exist_vida_idx');
        });

        Schema::create('asignacion_periodos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asignacion_inventario_id');
            $table->foreignId('inventario_existencia_id');
            $table->string('tipo_inventario', 10);
            $table->string('estado_entrega', 30)->default('buen_estado');
            $table->timestamp('asignado_en');
            $table->timestamp('devuelto_en')->nullable();
            $table->unsignedBigInteger('vida_util_al_asignar_segundos')->nullable();
            $table->unsignedBigInteger('tiempo_consumido_segundos')->nullable();
            $table->unsignedBigInteger('vida_util_restante_segundos')->nullable();
            $table->string('estado_devolucion', 30)->nullable();
            $table->string('motivo_devolucion', 150)->nullable();
            $table->text('observaciones_devolucion')->nullable();
            $table->boolean('reutilizable')->nullable();
            $table->foreignId('bodega_origen_id');
            $table->foreignId('bodega_retorno_id')->nullable();
            $table->foreignId('asignado_por_user_id')->nullable();
            $table->foreignId('devuelto_por_user_id')->nullable();
            $table->decimal('valor_referencia', 12, 2)->nullable();
            $table->decimal('cobro_calculado', 12, 2)->nullable();
            $table->string('estado_cobro', 30)->default('no_aplica');
            $table->json('evidencia_calculo')->nullable();
            $table->timestamps();

            $table->foreign('asignacion_inventario_id', 'asig_periodo_asig_fk')->references('id')->on('asignaciones_inventarios')->restrictOnDelete();
            $table->foreign('inventario_existencia_id', 'asig_periodo_exist_fk')->references('id')->on('inventario_existencias')->restrictOnDelete();
            $table->foreign('bodega_origen_id')->references('id')->on('bodegas')->restrictOnDelete();
            $table->foreign('bodega_retorno_id')->references('id')->on('bodegas')->nullOnDelete();
            $table->foreign('asignado_por_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('devuelto_por_user_id')->references('id')->on('users')->nullOnDelete();
            $table->unique(['inventario_existencia_id', 'asignado_en'], 'periodo_exist_inicio_uq');
            $table->index(['asignacion_inventario_id', 'devuelto_en'], 'periodo_asig_activo_idx');
        });

        // Los datos existentes solo permiten conocer cantidad y ubicación. No se inventa consumo histórico.
        DB::table('inventarios')->orderBy('id')->each(function ($inventario) {
            $producto = DB::table('productos')->where('codigo', $inventario->producto_codigo)->first();
            $tipo = $inventario->stock_tipo ?? 'nuevo';
            $vida = $tipo === 'nuevo' && $producto?->vida_util_meses !== null
                ? (int) $producto->vida_util_meses * 30 * 86400
                : (($inventario->vida_util_restante_meses ?? null) !== null
                    ? max(0, (int) $inventario->vida_util_restante_meses) * 30 * 86400
                    : null);

            for ($unidad = 0; $unidad < (int) $inventario->cantidad; $unidad++) {
                DB::table('inventario_existencias')->insert([
                    'producto_codigo' => $inventario->producto_codigo,
                    'bodega_id' => $inventario->bodega_id,
                    'condicion' => in_array($tipo, ['nuevo', 'usado', 'danado', 'perdido', 'baja'], true) ? $tipo : 'danado',
                    'vida_util_inicial_segundos' => $producto?->vida_util_meses !== null ? (int) $producto->vida_util_meses * 30 * 86400 : null,
                    'vida_util_restante_segundos' => $vida,
                    'disponible_desde' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asignacion_periodos');
        Schema::dropIfExists('inventario_existencias');
    }
};

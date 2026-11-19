<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entregas_inventario', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('modalidad', 15);
            $table->string('tercero', 180)->nullable();
            $table->dateTime('salida_en')->index();
            $table->dateTime('recibida_en')->nullable();
            $table->dateTime('confirmada_en')->nullable();
            $table->foreignId('confirmada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('medio_confirmacion', 100)->nullable();
            $table->timestamps();
        });

        Schema::table('asignacion_inventario_archivos', function (Blueprint $table) {
            $table->foreignUuid('grupo_entrega')->nullable()->constrained('entregas_inventario',
                indexName: 'archivos_grupo_entrega_fk')->restrictOnDelete();
        });

        Schema::table('asignaciones_inventarios', function (Blueprint $table) {
            $table->enum('estado', ['Activa', 'Devuelta', 'Dañada', 'Cerrada'])->default('Activa')->change();
            $table->dateTime('fecha_vencimiento')->nullable()->change();
            $table->unsignedBigInteger('vida_util_disponible_segundos')->nullable();
            $table->index(['colaborador_codigo', 'producto_codigo', 'estado'], 'asig_custodia_codigo_idx');
        });

        Schema::table('inventarios', function (Blueprint $table) {
            $table->unsignedBigInteger('vida_util_disponible_segundos')->nullable();
        });

        Schema::table('asignacion_periodos', function (Blueprint $table) {
            $table->dateTime('inicio_uso_en')->nullable();
        });

        Schema::create('incidencias_inventario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asignacion_id')->constrained('asignaciones_inventarios')->restrictOnDelete();
            $table->foreignId('reemplazo_id')->nullable()->constrained('asignaciones_inventarios')->nullOnDelete();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('cantidad');
            $table->string('motivo', 20);
            $table->string('resultado_stock', 20);
            $table->dateTime('ocurrio_en')->nullable();
            $table->text('observaciones')->nullable();
            $table->string('estado_vida', 15);
            $table->unsignedBigInteger('vida_restante_segundos')->nullable();
            $table->string('archivo')->nullable();
            $table->string('archivo_nombre')->nullable();
            $table->foreignId('archivo_subido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archivo_subido_en')->nullable();
            $table->timestamps();
        });

        Schema::table('alertas_reemplazos_rrhh', function (Blueprint $table) {
            $table->foreignId('incidencia_inventario_id')->nullable()->unique('alertas_incidencia_uq')
                ->constrained('incidencias_inventario', indexName: 'alertas_incidencia_fk')->restrictOnDelete();
        });

        Schema::create('alerta_rrhh_actuaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alerta_id')->constrained('alertas_reemplazos_rrhh')->restrictOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('estado', 30);
            $table->text('nota');
            $table->timestamps();
        });

        Schema::create('entrega_avisos', function (Blueprint $table) {
            $table->foreignUuid('entrega_id')->constrained('entregas_inventario')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('enviado_en');
            $table->primary(['entrega_id', 'user_id']);
        });
    }

    public function down(): void
    {
        throw new LogicException('Las entregas, incidencias y actuaciones deben conservarse al revertir el código.');
    }
};

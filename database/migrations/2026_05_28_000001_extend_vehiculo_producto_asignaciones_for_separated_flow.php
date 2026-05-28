<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehiculo_producto_asignaciones', function (Blueprint $table) {
            $table->foreignId('asignacion_vehiculo_id')->nullable()->change();
            $table->string('motivo', 150)->nullable()->after('fecha');
            $table->enum('estado', ['activo', 'regresado', 'consumido', 'danado', 'baja'])->default('activo')->after('observaciones');
            $table->foreignId('cerrado_por_user_id')->nullable()->after('asignado_por_user_id')->constrained('users')->nullOnDelete();
            $table->dateTime('fecha_cierre')->nullable()->after('fecha');
            $table->enum('accion_cierre', ['regresar', 'consumido', 'danado', 'baja'])->nullable()->after('fecha_cierre');
            $table->boolean('mal_uso_colaborador')->default(false)->after('accion_cierre');
            $table->string('colaborador_responsable_codigo', 20)->nullable()->after('mal_uso_colaborador');
            $table->boolean('descuento_generado')->default(false)->after('colaborador_responsable_codigo');

            $table->foreign('colaborador_responsable_codigo', 'vpa_colab_resp_fk')
                ->references('codigo')->on('colaboradores')
                ->cascadeOnUpdate()->nullOnDelete();
            $table->index(['vehiculo_vin', 'estado'], 'vpa_vehiculo_estado_idx');
        });
    }

    public function down(): void
    {
        Schema::table('vehiculo_producto_asignaciones', function (Blueprint $table) {
            $table->dropIndex('vpa_vehiculo_estado_idx');
            $table->dropForeign('vpa_colab_resp_fk');
            $table->dropConstrainedForeignId('cerrado_por_user_id');
            $table->dropColumn([
                'motivo',
                'estado',
                'fecha_cierre',
                'accion_cierre',
                'mal_uso_colaborador',
                'colaborador_responsable_codigo',
                'descuento_generado',
            ]);
            $table->foreignId('asignacion_vehiculo_id')->nullable(false)->change();
        });
    }
};

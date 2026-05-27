<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asignaciones_vehiculos', function (Blueprint $table) {
            $table->foreignId('asignado_por_user_id')->nullable()->after('colaborador_codigo')->constrained('users')->nullOnDelete();
            $table->foreignId('desasignado_por_user_id')->nullable()->after('asignado_por_user_id')->constrained('users')->nullOnDelete();
            $table->string('estado_inicial_vehiculo', 60)->nullable()->after('fecha_fin');
            $table->string('estado_final_vehiculo', 60)->nullable()->after('estado_inicial_vehiculo');
            $table->text('observaciones_asignacion')->nullable()->after('estado_final_vehiculo');
            $table->text('observaciones_desasignacion')->nullable()->after('observaciones_asignacion');
            $table->boolean('activa')->default(true)->after('observaciones_desasignacion');
            $table->index(['vehiculo_vin', 'activa']);
            $table->index(['colaborador_codigo', 'activa']);
        });
    }

    public function down(): void
    {
        Schema::table('asignaciones_vehiculos', function (Blueprint $table) {
            $table->dropIndex(['vehiculo_vin', 'activa']);
            $table->dropIndex(['colaborador_codigo', 'activa']);
            $table->dropConstrainedForeignId('asignado_por_user_id');
            $table->dropConstrainedForeignId('desasignado_por_user_id');
            $table->dropColumn([
                'estado_inicial_vehiculo',
                'estado_final_vehiculo',
                'observaciones_asignacion',
                'observaciones_desasignacion',
                'activa',
            ]);
        });
    }
};

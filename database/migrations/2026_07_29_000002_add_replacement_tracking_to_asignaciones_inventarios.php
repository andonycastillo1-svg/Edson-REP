<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asignaciones_inventarios', function (Blueprint $table) {
            $table->string('tipo_entrega', 30)->default('inicial')->after('stock_tipo')->index();
            $table->foreignId('asignacion_anterior_id')->nullable()->after('tipo_entrega');
            $table->string('solicitado_por', 150)->nullable()->after('asignacion_anterior_id');
            $table->string('motivo_reposicion', 40)->nullable()->after('solicitado_por');
            $table->text('justificacion_reposicion')->nullable()->after('motivo_reposicion');
            $table->unsignedBigInteger('vida_restante_anterior_segundos')->nullable()->after('justificacion_reposicion');

            $table->foreign('asignacion_anterior_id', 'asig_inv_anterior_fk')
                ->references('id')->on('asignaciones_inventarios')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('asignaciones_inventarios', function (Blueprint $table) {
            $table->dropForeign('asig_inv_anterior_fk');
            $table->dropIndex(['tipo_entrega']);
            $table->dropColumn([
                'tipo_entrega',
                'asignacion_anterior_id',
                'solicitado_por',
                'motivo_reposicion',
                'justificacion_reposicion',
                'vida_restante_anterior_segundos',
            ]);
        });
    }
};

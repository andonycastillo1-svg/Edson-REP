<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alertas_reemplazos_rrhh', function (Blueprint $table) {
            $table->string('vehiculo_vin', 50)->nullable()->after('producto_codigo');
            $table->foreignId('vehiculo_producto_asignacion_id')->nullable()->after('vehiculo_vin')->constrained('vehiculo_producto_asignaciones')->nullOnDelete();
            $table->unsignedInteger('cantidad')->nullable()->after('vehiculo_producto_asignacion_id');
            $table->decimal('costo_estimado', 12, 2)->nullable()->after('cantidad');
            $table->foreignId('registrado_por_user_id')->nullable()->after('costo_estimado')->constrained('users')->nullOnDelete();

            $table->foreign('vehiculo_vin', 'alertas_vehiculo_vin_fk')
                ->references('vin')->on('vehiculos')
                ->cascadeOnUpdate()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('alertas_reemplazos_rrhh', function (Blueprint $table) {
            $table->dropForeign('alertas_vehiculo_vin_fk');
            $table->dropConstrainedForeignId('vehiculo_producto_asignacion_id');
            $table->dropConstrainedForeignId('registrado_por_user_id');
            $table->dropColumn(['vehiculo_vin', 'cantidad', 'costo_estimado']);
        });
    }
};

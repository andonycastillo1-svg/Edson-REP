<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventario_existencias', function (Blueprint $table) {
            $table->foreignId('vehiculo_producto_asignacion_id')->nullable()
                ->constrained('vehiculo_producto_asignaciones', indexName: 'exist_vehiculo_producto_fk')->nullOnDelete();
        });

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::table('asignacion_movimientos', function (Blueprint $table) {
                $table->enum('tipo', ['Asignacion', 'Devolucion', 'Reemplazo'])->change();
            });
        }

        Schema::create('solicitudes_inventario', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('operacion', 30);
            $table->uuid('clave');
            $table->timestamp('created_at');
            $table->primary(['user_id', 'operacion', 'clave'], 'solicitudes_inventario_pk');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_inventario');
        Schema::table('inventario_existencias', function (Blueprint $table) {
            $table->dropForeign('exist_vehiculo_producto_fk');
            $table->dropColumn('vehiculo_producto_asignacion_id');
        });
    }
};

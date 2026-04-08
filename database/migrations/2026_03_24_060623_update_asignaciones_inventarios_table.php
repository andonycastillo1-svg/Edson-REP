<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asignaciones_inventarios', function (Blueprint $table) {

            $table->decimal('costo_unitario', 10, 2)->nullable()->after('cantidad_asignada');

            $table->string('aprobado_por')->nullable()->after('costo_unitario');

            $table->enum('medio_solicitud', ['WhatsApp', 'Correo'])
                  ->nullable()
                  ->after('aprobado_por');

            $table->string('imagen')->nullable()->after('medio_solicitud');

            $table->text('observaciones')->nullable()->after('imagen');

            $table->date('fecha_vencimiento')->nullable()->after('observaciones');

            $table->enum('estado', ['Activa', 'Devuelta', 'Dañada'])
                  ->default('Activa')
                  ->after('fecha_vencimiento');

        });
    }

    public function down(): void
    {
        Schema::table('asignaciones_inventarios', function (Blueprint $table) {
            $table->dropColumn([
                'costo_unitario',
                'aprobado_por',
                'medio_solicitud',
                'imagen',
                'observaciones',
                'fecha_vencimiento',
                'estado'
            ]);
        });
    }
};
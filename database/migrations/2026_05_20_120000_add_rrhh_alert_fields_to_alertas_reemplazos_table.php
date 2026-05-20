<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('alertas_reemplazos_rrhh', function (Blueprint $table) {
            $table->string('producto_nombre')->nullable()->after('producto_codigo');
            $table->unsignedSmallInteger('meses_usados')->default(0)->after('meses_restantes');
            $table->decimal('costo_producto', 12, 2)->default(0)->after('meses_usados');
            $table->decimal('descuento_proporcional_sugerido', 12, 2)->default(0)->after('costo_producto');
            $table->string('motivo_alerta', 40)->default('reemplazo_danio')->after('descuento_proporcional_sugerido');
        });
    }

    public function down(): void
    {
        Schema::table('alertas_reemplazos_rrhh', function (Blueprint $table) {
            $table->dropColumn([
                'producto_nombre',
                'meses_usados',
                'costo_producto',
                'descuento_proporcional_sugerido',
                'motivo_alerta',
            ]);
        });
    }
};

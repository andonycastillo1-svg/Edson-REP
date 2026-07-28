<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStockTipoToInventariosTable extends Migration
{
    public function up(): void
    {
        Schema::table('inventarios', function (Blueprint $table) {
            if (!Schema::hasColumn('inventarios', 'stock_tipo')) {
                $table->string('stock_tipo', 30)
                    ->default('nuevo')
                    ->after('cantidad');
            }

            if (!Schema::hasColumn('inventarios', 'vida_util_restante_meses')) {
                $table->integer('vida_util_restante_meses')
                    ->nullable()
                    ->after('stock_tipo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventarios', function (Blueprint $table) {
            if (Schema::hasColumn('inventarios', 'vida_util_restante_meses')) {
                $table->dropColumn('vida_util_restante_meses');
            }

            if (Schema::hasColumn('inventarios', 'stock_tipo')) {
                $table->dropColumn('stock_tipo');
            }
        });
    }
}
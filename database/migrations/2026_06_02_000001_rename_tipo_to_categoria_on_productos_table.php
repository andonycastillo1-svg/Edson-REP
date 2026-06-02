<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('productos')) {
            return;
        }

        if (Schema::hasColumn('productos', 'tipo') && !Schema::hasColumn('productos', 'categoria')) {
            Schema::table('productos', function (Blueprint $table) {
                $table->renameColumn('tipo', 'categoria');
            });

            return;
        }

        if (!Schema::hasColumn('productos', 'categoria')) {
            $afterColumn = Schema::hasColumn('productos', 'vida_util_meses')
                ? 'vida_util_meses'
                : (Schema::hasColumn('productos', 'unidad_medida') ? 'unidad_medida' : null);

            Schema::table('productos', function (Blueprint $table) use ($afterColumn) {
                $column = $table->string('categoria', 50)->nullable();

                if ($afterColumn) {
                    $column->after($afterColumn);
                }
            });
        }

        if (Schema::hasColumn('productos', 'tipo')) {
            DB::table('productos')
                ->where(function ($query) {
                    $query->whereNull('categoria')
                        ->orWhere('categoria', '');
                })
                ->whereNotNull('tipo')
                ->where('tipo', '<>', '')
                ->update(['categoria' => DB::raw('tipo')]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('productos')) {
            return;
        }

        if (Schema::hasColumn('productos', 'categoria') && !Schema::hasColumn('productos', 'tipo')) {
            Schema::table('productos', function (Blueprint $table) {
                $table->renameColumn('categoria', 'tipo');
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('compras', 'mes')) {
            return;
        }

        $connection = Schema::getConnection()->getDriverName();

        if ($connection === 'sqlite') {
            DB::statement('ALTER TABLE compras RENAME COLUMN mes TO fecha_compra');
            return;
        }

        if ($connection === 'mysql') {
            DB::statement("ALTER TABLE compras CHANGE mes fecha_compra DATE NOT NULL");
            return;
        }

        DB::statement('ALTER TABLE compras RENAME COLUMN mes TO fecha_compra');
    }

    public function down(): void
    {
        if (!Schema::hasColumn('compras', 'fecha_compra')) {
            return;
        }

        $connection = Schema::getConnection()->getDriverName();

        if ($connection === 'sqlite') {
            DB::statement('ALTER TABLE compras RENAME COLUMN fecha_compra TO mes');
            return;
        }

        if ($connection === 'mysql') {
            DB::statement("ALTER TABLE compras CHANGE fecha_compra mes VARCHAR(255) NOT NULL");
            return;
        }

        DB::statement('ALTER TABLE compras RENAME COLUMN fecha_compra TO mes');
    }
};
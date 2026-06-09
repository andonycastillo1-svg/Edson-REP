<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')
            ->where('id', 3)
            ->where(function ($query) {
                $query->whereRaw('LOWER(TRIM(nombre)) = ?', ['coordinador'])
                    ->orWhereRaw('LOWER(TRIM(nombre)) = ?', ['supervisor']);
            })
            ->update(['nombre' => 'Supervisor']);
    }

    public function down(): void
    {
        DB::table('roles')
            ->where('id', 3)
            ->whereRaw('LOWER(TRIM(nombre)) = ?', ['supervisor'])
            ->update(['nombre' => 'Coordinador']);
    }
};

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('roles')->insertOrIgnore([
            ['id' => 1, 'nombre' => 'Administrador'],
            ['id' => 2, 'nombre' => 'Encargado'],
            ['id' => 3, 'nombre' => 'Coordinador'],
            ['id' => 4, 'nombre' => 'Consultas'],
        ]);
    }
}

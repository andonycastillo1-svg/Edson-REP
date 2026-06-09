<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('roles')->upsert([
            ['id' => 1, 'nombre' => 'Administrador'],
            ['id' => 2, 'nombre' => 'Encargado'],
            ['id' => 3, 'nombre' => 'Supervisor'],
            ['id' => 4, 'nombre' => 'Consultas'],
        ], ['id'], ['nombre']);
    }
}

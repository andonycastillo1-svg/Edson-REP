<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'etinti@gruponetsolutions.com'],
            [
                'name' => 'Edson',
                'password' => Hash::make('Admin1234'),
                'role_id' => 1,
            ]
        );
    }
}

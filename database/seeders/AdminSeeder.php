<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $credenciales = Validator::make([
            'email' => config('auth.admin_bootstrap.email'),
            'password' => config('auth.admin_bootstrap.password'),
        ], [
            'email' => ['required', 'email'],
            'password' => ['required', Password::min(16)->mixedCase()->letters()->numbers()->symbols()],
        ])->validate();

        if (User::where('email', $credenciales['email'])->exists()) {
            throw new \RuntimeException('La cuenta ya existe. Sus credenciales no se modificaron.');
        }

        User::create([
            'email' => $credenciales['email'],
            'name' => 'Administrador',
            'password' => Hash::make($credenciales['password']),
            'role_id' => 1,
        ]);
    }
}

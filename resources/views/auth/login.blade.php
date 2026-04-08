@extends('layouts.guest')

@section('title', 'Login - Inventario')

@section('content')
<div class="w-full max-w-md bg-white rounded-2xl shadow-2xl overflow-hidden">

    <!-- Header con logo -->
    <div class="p-8 flex flex-col items-center">
        <img src="{{ asset('img/logo.png') }}" class="w-[180px] mb-4" alt="Logo">
        <h2 class="text-xl font-semibold text-gray-800">Iniciar sesión</h2>
        <p class="text-sm text-gray-500 mt-1">Accede al sistema de inventario</p>
    </div>

    <!-- Form -->
    <div class="px-8 pb-8">
        @if (session('status'))
            <div class="mb-4 rounded-lg bg-green-50 text-green-700 px-4 py-3 text-sm">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-4 rounded-lg bg-red-50 text-red-700 px-4 py-3 text-sm">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="space-y-4">
            @csrf

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                       class="mt-1 w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                <input id="password" name="password" type="password" required
                       class="mt-1 w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
            </div>

            <div class="flex items-center justify-between">
                <label class="inline-flex items-center">
                    <input type="checkbox" name="remember"
                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    <span class="ml-2 text-sm text-gray-600">Recordar</span>
                </label>

                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="text-sm text-blue-600 hover:underline">
                        Olvidó su contraseña?
                    </a>
                @endif
            </div>

            <button type="submit"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 rounded-lg transition">
                Ingresar
            </button>
        </form>
    </div>
</div>
@endsection

@extends('layouts.admin')

@section('title', 'Admin - Inicio')

@section('content')
    <div class="w-full max-w-lg bg-white/95 backdrop-blur rounded-2xl shadow-2xl p-8">

        <div class="text-center mb-8">
            <h1 class="text-2xl font-semibold text-gray-800">
                Bienvenido, {{ auth()->user()->name }}
            </h1>
            <p class="text-sm text-gray-500 mt-1">
                Selecciona una opción para continuar
            </p>
        </div>

        <!-- Menú vertical centrado -->
        <div class="space-y-3">
            <a href="{{ route('admin.usuarios.index') }}"
               class="w-full flex items-center justify-between px-5 py-3 rounded-xl border border-gray-200 hover:border-blue-300 hover:bg-blue-50 transition">
                <span class="font-medium text-gray-700">Usuarios</span>
                <span class="text-gray-400">›</span>
            </a>

            <a href="{{ route('admin.bodegas.index') }}"
               class="w-full flex items-center justify-between px-5 py-3 rounded-xl border border-gray-200 hover:border-blue-300 hover:bg-blue-50 transition">
                <span class="font-medium text-gray-700">Bodegas</span>
                <span class="text-gray-400">›</span>
            </a>

            <a href="{{ route('admin.compras.index') }}"
               class="w-full flex items-center justify-between px-5 py-3 rounded-xl border border-gray-200 hover:border-blue-300 hover:bg-blue-50 transition">
                <span class="font-medium text-gray-700">Compras</span>
                <span class="text-gray-400">›</span>
            </a>

            <a href="{{ route('admin.colaboradores.index') }}"
               class="w-full flex items-center justify-between px-5 py-3 rounded-xl border border-gray-200 hover:border-blue-300 hover:bg-blue-50 transition">
                <span class="font-medium text-gray-700">Colaboradores</span>
                <span class="text-gray-400">›</span>
            </a>

            <a href="{{ route('admin.asignaciones.create') }}"
               class="w-full flex items-center justify-between px-5 py-3 rounded-xl border border-gray-200 hover:border-blue-300 hover:bg-blue-50 transition">
                <span class="font-medium text-gray-700">Asignaciones</span>
                <span class="text-gray-400">›</span>
            </a>

            <a href="{{ route('admin.vehiculos.index') }}"
               class="w-full flex items-center justify-between px-5 py-3 rounded-xl border border-gray-200 hover:border-blue-300 hover:bg-blue-50 transition">
                <span class="font-medium text-gray-700">Vehículos</span>
                <span class="text-gray-400">›</span>
            </a>
        </div>

        <div class="mt-8 pt-6 border-t text-center">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="text-sm text-red-600 hover:underline">
                    Cerrar sesión
                </button>
            </form>
        </div>



    </div>
@endsection

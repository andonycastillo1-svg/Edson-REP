@extends('layouts.admin')

@section('title', 'RRHH - Inicio')

@section('content')
<div class="w-full max-w-3xl rounded-3xl border border-slate-200 bg-white shadow-2xl overflow-hidden">
    <div class="bg-emerald-700 px-6 py-5 text-white">
        <h1 class="text-2xl font-bold">Panel RRHH</h1>
        <p class="text-sm text-emerald-100">Gestión de personal y cuentas del sistema.</p>
    </div>

    <div class="p-6 grid gap-3 bg-slate-50">
        <a href="{{ route('rrhh.colaboradores.index') }}" class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-slate-800 hover:border-emerald-300 hover:bg-emerald-50">
            <div class="font-semibold">🧑‍💼 Colaboradores</div>
            <div class="text-xs text-slate-500">Altas, bajas y gestión de personal</div>
        </a>

        <a href="{{ route('rrhh.usuarios.index') }}" class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-slate-800 hover:border-emerald-300 hover:bg-emerald-50">
            <div class="font-semibold">👥 Usuarios</div>
            <div class="text-xs text-slate-500">Cuentas y permisos</div>
        </a>

        <div class="pt-3 text-center border-t border-slate-200">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="text-sm font-medium text-rose-600 hover:underline">Cerrar sesión</button>
            </form>
        </div>
    </div>
</div>
@endsection

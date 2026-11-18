@extends('layouts.admin')

@section('title', 'RRHH - Inicio')

@section('content')
<div class="mx-auto w-full max-w-6xl">

    <div class="ui-page-header">
        <div class="ui-page-header-body">
            <div class="flex items-center gap-4">
                <div class="flex h-16 w-16 items-center justify-center rounded-2xl border border-sky-100 bg-white shadow-sm">
                    <img src="{{ asset('img/logo1.png') }}" alt="Logo" class="h-11 w-11 object-contain">
                </div>

                <div>
                    <span class="ui-kicker">Panel principal</span>
                    <h1 class="ui-page-title">Panel RRHH</h1>
                    <p class="ui-page-subtitle">
                        Bienvenido, <span class="font-bold text-slate-700">{{ auth()->user()->name }}</span>. Gestión de personal, cuentas y alertas por reemplazo antes de vida útil.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <a href="{{ route('rrhh.colaboradores.index') }}" class="dashboard-card group">
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-50 text-xl text-emerald-700">🧑‍💼</span>
                    <div>
                        <h2 class="text-base font-extrabold text-slate-950">Colaboradores</h2>
                        <p class="mt-1 text-sm text-slate-500">Altas, bajas y gestión de personal</p>
                    </div>
                </div>
                <span class="text-2xl text-slate-300 transition group-hover:text-emerald-600">›</span>
            </div>
        </a>

        <a href="{{ route('rrhh.usuarios.index') }}" class="dashboard-card group">
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-50 text-xl text-blue-700">👥</span>
                    <div>
                        <h2 class="text-base font-extrabold text-slate-950">Usuarios</h2>
                        <p class="mt-1 text-sm text-slate-500">Cuentas y permisos</p>
                    </div>
                </div>
                <span class="text-2xl text-slate-300 transition group-hover:text-blue-600">›</span>
            </div>
        </a>

        <a href="{{ route('rrhh.alertas.index') }}" class="dashboard-card group">
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-50 text-xl text-amber-700">⚠️</span>
                    <div>
                        <h2 class="text-base font-extrabold text-slate-950">Alertas</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ $totalAlertas ?? 0 }} alerta(s) pendiente(s)</p>
                    </div>
                </div>
                <span class="text-2xl text-slate-300 transition group-hover:text-amber-600">›</span>
            </div>
        </a>
    </div>

    <div class="ui-panel mt-5 px-5 py-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm font-semibold text-slate-500">Sesión activa como RRHH.</p>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="ui-btn-danger text-xs">Cerrar sesión</button>
            </form>
        </div>
    </div>

</div>
@endsection

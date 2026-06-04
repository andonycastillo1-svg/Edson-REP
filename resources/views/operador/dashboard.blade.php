@extends('layouts.operador')

@section('title', 'Operador - Inicio')

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
                    <h1 class="ui-page-title">Panel operador</h1>
                    <p class="ui-page-subtitle">
                        Bienvenido, <span class="font-bold text-slate-700">{{ auth()->user()->name }}</span>. Administra inventario, compras, asignaciones y traslados entre bodegas.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <a href="{{ route('operador.bodegas.index') }}" class="dashboard-card group">
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-50 text-blue-700">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5">
                            <path d="M3 10.5L12 4l9 6.5V20a1 1 0 01-1 1h-4.5v-6h-7v6H4a1 1 0 01-1-1v-9.5z"/>
                        </svg>
                    </span>
                    <div>
                        <h2 class="text-base font-extrabold text-slate-950">Bodegas</h2>
                        <p class="mt-1 text-sm text-slate-500">Inventario por bodega</p>
                    </div>
                </div>
                <span class="text-2xl text-slate-300 transition group-hover:text-blue-600">›</span>
            </div>
        </a>

        <a href="{{ route('operador.compras.index') }}" class="dashboard-card group">
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-50 text-xl text-indigo-700">🧾</span>
                    <div>
                        <h2 class="text-base font-extrabold text-slate-950">Compras</h2>
                        <p class="mt-1 text-sm text-slate-500">Registro de compras</p>
                    </div>
                </div>
                <span class="text-2xl text-slate-300 transition group-hover:text-blue-600">›</span>
            </div>
        </a>

        <a href="{{ route('operador.asignaciones.create') }}" class="dashboard-card group">
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-50 text-xl text-amber-700">📦</span>
                    <div>
                        <h2 class="text-base font-extrabold text-slate-950">Asignaciones</h2>
                        <p class="mt-1 text-sm text-slate-500">Entrega de equipo</p>
                    </div>
                </div>
                <span class="text-2xl text-slate-300 transition group-hover:text-blue-600">›</span>
            </div>
        </a>

        <a href="{{ route('operador.operaciones.traslados.index') }}" class="dashboard-card group">
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-50 text-xl text-emerald-700">🔁</span>
                    <div>
                        <h2 class="text-base font-extrabold text-slate-950">Traslados</h2>
                        <p class="mt-1 text-sm text-slate-500">Solicitudes entre bodegas</p>
                    </div>
                </div>
                <span class="text-2xl text-slate-300 transition group-hover:text-blue-600">›</span>
            </div>
        </a>
    </div>

    <div class="ui-panel mt-5 px-5 py-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm font-semibold text-slate-500">Sesión activa como operador.</p>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="ui-btn-danger text-xs">Cerrar sesión</button>
            </form>
        </div>
    </div>

</div>
@endsection

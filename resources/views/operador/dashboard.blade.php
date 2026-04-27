@extends('layouts.operador')

@section('title', 'Operador - Inicio')

@section('content')
<div class="ui-panel w-full max-w-2xl overflow-hidden">
    <div class="ui-dashboard-hero">
        <div class="flex flex-col items-center gap-4 text-center sm:flex-row sm:text-left">
            <div class="ui-logo-card">
                <x-logo-image variant="logo1" class="ui-dashboard-logo" />
            </div>
            <div class="min-w-0">
                <p class="text-xs font-bold uppercase tracking-[0.22em] text-sky-600">Panel de operador</p>
                <h1 class="mt-1 text-2xl font-extrabold tracking-tight text-slate-950 sm:text-3xl">Bienvenido, {{ auth()->user()->name }}</h1>
                <p class="mt-2 max-w-xl text-sm font-medium text-slate-600">Gestiona compras, asignaciones y movimientos de bodega con accesos rápidos.</p>
            </div>
        </div>
    </div>

    <div class="grid gap-3 bg-sky-50/70 p-5">
        <a href="{{ route('operador.bodegas.index') }}" class="ui-card group p-4 hover:border-blue-300 hover:bg-blue-50">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-blue-100 text-blue-700">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5"><path d="M3 10.5L12 4l9 6.5V20a1 1 0 01-1 1h-4.5v-6h-7v6H4a1 1 0 01-1-1v-9.5z"/></svg>
                    </span>
                    <div>
                        <div class="font-semibold text-slate-800">Bodegas</div>
                        <div class="text-xs text-slate-500">Inventario por bodega</div>
                    </div>
                </div>
                <span class="text-slate-400 group-hover:text-blue-600">›</span>
            </div>
        </a>

        <a href="{{ route('operador.compras.index') }}" class="ui-card group p-4 hover:border-blue-300 hover:bg-blue-50">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-blue-100 text-blue-700">🧾</span>
                    <div>
                        <div class="font-semibold text-slate-800">Compras</div>
                        <div class="text-xs text-slate-500">Registro de compras</div>
                    </div>
                </div>
                <span class="text-slate-400 group-hover:text-blue-600">›</span>
            </div>
        </a>

        <a href="{{ route('operador.asignaciones.create') }}" class="ui-card group p-4 hover:border-blue-300 hover:bg-blue-50">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-blue-100 text-blue-700">📦</span>
                    <div>
                        <div class="font-semibold text-slate-800">Asignaciones</div>
                        <div class="text-xs text-slate-500">Entrega de equipo</div>
                    </div>
                </div>
                <span class="text-slate-400 group-hover:text-blue-600">›</span>
            </div>
        </a>

        <a href="{{ route('operador.operaciones.traslados.index') }}" class="ui-card group p-4 hover:border-blue-300 hover:bg-blue-50">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-blue-100 text-blue-700">🔁</span>
                    <div>
                        <div class="font-semibold text-slate-800">Traslados</div>
                        <div class="text-xs text-slate-500">Solicitudes entre bodegas</div>
                    </div>
                </div>
                <span class="text-slate-400 group-hover:text-blue-600">›</span>
            </div>
        </a>

        <div class="border-t border-slate-200 pt-3 text-center">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="ui-btn-danger">Cerrar sesión</button>
            </form>
        </div>
    </div>
</div>
@endsection

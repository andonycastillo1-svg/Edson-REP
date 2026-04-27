@extends('layouts.operador')

@section('title', 'Operador - Inicio')

@section('content')
<div class="ui-panel w-full max-w-4xl overflow-hidden">
    <div class="ui-hero">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-center">
            <x-logo-image variant="logo1" class="h-20 w-20 rounded-2xl border border-white/40 bg-white p-2 object-contain shadow-lg" />
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-blue-100">Panel de operador</p>
                <h1 class="mt-1 text-3xl font-extrabold tracking-tight text-white">Bienvenido, {{ auth()->user()->name }}</h1>
                <p class="mt-2 text-sm text-blue-50">Gestiona compras, asignaciones y movimientos de bodega con accesos rápidos.</p>
            </div>
        </div>
    </div>

    <div class="grid gap-4 bg-slate-50/90 p-6 md:grid-cols-2">
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

        <div class="border-t border-slate-200 pt-3 text-center md:col-span-2">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="ui-btn-danger">Cerrar sesión</button>
            </form>
        </div>
    </div>
</div>
@endsection

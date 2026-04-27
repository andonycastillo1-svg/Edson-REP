@extends('layouts.operador')

@section('title', 'Operador - Inicio')

@section('content')
<div class="ui-panel w-full max-w-3xl overflow-hidden">
    <div class="bg-gradient-to-r from-blue-700 to-indigo-700 px-6 py-6 text-white">
        <div class="flex items-center gap-4">
            <x-logo-image variant="logo1" class="h-14 w-14 rounded-xl border border-white/30 bg-white p-1 object-contain" />
            <div>
                <h1 class="text-3xl font-bold">Bienvenido, {{ auth()->user()->name }}</h1>
                <p class="text-sm text-blue-100">Panel de operador</p>
            </div>
        </div>
    </div>

    <div class="grid gap-3 bg-slate-50 p-6">
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

        <div class="pt-3 border-t border-slate-200 text-center">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="text-sm font-medium text-rose-600 hover:underline">Cerrar sesión</button>
            </form>
        </div>
    </div>
</div>
@endsection

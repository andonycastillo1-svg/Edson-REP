@extends('layouts.admin')

@section('title', 'Coordinador - Inicio')

@section('content')
<div class="ui-panel w-full max-w-2xl overflow-hidden">
    <div class="ui-hero">
        <div class="flex flex-col items-center gap-4 text-center">
            <div class="dashboard-logo-card">
                <x-logo-image class="dashboard-logo" />
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-blue-100">Panel de coordinador</p>
                <h1 class="mt-1 text-2xl font-extrabold text-white md:text-3xl">Bienvenido, {{ auth()->user()->name }}</h1>
                <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-blue-50">Consulta rápida y control visual de tu bodega.</p>
            </div>
        </div>
    </div>

    <div class="dashboard-menu-list">
        <a href="{{ route('coordinador.bodegas.index') }}"
           class="ui-card group p-5 hover:border-blue-300 hover:bg-blue-50/70">
            <div class="flex items-center justify-between">
                <span class="flex items-center gap-3">
                    <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-100 text-2xl text-blue-700">🏬</span>
                    <span>
                        <div class="font-bold text-slate-900">Mi bodega</div>
                        <div class="text-sm text-slate-600">Consulta rápida de inventario</div>
                    </span>
                </span>
                <span class="text-2xl text-slate-300 group-hover:text-blue-600">›</span>
            </div>
        </a>
    </div>

    <div class="border-t border-slate-200 bg-slate-50 px-6 py-4 text-center">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="ui-btn-danger">Cerrar sesión</button>
        </form>
    </div>
</div>
@endsection

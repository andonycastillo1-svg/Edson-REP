@extends('layouts.admin')

@section('title', 'Encargado - Inicio')

@section('content')
<div class="ui-panel w-full max-w-xl overflow-hidden">
    <div class="section-hero compact-hero text-center">
        <div class="mx-auto mb-4 flex w-full justify-center">
            <div class="dashboard-logo-card">
                <x-logo-image />
            </div>
        </div>
        <h1 class="text-2xl font-extrabold text-slate-950">Panel Encargado</h1>
        <p class="mx-auto mt-1 max-w-md text-sm font-medium text-slate-600">Accesos rápidos para control de bodega y traslados.</p>
    </div>

    <div class="dashboard-stack">
        <a href="{{ route('admin.bodegas.index') }}" class="dashboard-card">
            <div class="font-bold text-slate-900">🏬 Bodegas</div>
            <div class="text-xs font-medium text-slate-500">Inventario por sede</div>
        </a>

        <a href="{{ route('admin.operaciones.traslados.index') }}" class="dashboard-card">
            <div class="font-bold text-slate-900">🔁 Traslados</div>
            <div class="text-xs font-medium text-slate-500">Solicitudes y aprobaciones</div>
        </a>

        <div class="pt-3 text-center border-t border-slate-200">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="text-sm font-semibold text-rose-700 hover:underline">Cerrar sesión</button>
            </form>
        </div>
    </div>
</div>
@endsection

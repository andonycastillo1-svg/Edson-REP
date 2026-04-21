@extends('layouts.admin')

@section('title', 'Encargado - Inicio')

@section('content')
<div class="w-full max-w-3xl rounded-3xl border border-slate-200 bg-white shadow-2xl overflow-hidden">
    <div class="bg-indigo-700 px-6 py-5 text-white">
        <h1 class="text-2xl font-bold">Panel Encargado</h1>
        <p class="text-sm text-indigo-100">Accesos rápidos para control de bodega y traslados.</p>
    </div>

    <div class="p-6 grid gap-3 bg-slate-50">
        <a href="{{ route('admin.bodegas.index') }}" class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-slate-800 hover:border-indigo-300 hover:bg-indigo-50">
            <div class="font-semibold">🏬 Bodegas</div>
            <div class="text-xs text-slate-500">Inventario por sede</div>
        </a>

        <a href="{{ route('admin.operaciones.traslados.index') }}" class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-slate-800 hover:border-indigo-300 hover:bg-indigo-50">
            <div class="font-semibold">🔁 Traslados</div>
            <div class="text-xs text-slate-500">Solicitudes y aprobaciones</div>
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

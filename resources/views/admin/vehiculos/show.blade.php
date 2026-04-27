@extends('layouts.admin')

@section('title', 'Detalle Vehiculo')

@section('content')
<div class="w-full max-w-3xl bg-white/90 rounded-2xl shadow-2xl p-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Detalle Vehiculo</h1>
        <a href="{{ route('admin.vehiculos.index') }}"
           class="px-4 py-2 rounded-xl bg-slate-200 text-slate-700 font-semibold hover:bg-slate-300">
            Volver
        </a>
    </div>

    <dl class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div class="rounded-xl border border-slate-200 p-4">
            <dt class="text-xs font-semibold uppercase text-slate-500">VIN</dt>
            <dd class="mt-1 text-slate-900">{{ $vehiculo->vin }}</dd>
        </div>
        <div class="rounded-xl border border-slate-200 p-4">
            <dt class="text-xs font-semibold uppercase text-slate-500">Placa</dt>
            <dd class="mt-1 text-slate-900">{{ $vehiculo->placa }}</dd>
        </div>
        <div class="rounded-xl border border-slate-200 p-4">
            <dt class="text-xs font-semibold uppercase text-slate-500">Marca</dt>
            <dd class="mt-1 text-slate-900">{{ $vehiculo->marca ?? '-' }}</dd>
        </div>
        <div class="rounded-xl border border-slate-200 p-4">
            <dt class="text-xs font-semibold uppercase text-slate-500">Modelo</dt>
            <dd class="mt-1 text-slate-900">{{ $vehiculo->modelo ?? '-' }}</dd>
        </div>
        <div class="rounded-xl border border-slate-200 p-4">
            <dt class="text-xs font-semibold uppercase text-slate-500">Estado</dt>
            <dd class="mt-1 text-slate-900">{{ $vehiculo->estado }}</dd>
        </div>
    </dl>

    <div class="mt-6 flex justify-end">
        <a href="{{ route('admin.vehiculos.edit', $vehiculo->vin) }}"
           class="px-5 py-3 rounded-xl bg-blue-600 text-white font-semibold hover:bg-blue-700">
            Editar
        </a>
    </div>
</div>
@endsection

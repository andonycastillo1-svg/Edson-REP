@extends('layouts.admin')

@section('title', 'Bodegas')

@section('content')
@php
    $esOperador = auth()->user()->role_id == 2;
    $routePrefix = match ((int) auth()->user()->role_id) {
        2 => 'operador',
        3 => 'coordinador',
        default => 'admin',
    };
    $bodegaOperadorId = auth()->user()->bodega_id;
@endphp

<div class="ui-panel w-full max-w-7xl overflow-hidden">
    <div class="bg-gradient-to-r from-sky-700 via-blue-700 to-indigo-800 px-6 py-6 text-white md:px-8">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Bodegas · Vista Empresarial</h1>
                <p class="mt-1 text-sm text-sky-100">Inventario por sede con lectura clara y acciones rápidas.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('dashboard') }}" class="ui-btn-secondary border-white/30 bg-white/95">← Volver al menú</a>
                @if(auth()->user()->role_id == 1)
                    <a href="{{ route('admin.bodegas.create') }}" class="ui-btn-success">+ Nueva bodega</a>
                @endif
            </div>
        </div>
        <div class="mt-4 inline-flex rounded-full bg-white/15 px-3 py-1 text-xs font-semibold text-sky-50">Total: <span class="ml-1 text-white">{{ $bodegas->count() }}</span> bodegas</div>
    </div>

    <div class="bg-sky-50/70 p-6 md:p-8">
        <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
            @forelse($bodegas as $bodega)
                @php
                    $esMiBodega = (int) $bodega->id === (int) $bodegaOperadorId;
                    $isPrincipal = ($bodega->tipo === 'Principal');
                @endphp

                <article class="ui-card p-5">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-900">{{ $bodega->nombre ?? ('Bodega #'.$bodega->id) }}</h2>
                            <p class="text-sm text-slate-500">{{ $bodega->ubicacion ?? 'Sin ubicación registrada' }}</p>
                        </div>
                        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $isPrincipal ? 'bg-emerald-100 text-emerald-700' : 'bg-blue-100 text-blue-700' }}">{{ $bodega->tipo ?? '—' }}</span>
                    </div>

                    @if($esOperador)
                        <div class="mt-3 text-xs {{ $esMiBodega ? 'text-emerald-700' : 'text-slate-500' }}">{{ $esMiBodega ? 'Tu bodega asignada' : 'Solo consulta' }}</div>
                    @endif

                    <div class="mt-5 grid grid-cols-2 gap-2">
                        <a href="{{ route($routePrefix . '.bodegas.show', $bodega->id) }}" class="{{ ($esOperador && !$esMiBodega) ? 'col-span-2' : '' }} ui-btn-dark">Ver inventario</a>

                        @if(auth()->user()->role_id == 1)
                            <a href="{{ route('admin.operaciones.traslados.create', ['origen' => $bodega->id]) }}" class="ui-btn-transfer">Trasladar</a>
                        @endif

                        @if($esOperador && $esMiBodega)
                            <a href="{{ route('operador.operaciones.traslados.create', ['origen' => $bodega->id]) }}" class="ui-btn-transfer">Trasladar</a>
                        @endif

                        @if(auth()->user()->role_id == 1)
                            <a href="{{ route('admin.operaciones.traslados.index') }}" class="ui-btn-download col-span-2">Aprobación de traslados</a>
                            <a href="{{ route('admin.bodegas.edit', $bodega->id) }}" class="ui-btn-edit">Editar</a>
                            <form method="POST" action="{{ route('admin.bodegas.destroy', $bodega->id) }}" onsubmit="return confirm('¿Eliminar esta bodega?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="ui-btn-danger w-full">Eliminar</button>
                            </form>
                        @endif
                    </div>
                </article>
            @empty
                <div class="col-span-full rounded-xl border border-amber-200 bg-amber-50 p-6 text-amber-800">
                    <div class="font-semibold">No hay bodegas registradas aún.</div>
                    <div class="mt-1 text-sm">Crea una nueva bodega para empezar.</div>
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection

@extends('layouts.admin')

@section('title', 'Bodegas')

@section('content')
@php
    $esOperador = auth()->user()->role_id == 2;
    $bodegaOperadorId = auth()->user()->bodega_id;
@endphp

<div class="ui-panel w-full max-w-7xl overflow-hidden text-[#1F2937]">
    <div class="bg-slate-900 text-white px-6 py-5 md:px-8">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Bodegas · Vista Empresarial</h1>
                <p class="mt-1 text-sm text-slate-300">Diseño renovado con enfoque corporativo y mejor legibilidad.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('dashboard') }}" class="inline-flex items-center rounded-lg bg-sky-200 px-4 py-2 text-sm font-semibold text-[#1F2937] shadow-sm transition hover:bg-sky-300">← Volver al menú</a>
                @if(auth()->user()->role_id == 1)
                    <a href="{{ route('admin.bodegas.create') }}" class="inline-flex items-center rounded-lg bg-indigo-700 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-800">+ Nueva bodega</a>
                @endif
            </div>
        </div>
        <div class="mt-3 text-xs text-slate-300">Total: <span class="font-semibold text-white">{{ $bodegas->count() }}</span> bodegas</div>
    </div>

    <div class="p-6 md:p-8 bg-slate-50">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse($bodegas as $bodega)
                @php
                    $esMiBodega = (int) $bodega->id === (int) $bodegaOperadorId;
                    $isPrincipal = ($bodega->tipo === 'Principal');
                @endphp

                <article class="ui-card p-4">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <h2 class="text-lg font-semibold text-[#1F2937]">{{ $bodega->nombre ?? ('Bodega #'.$bodega->id) }}</h2>
                            <p class="text-sm text-[#475569]">{{ $bodega->ubicacion ?? 'Sin ubicación registrada' }}</p>
                        </div>
                        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $isPrincipal ? 'bg-emerald-100 text-emerald-700' : 'bg-blue-100 text-blue-700' }}">{{ $bodega->tipo ?? '—' }}</span>
                    </div>

                    @if($esOperador)
                        <div class="mt-3 text-xs {{ $esMiBodega ? 'text-emerald-700' : 'text-slate-500' }}">{{ $esMiBodega ? 'Tu bodega asignada' : 'Solo consulta' }}</div>
                    @endif

                    <div class="mt-4 grid grid-cols-2 gap-2">
                        <a href="{{ $esOperador ? route('operador.bodegas.show', $bodega->id) : route('admin.bodegas.show', $bodega->id) }}" class="{{ ($esOperador && !$esMiBodega) ? 'col-span-2' : '' }} rounded-lg bg-gradient-to-r from-indigo-700 to-blue-700 px-3 py-2 text-center text-sm font-semibold text-white shadow-sm transition hover:from-indigo-800 hover:to-blue-800">Ver inventario</a>

                        @if(auth()->user()->role_id == 1)
                            <a href="{{ route('admin.operaciones.traslados.create', ['origen' => $bodega->id]) }}" class="rounded-lg bg-slate-800 px-3 py-2 text-center text-sm font-semibold text-white shadow-sm transition hover:bg-black">Trasladar</a>
                        @endif

                        @if($esOperador && $esMiBodega)
                            <a href="{{ route('operador.operaciones.traslados.create', ['origen' => $bodega->id]) }}" class="rounded-lg bg-slate-800 px-3 py-2 text-center text-sm font-semibold text-white shadow-sm transition hover:bg-black">Trasladar</a>
                        @endif

                        @if(auth()->user()->role_id == 1)
                            <a href="{{ route('admin.operaciones.traslados.index') }}" class="col-span-2 rounded-lg bg-cyan-200 px-3 py-2 text-center text-sm font-semibold text-[#1F2937] shadow-sm transition hover:bg-cyan-300">Aprobación de traslados</a>
                            <a href="{{ route('admin.bodegas.edit', $bodega->id) }}" class="rounded-lg bg-amber-200 px-3 py-2 text-center text-sm font-semibold text-[#1F2937] shadow-sm transition hover:bg-amber-300">Editar</a>
                            <form method="POST" action="{{ route('admin.bodegas.destroy', $bodega->id) }}" onsubmit="return confirm('¿Eliminar esta bodega?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="w-full rounded-lg bg-rose-200 px-3 py-2 text-sm font-semibold text-rose-900 shadow-sm transition hover:bg-rose-300">Eliminar</button>
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

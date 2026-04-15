@extends('layouts.admin')

@section('title', 'Bodegas')

@section('content')
@php
    $esOperador = auth()->user()->role_id == 2;
    $bodegaOperadorId = auth()->user()->bodega_id;
@endphp

<div class="w-full max-w-7xl rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl md:p-8">
    <div class="mb-6 rounded-2xl border border-blue-100 bg-gradient-to-r from-blue-50 to-sky-50 p-6">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 md:text-3xl">Panel de Bodegas</h1>
                <p class="mt-1 text-sm text-slate-600">Visualiza inventario por sede y gestiona movimientos rápidamente.</p>
                <div class="mt-3 inline-flex items-center gap-2 rounded-full bg-blue-600 px-3 py-1 text-xs font-semibold text-white">
                    <span class="inline-block h-2 w-2 rounded-full bg-emerald-300"></span>
                    {{ $bodegas->count() }} bodegas registradas
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('dashboard') }}"
                   class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    ← Volver al menú
                </a>

                @if(auth()->user()->role_id == 1)
                    <a href="{{ route('admin.bodegas.create') }}"
                       class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                        <span class="text-base">＋</span>
                        Nueva bodega
                    </a>
                @endif
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            {{ session('error') }}
        </div>
    @endif

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse($bodegas as $bodega)
            @php
                $esMiBodega = (int) $bodega->id === (int) $bodegaOperadorId;
                $isPrincipal = ($bodega->tipo === 'Principal');
            @endphp

            <div class="group overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg">
                <div class="p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-blue-100 text-blue-700">🏬</span>
                                <h2 class="truncate text-lg font-bold text-slate-900">{{ $bodega->nombre ?? ('Bodega #'.$bodega->id) }}</h2>
                            </div>
                            <p class="mt-2 truncate text-sm text-slate-600">{{ $bodega->ubicacion ?? 'Sin ubicación registrada' }}</p>
                        </div>

                        <span class="shrink-0 rounded-full border px-3 py-1 text-xs font-semibold {{ $isPrincipal ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-blue-200 bg-blue-50 text-blue-700' }}">
                            {{ $bodega->tipo ?? '—' }}
                        </span>
                    </div>

                    @if($esOperador)
                        <div class="mt-3 text-xs {{ $esMiBodega ? 'text-emerald-700' : 'text-slate-500' }}">
                            {{ $esMiBodega ? 'Tu bodega asignada' : 'Disponible solo para consulta' }}
                        </div>
                    @endif

                    <div class="my-4 h-px bg-slate-100"></div>

                    <div class="grid grid-cols-2 gap-2">
                        <a href="{{ $esOperador ? route('operador.bodegas.show', $bodega->id) : route('admin.bodegas.show', $bodega->id) }}"
                           class="{{ ($esOperador && !$esMiBodega) ? 'col-span-2' : '' }} inline-flex items-center justify-center rounded-xl bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                            Ver inventario
                        </a>

                        @if(auth()->user()->role_id == 1)
                            <a href="{{ route('admin.operaciones.traslados.create', ['origen' => $bodega->id]) }}"
                               class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-black">
                                Trasladar
                            </a>
                        @endif

                        @if($esOperador && $esMiBodega)
                            <a href="{{ route('operador.operaciones.traslados.create', ['origen' => $bodega->id]) }}"
                               class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-black">
                                Trasladar
                            </a>
                        @endif

                        @if(auth()->user()->role_id == 1)
                            <a href="{{ route('admin.operaciones.traslados.index') }}"
                               class="col-span-2 inline-flex items-center justify-center rounded-xl border border-blue-200 bg-blue-50 px-3 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-100">
                                Aprobación de traslados
                            </a>

                            <a href="{{ route('admin.bodegas.edit', $bodega->id) }}"
                               class="inline-flex items-center justify-center rounded-xl bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-200">
                                Editar
                            </a>

                            <form method="POST" action="{{ route('admin.bodegas.destroy', $bodega->id) }}" onsubmit="return confirm('¿Eliminar esta bodega?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="w-full rounded-xl bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-100">
                                    Eliminar
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full rounded-2xl border border-amber-200 bg-amber-50 p-6 text-amber-800">
                <div class="font-semibold">No hay bodegas registradas aún.</div>
                <div class="mt-1 text-sm">Crea una nueva bodega para empezar a cargar inventario.</div>
            </div>
        @endforelse
    </div>
</div>
@endsection

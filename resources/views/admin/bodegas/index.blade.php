@extends('layouts.admin')

@section('title', 'Bodegas')

@section('content')
@php
    $esOperador = auth()->user()->role_id == 2;
    $bodegaOperadorId = auth()->user()->bodega_id;
@endphp

<div class="w-full max-w-7xl rounded-2xl border border-slate-200 bg-white shadow-xl">
    <div class="border-b border-slate-200 px-6 py-5 md:px-8">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Gestión de Bodegas</h1>
                <p class="mt-1 text-sm text-slate-600">Consulta inventario por sede y administra traslados internos.</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('dashboard') }}"
                   class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    ← Volver al menú
                </a>

                @if(auth()->user()->role_id == 1)
                    <a href="{{ route('admin.bodegas.create') }}"
                       class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-black">
                        + Nueva bodega
                    </a>
                @endif
            </div>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Total de bodegas</div>
                <div class="mt-1 text-2xl font-semibold text-slate-900">{{ $bodegas->count() }}</div>
            </div>
            <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Rol actual</div>
                <div class="mt-1 text-sm font-semibold text-slate-800">{{ $esOperador ? 'Operador' : 'Administrador' }}</div>
            </div>
            <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Acceso</div>
                <div class="mt-1 text-sm font-semibold text-slate-800">{{ $esOperador ? 'Consulta y traslados de tu bodega' : 'Control total de bodegas' }}</div>
            </div>
        </div>
    </div>

    <div class="px-6 py-5 md:px-8">
        @if(session('success'))
            <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                {{ session('error') }}
            </div>
        @endif

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse($bodegas as $bodega)
                @php
                    $esMiBodega = (int) $bodega->id === (int) $bodegaOperadorId;
                    $isPrincipal = ($bodega->tipo === 'Principal');
                @endphp

                <div class="rounded-xl border border-slate-200 bg-white shadow-sm transition hover:shadow-md">
                    <div class="border-b border-slate-100 px-4 py-3">
                        <div class="flex items-center justify-between gap-2">
                            <div class="min-w-0">
                                <h2 class="truncate text-base font-semibold text-slate-900">{{ $bodega->nombre ?? ('Bodega #'.$bodega->id) }}</h2>
                                <p class="truncate text-sm text-slate-500">{{ $bodega->ubicacion ?? 'Sin ubicación registrada' }}</p>
                            </div>
                            <span class="rounded-full border px-2.5 py-1 text-xs font-medium {{ $isPrincipal ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-blue-200 bg-blue-50 text-blue-700' }}">
                                {{ $bodega->tipo ?? '—' }}
                            </span>
                        </div>
                    </div>

                    <div class="px-4 py-3">
                        @if($esOperador)
                            <div class="mb-3 rounded-md px-2 py-1 text-xs font-medium {{ $esMiBodega ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                                {{ $esMiBodega ? 'Tu bodega asignada' : 'Disponible solo para consulta' }}
                            </div>
                        @endif

                        <div class="grid grid-cols-2 gap-2">
                            <a href="{{ $esOperador ? route('operador.bodegas.show', $bodega->id) : route('admin.bodegas.show', $bodega->id) }}"
                               class="{{ ($esOperador && !$esMiBodega) ? 'col-span-2' : '' }} inline-flex items-center justify-center rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">
                                Ver inventario
                            </a>

                            @if(auth()->user()->role_id == 1)
                                <a href="{{ route('admin.operaciones.traslados.create', ['origen' => $bodega->id]) }}"
                                   class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-black">
                                    Trasladar
                                </a>
                            @endif

                            @if($esOperador && $esMiBodega)
                                <a href="{{ route('operador.operaciones.traslados.create', ['origen' => $bodega->id]) }}"
                                   class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-black">
                                    Trasladar
                                </a>
                            @endif

                            @if(auth()->user()->role_id == 1)
                                <a href="{{ route('admin.operaciones.traslados.index') }}"
                                   class="col-span-2 inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                    Aprobación de traslados
                                </a>

                                <a href="{{ route('admin.bodegas.edit', $bodega->id) }}"
                                   class="inline-flex items-center justify-center rounded-lg bg-slate-100 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200">
                                    Editar
                                </a>

                                <form method="POST" action="{{ route('admin.bodegas.destroy', $bodega->id) }}" onsubmit="return confirm('¿Eliminar esta bodega?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="w-full rounded-lg bg-rose-50 px-3 py-2 text-sm font-medium text-rose-700 hover:bg-rose-100">
                                        Eliminar
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full rounded-xl border border-amber-200 bg-amber-50 p-6 text-amber-800">
                    <div class="font-semibold">No hay bodegas registradas aún.</div>
                    <div class="mt-1 text-sm">Crea una nueva bodega para empezar a cargar inventario.</div>
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection

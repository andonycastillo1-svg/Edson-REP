@extends('layouts.admin')

@section('title', 'Bodegas')

@section('content')
<div class="w-full max-w-6xl bg-white/90 backdrop-blur rounded-2xl shadow-2xl p-6 md:p-8 border border-white/50">

    {{-- Header --}}
    <div class="flex items-start justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold text-slate-800 tracking-tight">Bodegas</h1>
            <p class="text-slate-500 text-sm">Selecciona una bodega para ver su inventario y operaciones.</p>
        </div>

        @if(auth()->user()->role_id == 1)
        <a href="{{ route('admin.bodegas.create') }}"
           class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-blue-600 text-white font-semibold shadow hover:bg-blue-700 hover:shadow-md active:scale-[0.99] transition">
            <span class="text-lg leading-none">＋</span>
            Nueva bodega
        </a>
        @endif
    </div>

    {{-- Alerts --}}
    @if(session('success'))
        <div class="mb-4 p-3 rounded-xl bg-emerald-50 text-emerald-800 border border-emerald-200">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="mb-4 p-3 rounded-xl bg-rose-50 text-rose-800 border border-rose-200">
            {{ session('error') }}
        </div>
    @endif

    @php
        $esOperador = auth()->user()->role_id == 2;
        $bodegaOperadorId = auth()->user()->bodega_id;
    @endphp

    {{-- Top actions / info --}}
    <div class="mb-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <a href="{{ route('dashboard') }}"
           class="inline-flex items-center gap-2 text-blue-700 font-semibold hover:text-blue-900 transition">
            ← Volver al menú
        </a>

        <div class="text-sm text-slate-500">
            Total bodegas:
            <span class="font-semibold text-slate-800">{{ $bodegas->count() }}</span>
        </div>
    </div>

    {{-- Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse($bodegas as $bodega)
            @php
                $esMiBodega = (int)$bodega->id === (int)$bodegaOperadorId;
            @endphp

            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm hover:shadow-md transition overflow-hidden">
                <div class="p-5">

                    {{-- Header card --}}
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="text-lg font-bold text-slate-900 leading-tight truncate">
                                {{ $bodega->nombre ?? ('Bodega #'.$bodega->id) }}
                            </div>
                            <div class="text-sm text-slate-500 truncate">
                                {{ $bodega->ubicacion ?? 'Sin ubicación' }}
                            </div>
                        </div>

                        @php
                            $isPrincipal = ($bodega->tipo === 'Principal');
                        @endphp
                        <span class="shrink-0 px-3 py-1 rounded-full text-xs font-semibold border
                            {{ $isPrincipal ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-blue-50 text-blue-700 border-blue-200' }}">
                            {{ $bodega->tipo ?? '—' }}
                        </span>
                    </div>

                    {{-- Divider --}}
                    <div class="mt-4 h-px bg-slate-100"></div>

                    {{-- Actions --}}
                    <div class="mt-4 grid grid-cols-2 gap-2">

                        {{-- Ver inventario (todos) --}}
                        <a href="{{ auth()->user()->role_id == 2
    ? route('operador.bodegas.show', $bodega->id)
    : route('admin.bodegas.show', $bodega->id) }}"
   class="inline-flex items-center justify-center px-3 py-2 rounded-xl bg-blue-600 text-white font-semibold hover:bg-blue-700 transition {{ ($esOperador && !$esMiBodega) ? 'col-span-2' : '' }}">
    Ver inventario
</a>

                        {{-- ADMIN: puede trasladar cualquier bodega --}}
                        @if(auth()->user()->role_id == 1)
                        <a href="{{ route('admin.operaciones.traslados.create', ['origen' => $bodega->id]) }}"
                           class="inline-flex items-center justify-center px-3 py-2 rounded-xl bg-blue-600 text-white font-semibold hover:bg-blue-700 transition">
                            Trasladar
                        </a>
                        @endif

                        {{-- OPERADOR: solo su bodega puede trasladar --}}
                        @if($esOperador && $esMiBodega)
                        <a href="{{ route('operador.operaciones.traslados.create', ['origen' => $bodega->id]) }}"
                           class="inline-flex items-center justify-center px-3 py-2 rounded-xl bg-blue-600 text-white font-semibold hover:bg-blue-700 transition">
                            Trasladar
                        </a>
                        @endif

                        {{-- Aprobación de traslados: solo admin --}}
                        @if(auth()->user()->role_id == 1)
                        <a href="{{ route('admin.operaciones.traslados.index') }}"
                           class="col-span-2 inline-flex items-center justify-center px-3 py-2 rounded-xl border border-blue-200 text-blue-700 bg-blue-50/40 font-semibold hover:bg-blue-50 transition">
                            Aprobación de traslados
                        </a>
                        @endif

                        {{-- Editar (solo Admin) --}}
                        @if(auth()->user()->role_id == 1)
                        <a href="{{ route('admin.bodegas.edit', $bodega->id) }}"
                           class="inline-flex items-center justify-center px-3 py-2 rounded-xl bg-slate-100 text-slate-800 font-semibold hover:bg-slate-200 transition">
                            Editar
                        </a>
                        @endif

                        {{-- Eliminar (solo Admin) --}}
                        @if(auth()->user()->role_id == 1)
                        <form method="POST" action="{{ route('admin.bodegas.destroy', $bodega->id) }}"
                              onsubmit="return confirm('¿Eliminar esta bodega?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    class="w-full inline-flex items-center justify-center px-3 py-2 rounded-xl bg-rose-50 text-rose-700 font-semibold hover:bg-rose-100 transition">
                                Eliminar
                            </button>
                        </form>
                        @endif

                    </div>

                </div>
            </div>
        @empty
            <div class="col-span-full p-6 rounded-2xl bg-amber-50 text-amber-800 border border-amber-200">
                <div class="font-semibold">No hay bodegas registradas aún.</div>
                <div class="text-sm mt-1">Crea una nueva bodega para empezar a cargar inventario.</div>
            </div>
        @endforelse
    </div>

</div>
@endsection
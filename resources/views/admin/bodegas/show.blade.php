@extends('layouts.admin')

@section('title', 'Inventario')

@section('content')
@php
    $routePrefix = match ((int) auth()->user()->role_id) {
        2 => 'operador',
        3 => 'coordinador',
        default => 'admin',
    };
    $canAddInventory = auth()->user()->role_id == 1
        || ((int) auth()->user()->role_id === 2 && (int) auth()->user()->bodega_id === (int) $bodega->id);
@endphp
<div class="ui-panel w-full max-w-7xl overflow-hidden">

    <div class="bg-gradient-to-r from-slate-900 via-sky-900 to-blue-800 px-6 py-6 text-white md:px-8">
    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.28em] text-sky-200">Inventario por bodega</p>
            <h1 class="mt-2 text-2xl font-extrabold tracking-tight md:text-3xl">
                Inventario — {{ $bodega->nombre ?? ('Bodega #'.$bodega->id) }}
            </h1>
            <p class="mt-2 text-sm text-sky-100">
                Tipo: <span class="font-semibold text-white">{{ $bodega->tipo }}</span>
                · Ubicación: <span class="font-semibold text-white">{{ $bodega->ubicacion ?? '—' }}</span>
            </p>
        </div>

        <div class="flex gap-2 flex-wrap">
            <a href="{{ route('dashboard') }}"
               class="ui-btn bg-white/15 text-white ring-1 ring-white/25 hover:bg-white/25">
                ← Volver
            </a>

            <button class="ui-btn-download opacity-70" disabled>
                Descargar inventario
            </button>

            @if($canAddInventory)
                <a href="{{ route($routePrefix . '.bodegas.entrada', $bodega->id) }}"
                   class="ui-btn-create">
                    + Agregar al inventario
                </a>
            @endif
        </div>
    </div>
    </div>

    {{-- RESUMEN --}}
    <div class="bg-slate-50/80 p-6 md:p-8">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="ui-stat-card">
            <div class="text-slate-500 text-sm font-semibold">Productos</div>
            <div class="mt-2 text-3xl font-extrabold text-slate-900">{{ $productosTotal }}</div>
        </div>

        <div class="ui-stat-card">
            <div class="text-slate-500 text-sm font-semibold">Stock total</div>
            <div class="mt-2 text-3xl font-extrabold text-emerald-700">{{ number_format($stockTotal) }}</div>
        </div>

        <div class="ui-stat-card">
            <div class="text-slate-500 text-sm font-semibold">Costo total (inventario)</div>
            <div class="mt-2 text-3xl font-extrabold text-indigo-700">
                Q {{ number_format((float)($costoTotalInventario ?? 0), 2) }}
            </div>
            <div class="text-xs text-slate-500 mt-1">
                * Basado en el último costo de compra registrado por producto.
            </div>
        </div>
    </div>

    {{-- TABLA --}}
    <div class="ui-table overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr>
                    <th class="px-4 py-3">Código</th>
                    <th class="px-4 py-3">Producto</th>
                    <th class="px-4 py-3">Unidad</th>
                    <th class="px-4 py-3 text-right">Cantidad</th>
                    <th class="px-4 py-3 text-right">Vida útil (meses)</th>
                    <th class="px-4 py-3 text-right">Costo unitario</th>
                    <th class="px-4 py-3 text-right">Costo total</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-slate-100">
                @forelse($inventarios as $inv)
                    <tr>
                        <td class="px-4 py-3 font-semibold text-slate-800">
                            {{ $inv->producto_codigo }}
                        </td>

                        <td class="px-4 py-3">
                            <div class="font-semibold text-slate-800">{{ $inv->nombre }}</div>
                            <div class="text-slate-500 text-xs line-clamp-2">
                                {{ $inv->descripcion ?: 'Sin descripción' }}
                            </div>
                        </td>

                        <td class="px-4 py-3 text-slate-700">
                            {{ $inv->unidad_medida ?? '—' }}
                        </td>

                        <td class="px-4 py-3 text-right font-bold text-slate-800">
                            {{ number_format($inv->cantidad) }}
                        </td>

                        <td class="px-4 py-3 text-right text-slate-700">
                            {{ is_null($inv->vida_util_meses) ? '—' : number_format($inv->vida_util_meses) }}
                        </td>

                        <td class="px-4 py-3 text-right text-slate-700">
                            Q {{ number_format((float)($inv->costo_unitario ?? 0), 2) }}
                        </td>

                        <td class="px-4 py-3 text-right font-bold text-slate-800">
                            Q {{ number_format((float)($inv->costo_total ?? 0), 2) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-slate-500">
                            Esta bodega aún no tiene inventario.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- PAGINACIÓN --}}
    <div class="mt-5 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <p class="text-sm text-slate-600">
            Mostrando
            <span class="font-semibold">{{ $inventarios->firstItem() ?? 0 }}</span>
            a
            <span class="font-semibold">{{ $inventarios->lastItem() ?? 0 }}</span>
            de
            <span class="font-semibold">{{ $inventarios->total() }}</span>
            resultados
        </p>

        <div>
            {{ $inventarios->links() }}
        </div>
    </div>
    </div>

</div>
@endsection
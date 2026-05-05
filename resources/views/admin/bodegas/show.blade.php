@extends('layouts.admin')

@section('title', 'Inventario')

@section('content')
<div class="w-full max-w-6xl bg-white/90 rounded-2xl shadow-2xl p-8">

    <div class="flex items-start justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">
                Inventario — {{ $bodega->nombre ?? ('Bodega #'.$bodega->id) }}
            </h1>
            <p class="text-slate-500 text-sm">
                Tipo: <span class="font-semibold text-slate-700">{{ $bodega->tipo }}</span>
                · Ubicación: <span class="font-semibold text-slate-700">{{ $bodega->ubicacion ?? '—' }}</span>
            </p>
        </div>

        <div class="flex gap-2 flex-wrap">
            <a href="{{ route('dashboard') }}"
               class="px-4 py-2 rounded-xl bg-sky-600 text-white font-semibold shadow-sm transition hover:bg-sky-700">
                ← Volver
            </a>

            <button class="px-4 py-2 rounded-xl bg-blue-600 text-white font-semibold hover:bg-blue-700 transition" disabled>
                Descargar inventario
            </button>

            <a href="{{ route('admin.bodegas.entrada', $bodega->id) }}"
               class="px-4 py-2 rounded-xl bg-indigo-600 text-white font-semibold shadow-sm transition hover:bg-indigo-700">
                + Agregar al inventario
            </a>
        </div>
    </div>

    {{-- RESUMEN --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="p-4 rounded-2xl bg-white border border-slate-200">
            <div class="text-slate-500 text-sm">Productos</div>
            <div class="text-2xl font-bold text-slate-800">{{ $productosTotal }}</div>
        </div>

        <div class="p-4 rounded-2xl bg-white border border-slate-200">
            <div class="text-slate-500 text-sm">Stock total</div>
            <div class="text-2xl font-bold text-slate-800">{{ number_format($stockTotal) }}</div>
        </div>

        <div class="p-4 rounded-2xl bg-white border border-slate-200">
            <div class="text-slate-500 text-sm">Costo total (inventario)</div>
            <div class="text-2xl font-bold text-slate-800">
                Q {{ number_format((float)($costoTotalInventario ?? 0), 2) }}
            </div>
            <div class="text-xs text-slate-500 mt-1">
                * Basado en el último costo de compra registrado por producto.
            </div>
        </div>
    </div>

    {{-- TABLA --}}
    <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50">
                <tr class="text-left text-slate-600">
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
                    <tr class="hover:bg-slate-50">
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
@endsection
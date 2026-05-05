@extends('layouts.admin')

@section('title', 'Detalle Compra')

@section('content')
@php($routePrefix = auth()->user()->role_id == 2 ? 'operador' : 'admin')
<div class="w-full max-w-6xl bg-white/90 rounded-2xl shadow-2xl p-8">

    <div class="flex items-start justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Compra #{{ $compra->id }}</h1>
            <p class="text-slate-500 text-sm">
                Proveedor: <span class="font-semibold text-slate-700">{{ $compra->proveedor_nombre }}</span> ·
                Factura: <span class="font-semibold text-slate-700">{{ $compra->no_factura }}</span> ·
                Fecha de compra: <span class="font-semibold text-slate-700">{{ $compra->fecha_compra }}</span>
            </p>
        </div>

        <a href="{{ route($routePrefix . '.compras.index') }}"
           class="px-4 py-2 rounded-xl border border-slate-200 font-semibold hover:bg-slate-50 transition">
            ← Volver
        </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="p-4 rounded-2xl bg-white border border-slate-200">
            <div class="text-slate-500 text-sm">Proyecto</div>
            <div class="font-semibold text-slate-800">{{ $compra->proyecto ?? '—' }}</div>
        </div>
        <div class="p-4 rounded-2xl bg-white border border-slate-200">
            <div class="text-slate-500 text-sm">Forma de pago</div>
            <div class="font-semibold text-slate-800">{{ $compra->forma_pago ?? '—' }}</div>
        </div>
        <div class="p-4 rounded-2xl bg-white border border-slate-200">
            <div class="text-slate-500 text-sm">Total factura</div>
            <div class="text-2xl font-bold text-slate-800">{{ number_format($compra->total_factura, 2) }}</div>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white overflow-x-auto mb-6">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-slate-600">
                <tr>
                    <th class="px-4 py-3">Código</th>
                    <th class="px-4 py-3">Producto</th>
                    <th class="px-4 py-3">Unidad</th>
                    <th class="px-4 py-3 text-right">Cantidad</th>
                    <th class="px-4 py-3 text-right">Precio U.</th>
                    <th class="px-4 py-3 text-right">Valor</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($detalles as $d)
                    <tr>
                        <td class="px-4 py-3 font-semibold">{{ $d->producto_codigo }}</td>
                        <td class="px-4 py-3">{{ $d->producto_nombre }}</td>
                        <td class="px-4 py-3">{{ $d->unidad_medida ?? '—' }}</td>
                        <td class="px-4 py-3 text-right font-semibold">{{ number_format($d->cantidad) }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format($d->precio_unitario, 2) }}</td>
                        <td class="px-4 py-3 text-right font-bold">{{ number_format($d->valor_total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-4">
        <h2 class="font-bold text-slate-800 mb-2">Archivos (PDF)</h2>

        @if($archivos->isEmpty())
            <div class="text-slate-500 text-sm">No hay archivos adjuntos.</div>
        @else
            <ul class="list-disc pl-6 text-sm">
                @foreach($archivos as $a)
                    <li class="mb-1">
                        <a class="text-blue-700 font-semibold hover:underline" href="{{ $a->url }}" target="_blank">
                            {{ $a->nombre_original ?? $a->ruta }}
                        </a>
                        <span class="text-slate-500">({{ number_format(($a->tamano ?? 0)/1024, 0) }} KB)</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

</div>
@endsection

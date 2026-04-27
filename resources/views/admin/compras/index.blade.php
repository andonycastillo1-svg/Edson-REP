@extends('layouts.admin')

@section('title', 'Compras')

@section('content')
@php($routePrefix = auth()->user()->role_id == 2 ? 'operador' : 'admin')
<div class="ui-panel w-full max-w-6xl overflow-hidden">

    {{-- HEADER --}}
    <div class="ui-section-header flex items-start justify-between gap-4">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.24em] text-blue-100">Compras</p>
            <h1 class="mt-1 text-3xl font-extrabold text-white">Compras (Facturas)</h1>
            <p class="mt-1 text-sm text-blue-100">
                Registrar facturas y generar entradas automáticas al inventario (bodega principal).
            </p>
        </div>

        @if($canCreateCompra)
            <button onclick="abrirModal()"
                class="ui-btn-success">
                + Nueva compra
            </button>
        @endif
    </div>

    <div class="p-6 md:p-8">
    {{-- MENSAJES --}}
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

    {{-- TABLA --}}
    <div class="ui-table overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Fecha de compra</th>
                    <th>No. Factura</th>
                    <th>Proveedor</th>
                    <th>Proyecto</th>
                    <th class="text-right">Total</th>
                    <th class="text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($compras as $c)
                    <tr class="hover:bg-slate-50">
                        <td class="font-semibold text-slate-800">{{ $c->id }}</td>
                        <td>{{ $c->fecha_compra }}</td>
                        <td class="font-semibold">{{ $c->no_factura }}</td>
                        <td>{{ $c->proveedor_nombre }}</td>
                        <td>{{ $c->proyecto ?? '—' }}</td>
                        <td class="text-right font-bold">
                            {{ number_format($c->total_factura, 2) }}
                        </td>
                        <td class="text-right">
                            <a class="ui-btn-info"
                               href="{{ route($routePrefix . '.compras.show', $c->id) }}">
                                Ver
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-slate-500">
                            No hay compras registradas.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6 text-sm text-gray-500">
        <a href="{{ route('dashboard') }}"
           class="ui-btn-secondary">
            ← Volver al menú
        </a>
    </div>
    </div>

</div>


@if($canCreateCompra)
<div id="modalCompra" class="fixed inset-0 z-50 hidden">

    {{-- FONDO OSCURO --}}
    <div class="absolute inset-0 bg-black/50" onclick="cerrarModal()"></div>

    {{-- CAJA --}}
    <div class="relative mx-auto mt-10 w-[95%] max-w-5xl">
        <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-2xl">

            <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-6 py-4">
                <h2 class="text-lg font-bold text-slate-900">Registrar compra</h2>
                <button onclick="cerrarModal()"
                    class="text-gray-500 hover:text-black text-2xl leading-none">
                    &times;
                </button>
            </div>

            <div class="p-6 max-h-[80vh] overflow-y-auto">
                @include('admin.compras._form')
            </div>

        </div>
    </div>

</div>
@endif


{{-- =========================
     SCRIPT MODAL
========================= --}}
<script>
function abrirModal() {
    const modal = document.getElementById('modalCompra');
    modal.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
}

function cerrarModal() {
    const modal = document.getElementById('modalCompra');
    modal.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') cerrarModal();
});
</script>

@endsection

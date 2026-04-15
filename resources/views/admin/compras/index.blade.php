@extends('layouts.admin')

@section('title', 'Compras')

@section('content')
<div class="w-full max-w-6xl bg-white/90 rounded-2xl shadow-2xl p-8">

    {{-- HEADER --}}
    <div class="flex items-start justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Compras (Facturas)</h1>
            <p class="text-slate-500 text-sm">
                Registrar facturas y generar entradas automáticas al inventario (bodega principal).
            </p>
        </div>

        <button onclick="abrirModal()"
            class="bg-blue-600 text-white px-4 py-2 rounded-lg shadow hover:bg-blue-700 transition">
            + Nueva compra
        </button>
    </div>

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
    <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50">
                <tr class="text-left text-slate-600">
                    <th class="px-4 py-3">ID</th>
                    <th class="px-4 py-3">Fecha de compra</th>
                    <th class="px-4 py-3">No. Factura</th>
                    <th class="px-4 py-3">Proveedor</th>
                    <th class="px-4 py-3">Proyecto</th>
                    <th class="px-4 py-3 text-right">Total</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($compras as $c)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-semibold text-slate-800">{{ $c->id }}</td>
                        <td class="px-4 py-3">{{ $c->fecha_compra }}</td>
                        <td class="px-4 py-3 font-semibold">{{ $c->no_factura }}</td>
                        <td class="px-4 py-3">{{ $c->proveedor_nombre }}</td>
                        <td class="px-4 py-3">{{ $c->proyecto ?? '—' }}</td>
                        <td class="px-4 py-3 text-right font-bold">
                            {{ number_format($c->total_factura, 2) }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a class="px-3 py-2 rounded-xl border border-blue-200 text-blue-700 font-semibold hover:bg-blue-50 transition"
                               href="{{ route('admin.compras.show', $c->id) }}">
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
        <a href="{{ route('admin.dashboard') }}"
           class="text-blue-600 hover:underline">
            ← Volver al menú
        </a>
    </div>

</div>


{{-- =========================
     MODAL NUEVA COMPRA
========================= --}}
<div id="modalCompra" class="fixed inset-0 z-50 hidden">

    {{-- FONDO OSCURO --}}
    <div class="absolute inset-0 bg-black/50" onclick="cerrarModal()"></div>

    {{-- CAJA --}}
    <div class="relative mx-auto mt-10 w-[95%] max-w-5xl">
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden">

            <div class="flex items-center justify-between px-6 py-4 border-b">
                <h2 class="text-lg font-bold">Registrar compra</h2>
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
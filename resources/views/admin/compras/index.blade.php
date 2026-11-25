@extends('layouts.app')

@section('title', 'Compras')

@section('content')
@php($routePrefix = \App\Services\AccesoInventarioOperativo::prefijo(auth()->user()))

<div class="space-y-4">
    <x-ui.encabezado titulo="Compras" descripcion="Consulta las facturas que respaldan entradas de inventario.">
        @rutaDisponible($routePrefix.'.compras.create')
            <x-ui.accion :href="route($routePrefix.'.compras.create')" variante="primaria">Registrar compra</x-ui.accion>
        @endrutaDisponible
    </x-ui.encabezado>

    <div class="flex flex-wrap gap-2">
        <x-sistema.enlace-datos modulo="compras" />
        <x-sistema.enlace-datos modulo="productos" />
    </div>
    <x-sistema.consulta-campos :tabla="$tablaCampos" :conservar="['buscar', 'por_pagina']" />

    <form method="GET" class="ui-filtros sm:grid-cols-2 lg:grid-cols-[minmax(0,2fr)_minmax(7rem,auto)_auto] lg:items-end">
        <x-sistema.conservar-filtros-campos :tabla="$tablaCampos" />
        <label class="ui-label mb-0">Buscar factura
            <input type="search" id="buscarCompra" name="buscar" value="{{ request('buscar') }}" maxlength="190" placeholder="Número, proveedor o fecha" class="ui-input mt-1">
        </label>
        <label class="ui-label mb-0">Filas por página
            <select id="filasPorPagina" name="por_pagina" class="ui-select mt-1">
                @foreach([10, 15, 25, 50] as $cantidad)
                    <option value="{{ $cantidad }}" @selected($compras->perPage() === $cantidad)>{{ $cantidad }} filas</option>
                @endforeach
            </select>
        </label>
        <div class="flex flex-wrap items-center gap-2">
            <x-ui.accion type="submit">Buscar</x-ui.accion>
            <x-ui.exportar-excel modulo="compras" />
            @rutaDisponible($routePrefix.'.compras.create')
                <x-ui.accion onclick="abrirModal()" aria-controls="modalCompra" aria-haspopup="dialog"
                    title="Abre el formulario en el listado">Compra rápida</x-ui.accion>
            @endrutaDisponible
        </div>
    </form>

    <x-sistema.panel titulo="Facturas registradas">
        <p class="mb-3 text-sm">{{ $compras->total() }} facturas</p>
        <div class="ui-table-scroll" tabindex="0" role="region" aria-label="Facturas registradas">
            <table class="ui-table" id="tablaCompras">
                <thead>
                    <tr>
                        <th scope="col">ID</th>
                        <th scope="col" class="whitespace-nowrap">Fecha de compra</th>
                        <th scope="col" class="whitespace-nowrap">Número de factura</th>
                        <th scope="col">Proveedor</th>
                        <th scope="col">Proyecto</th>
                        <th scope="col" class="text-right">Total</th>
                        <x-sistema.cabeceras-campos :tabla="$tablaCampos" />
                        <th scope="col" class="text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($compras as $c)
                        <tr>
                            <td class="whitespace-nowrap">#{{ $c->id }}</td>
                            <td class="whitespace-nowrap">{{ $c->fecha_compra }}</td>
                            <td><strong>{{ $c->no_factura }}</strong></td>
                            <td>{{ $c->proveedor_nombre }}</td>
                            <td>{{ $c->proyecto ?: '—' }}</td>
                            <td class="whitespace-nowrap text-right tabular-nums">Q {{ number_format($c->total_factura, 2) }}</td>
                            <x-sistema.celdas-campos :tabla="$tablaCampos" :registro="$c->id" />
                            <td class="whitespace-nowrap text-right">
                                <x-ui.accion :href="route($routePrefix.'.compras.show', $c->id)" class="ui-btn-sm">Ver factura</x-ui.accion>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ 7 + $tablaCampos['campos']->count() }}"><p class="ui-empty-state">No hay facturas que coincidan con la búsqueda.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $compras->links() }}
    </x-sistema.panel>
</div>

@rutaDisponible($routePrefix.'.compras.create')
    <div id="modalCompra" class="fixed inset-0 z-50 hidden p-3 sm:p-6" role="dialog" aria-modal="true" aria-labelledby="titulo-modal-compra" data-ui-dialogo>
        <div class="absolute inset-0 bg-slate-950/60" onclick="cerrarModal()"></div>
        <div class="ui-modal relative mx-auto flex max-h-full max-w-5xl flex-col overflow-hidden">
            <header class="flex shrink-0 items-start justify-between gap-3 border-b border-line p-4 sm:p-5">
                <div>
                    <h2 id="titulo-modal-compra" class="text-lg font-semibold">Compra rápida</h2>
                    <p class="mt-1 text-sm">Registra la factura sin salir del listado.</p>
                </div>
                <x-ui.accion onclick="cerrarModal()" data-foco-dialogo aria-label="Cerrar compra rápida">Cerrar</x-ui.accion>
            </header>
            <div class="overflow-y-auto p-4 sm:p-5">
                @include('admin.compras._form')
            </div>
        </div>
    </div>
@endrutaDisponible

<script>
function abrirModal() {
    const modal = document.getElementById('modalCompra');
    if (!modal) return;
    modal.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
}

function cerrarModal() {
    const modal = document.getElementById('modalCompra');
    if (!modal || modal.classList.contains('hidden')) return;
    modal.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') cerrarModal();
});

</script>
@endsection

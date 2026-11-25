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
    <x-sistema.consulta-campos :tabla="$tablaCampos" />

    <div class="ui-filtros sm:grid-cols-2 lg:grid-cols-[minmax(0,2fr)_minmax(7rem,auto)_auto] lg:items-end">
        <label class="ui-label mb-0">Buscar factura
            <input type="search" id="buscarCompra" maxlength="190" placeholder="Número, proveedor o fecha" class="ui-input mt-1">
        </label>
        <label class="ui-label mb-0">Filas por página
            <select id="filasPorPagina" class="ui-select mt-1">
                @foreach([10, 15, 25, 50] as $cantidad)
                    <option value="{{ $cantidad }}">{{ $cantidad }} filas</option>
                @endforeach
            </select>
        </label>
        <div class="flex flex-wrap items-center gap-2">
            <x-ui.exportar-excel modulo="compras" :campos="['buscar' => 'buscarCompra']" />
            @rutaDisponible($routePrefix.'.compras.create')
                <x-ui.accion onclick="abrirModal()" aria-controls="modalCompra" aria-haspopup="dialog"
                    title="Abre el formulario en el listado">Compra rápida</x-ui.accion>
            @endrutaDisponible
        </div>
    </div>

    <x-sistema.panel titulo="Facturas registradas">
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
                        <tr class="fila-compra" data-search="{{ strtolower($c->id . ' ' . $c->fecha_compra . ' ' . $c->no_factura . ' ' . $c->proveedor_nombre . ' ' . ($c->proyecto ?? '') . ' ' . number_format($c->total_factura, 2)) }}">
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
                        <tr id="sinRegistrosOriginal"><td colspan="{{ 7 + $tablaCampos['campos']->count() }}"><p class="ui-empty-state">No hay compras registradas.</p></td></tr>
                    @endforelse
                    <tr id="sinResultados" class="hidden"><td colspan="{{ 7 + $tablaCampos['campos']->count() }}"><p class="ui-empty-state">No hay facturas que coincidan con la búsqueda.</p></td></tr>
                </tbody>
            </table>
        </div>

        <nav class="ui-paginacion" aria-label="Paginación de compras">
            <p role="status">Compras <strong id="desdeRegistro">0</strong>–<strong id="hastaRegistro">0</strong> de <strong id="totalRegistros">0</strong></p>
            <div class="flex flex-wrap items-center gap-1.5">
                <button class="ui-pagina" type="button" id="btnAnterior">Anterior</button>
                <div id="numerosPagina" class="flex flex-wrap gap-1.5"></div>
                <button class="ui-pagina" type="button" id="btnSiguiente">Siguiente</button>
            </div>
        </nav>
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

document.addEventListener('DOMContentLoaded', function () {
    const inputBuscar = document.getElementById('buscarCompra');
    const selectFilas = document.getElementById('filasPorPagina');
    const filas = Array.from(document.querySelectorAll('.fila-compra'));
    const sinResultados = document.getElementById('sinResultados');

    const btnAnterior = document.getElementById('btnAnterior');
    const btnSiguiente = document.getElementById('btnSiguiente');
    const numerosPagina = document.getElementById('numerosPagina');

    const desdeRegistro = document.getElementById('desdeRegistro');
    const hastaRegistro = document.getElementById('hastaRegistro');
    const totalRegistros = document.getElementById('totalRegistros');

    let paginaActual = 1;
    let filasPorPagina = parseInt(selectFilas.value);

    function normalizarTexto(texto) {
        return texto
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .trim();
    }

    function obtenerFilasFiltradas() {
        const texto = normalizarTexto(inputBuscar.value);

        return filas.filter(fila => {
            const contenido = normalizarTexto(fila.dataset.search || '');
            return contenido.includes(texto);
        });
    }

    function renderTabla() {
        const filtradas = obtenerFilasFiltradas();
        const total = filtradas.length;
        const totalPaginas = Math.max(1, Math.ceil(total / filasPorPagina));

        if (paginaActual > totalPaginas) {
            paginaActual = totalPaginas;
        }

        filas.forEach(fila => fila.classList.add('hidden'));

        const inicio = (paginaActual - 1) * filasPorPagina;
        const fin = inicio + filasPorPagina;
        const visibles = filtradas.slice(inicio, fin);

        visibles.forEach(fila => fila.classList.remove('hidden'));

        sinResultados.classList.toggle('hidden', total !== 0 || filas.length === 0);

        desdeRegistro.textContent = total === 0 ? 0 : inicio + 1;
        hastaRegistro.textContent = Math.min(fin, total);
        totalRegistros.textContent = total;

        btnAnterior.disabled = paginaActual <= 1;
        btnSiguiente.disabled = paginaActual >= totalPaginas || total === 0;

        renderNumeros(totalPaginas, total);
    }

    function renderNumeros(totalPaginas, total) {
        numerosPagina.innerHTML = '';

        if (total === 0) return;

        const maxBotones = 5;
        let inicio = Math.max(1, paginaActual - 2);
        let fin = Math.min(totalPaginas, inicio + maxBotones - 1);

        if (fin - inicio < maxBotones - 1) {
            inicio = Math.max(1, fin - maxBotones + 1);
        }

        for (let i = inicio; i <= fin; i++) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.textContent = i;

            btn.className = 'ui-pagina';
            btn.dataset.pagina = i;
            if (i === paginaActual) btn.setAttribute('aria-current', 'page');
            btn.setAttribute('aria-label', 'Ir a la página ' + i);
            btn.addEventListener('click', () => {
                paginaActual = i;
                renderTabla();
                numerosPagina.querySelector(`[data-pagina="${i}"]`)?.focus();
            });

            numerosPagina.appendChild(btn);
        }
    }

    inputBuscar.addEventListener('input', function () {
        paginaActual = 1;
        renderTabla();
    });

    selectFilas.addEventListener('change', function () {
        filasPorPagina = parseInt(this.value);
        paginaActual = 1;
        renderTabla();
    });

    btnAnterior.addEventListener('click', function () {
        if (paginaActual > 1) {
            paginaActual--;
            renderTabla();
        }
    });

    btnSiguiente.addEventListener('click', function () {
        paginaActual++;
        renderTabla();
    });

    renderTabla();
});
</script>

@endsection

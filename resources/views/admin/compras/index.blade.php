@extends('layouts.admin')

@section('title', 'Compras')

@section('content')
@php($routePrefix = auth()->user()->role_id == 2 ? 'operador' : 'admin')

<div class="w-full">

    <div class="overflow-hidden rounded-3xl border border-sky-200 bg-white shadow-xl shadow-sky-100/70">

        {{-- HEADER --}}
        <div class="border-b border-sky-100 bg-gradient-to-r from-white via-sky-50 to-blue-50 px-6 py-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">

                <div class="flex items-start gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl border border-sky-200 bg-white shadow-sm">
                        <span class="text-xl text-blue-700">🧾</span>
                    </div>

                    <div>
                        <h1 class="text-2xl font-black tracking-tight text-slate-900 md:text-3xl">
                            Compras <span class="font-bold text-blue-700">(Facturas)</span>
                        </h1>
                        <p class="mt-1 text-sm text-slate-500">
                            Registrar facturas y generar entradas automáticas al inventario.
                        </p>
                    </div>
                </div>

            </div>
        </div>

        {{-- MENSAJES --}}
        <div class="px-6 pt-5">
            @if(session('success'))
                <div class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    <strong>Correcto:</strong> {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="mb-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    <strong>Error:</strong> {{ session('error') }}
                </div>
            @endif
        </div>

        {{-- BARRA DE HERRAMIENTAS --}}
        <div class="px-6 pb-5">
            <div class="rounded-3xl border border-sky-200 bg-sky-50/70 px-5 py-4 shadow-sm">

                <div>
                    <h2 class="text-base font-black text-slate-900">
                        Listado de compras
                    </h2>
                    <p class="mt-0.5 text-sm text-slate-500">
                        Busca por ID, factura, proveedor, proyecto, fecha o total.
                    </p>

                    <div class="mt-4 flex flex-col gap-3 lg:flex-row lg:items-center">

                        <input
                            id="buscarCompra"
                            type="text"
                            placeholder="Buscar compra..."
                            class="w-full rounded-2xl border border-sky-200 bg-white px-4 py-2.5 text-sm text-slate-700 shadow-sm outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100 lg:w-[360px]"
                        >

                        <select
                            id="filasPorPagina"
                            class="w-full rounded-2xl border border-sky-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 shadow-sm outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100 lg:w-36"
                        >
                            <option value="10">10 filas</option>
                            <option value="15">15 filas</option>
                            <option value="25">25 filas</option>
                            <option value="50">50 filas</option>
                        </select>

                        <button onclick="abrirModal()"
                            type="button"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-blue-700 px-7 py-3 text-sm font-black text-white shadow-md shadow-blue-200 transition hover:bg-blue-800 active:scale-[0.98] lg:w-auto">
                            <span class="text-lg leading-none">+</span>
                            Nueva compra
                        </button>

                    </div>
                </div>

            </div>
        </div>

        {{-- TABLA --}}
        <div class="px-6 pb-6">
            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm" id="tablaCompras">
                        <thead class="border-b border-slate-200 bg-gradient-to-r from-slate-50 to-sky-50">
                            <tr class="text-left text-xs font-black uppercase tracking-wide text-slate-600">
                                <th class="px-4 py-3 whitespace-nowrap">ID</th>
                                <th class="px-4 py-3 whitespace-nowrap">Fecha de compra</th>
                                <th class="px-4 py-3 whitespace-nowrap">No. Factura</th>
                                <th class="px-4 py-3 whitespace-nowrap">Proveedor</th>
                                <th class="px-4 py-3 whitespace-nowrap">Proyecto</th>
                                <th class="px-4 py-3 text-right whitespace-nowrap">Total</th>
                                <th class="px-4 py-3 text-right whitespace-nowrap">Acciones</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100">
                            @forelse($compras as $c)
                                <tr class="fila-compra transition hover:bg-sky-50/70"
                                    data-search="{{ strtolower($c->id . ' ' . $c->fecha_compra . ' ' . $c->no_factura . ' ' . $c->proveedor_nombre . ' ' . ($c->proyecto ?? '') . ' ' . number_format($c->total_factura, 2)) }}">

                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span class="inline-flex items-center rounded-xl bg-slate-100 px-3 py-1 text-xs font-black text-slate-700">
                                            #{{ $c->id }}
                                        </span>
                                    </td>

                                    <td class="px-4 py-3 whitespace-nowrap font-medium text-slate-700">
                                        {{ $c->fecha_compra }}
                                    </td>

                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span class="font-black text-slate-900">
                                            {{ $c->no_factura }}
                                        </span>
                                    </td>

                                    <td class="px-4 py-3">
                                        <div class="max-w-[330px]">
                                            <p class="truncate font-bold text-slate-800">
                                                {{ $c->proveedor_nombre }}
                                            </p>
                                        </div>
                                    </td>

                                    <td class="px-4 py-3 whitespace-nowrap">
                                        @if($c->proyecto)
                                            <span class="inline-flex rounded-full border border-blue-100 bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700">
                                                {{ $c->proyecto }}
                                            </span>
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </td>

                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        <span class="font-black text-slate-950">
                                            {{ number_format($c->total_factura, 2) }}
                                        </span>
                                    </td>

                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        <a href="{{ route($routePrefix . '.compras.show', $c->id) }}"
                                           class="inline-flex items-center justify-center rounded-xl border border-blue-200 bg-white px-4 py-2 text-xs font-bold text-blue-700 transition hover:border-blue-700 hover:bg-blue-700 hover:text-white">
                                            Ver
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr id="sinRegistrosOriginal">
                                    <td colspan="7" class="px-4 py-12 text-center">
                                        <div class="flex flex-col items-center">
                                            <div class="mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-sky-50 text-2xl">
                                                🧾
                                            </div>
                                            <p class="text-base font-bold text-slate-800">
                                                No hay compras registradas
                                            </p>
                                            <p class="mt-1 text-sm text-slate-500">
                                                Cuando registres una compra aparecerá en este listado.
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse

                            <tr id="sinResultados" class="hidden">
                                <td colspan="7" class="px-4 py-12 text-center">
                                    <div class="flex flex-col items-center">
                                        <div class="mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-sky-50 text-2xl">
                                            🔍
                                        </div>
                                        <p class="text-base font-bold text-slate-800">
                                            No se encontraron resultados
                                        </p>
                                        <p class="mt-1 text-sm text-slate-500">
                                            Intenta buscar por otro dato de la compra.
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                {{-- FOOTER TABLA / PAGINACIÓN --}}
                <div class="flex flex-col gap-3 border-t border-slate-200 bg-sky-50/70 px-4 py-3 md:flex-row md:items-center md:justify-between">
                    <div class="text-sm text-slate-500">
                        Mostrando
                        <span id="desdeRegistro" class="font-bold text-slate-800">0</span>
                        -
                        <span id="hastaRegistro" class="font-bold text-slate-800">0</span>
                        de
                        <span id="totalRegistros" class="font-bold text-slate-800">0</span>
                        compras
                    </div>

                    <div class="flex items-center justify-end gap-2">
                        <button type="button" id="btnAnterior"
                            class="rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm font-bold text-slate-600 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40">
                            Anterior
                        </button>

                        <div id="numerosPagina" class="flex items-center gap-1"></div>

                        <button type="button" id="btnSiguiente"
                            class="rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm font-bold text-slate-600 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40">
                            Siguiente
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>


{{-- -------- MODAL NUEVA COMPRA -------- --}}
<div id="modalCompra" class="fixed inset-0 z-50 hidden">

    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="cerrarModal()"></div>

    <div class="relative mx-auto mt-6 w-[95%] max-w-5xl">
        <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-2xl">

            <div class="flex items-center justify-between border-b border-slate-200 bg-sky-50 px-6 py-4">
                <div>
                    <h2 class="text-lg font-black text-slate-900">
                        Registrar compra
                    </h2>
                    <p class="text-sm text-slate-500">
                        Ingresa los datos de la factura y productos.
                    </p>
                </div>

                <button onclick="cerrarModal()"
                    type="button"
                    class="h-10 w-10 rounded-2xl border border-slate-200 bg-white text-2xl leading-none text-slate-500 transition hover:bg-slate-100 hover:text-slate-900">
                    &times;
                </button>
            </div>

            <div class="max-h-[80vh] overflow-y-auto bg-white p-6">
                @include('admin.compras._form')
            </div>

        </div>
    </div>

</div>


{{-- -------- SCRIPT MODAL + BÚSQUEDA + PAGINACIÓN -------- --}}
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

            btn.className = i === paginaActual
                ? 'h-9 min-w-9 rounded-xl bg-blue-700 px-3 text-sm font-black text-white shadow-sm'
                : 'h-9 min-w-9 rounded-xl border border-slate-300 bg-white px-3 text-sm font-bold text-slate-600 transition hover:bg-slate-100';

            btn.addEventListener('click', function () {
                paginaActual = i;
                renderTabla();
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
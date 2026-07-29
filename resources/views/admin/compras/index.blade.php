@extends((int) auth()->user()->role_id === 2 ? 'layouts.operador' : 'layouts.admin')

@section('title', 'Compras')

@section('content')
@php
    $routePrefix = (int) auth()->user()->role_id === 2 ? 'operador' : 'admin';
@endphp

<div class="mx-auto w-full max-w-7xl">

    @if(session('success'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">
            {{ session('error') }}
        </div>
    @endif

    <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">

        {{-- Encabezado --}}
        <header class="flex flex-col gap-4 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
            <div class="flex items-center gap-4">
                <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-blue-50 text-blue-700">
                    <svg viewBox="0 0 24 24" fill="currentColor" class="h-6 w-6">
                        <path fill-rule="evenodd"
                              d="M4.5 3.75A2.25 2.25 0 016.75 1.5h7.69c.597 0 1.17.237 1.591.659l3.31 3.31c.422.421.659.994.659 1.591v13.19a2.25 2.25 0 01-2.25 2.25h-11A2.25 2.25 0 014.5 20.25V3.75zM8.25 11.25a.75.75 0 000 1.5h7.5a.75.75 0 000-1.5h-7.5zm0 4a.75.75 0 000 1.5h7.5a.75.75 0 000-1.5h-7.5z"
                              clip-rule="evenodd"/>
                    </svg>
                </span>

                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight text-slate-950">
                        Compras
                    </h1>
                    <p class="mt-1 text-sm text-slate-500">
                        Registra facturas y genera entradas automáticas al inventario.
                    </p>
                </div>
            </div>

            <button type="button"
                    onclick="abrirModal()"
                    class="inline-flex items-center justify-center rounded-xl !border-blue-600 !bg-blue-600 px-5 py-2.5 text-sm font-extrabold !text-white shadow-sm hover:!bg-blue-700">
                + Nueva compra
            </button>
        </header>

        {{-- Herramientas --}}
        <div class="grid grid-cols-1 gap-3 border-t border-slate-200 p-4 sm:grid-cols-[minmax(0,1fr)_150px]">
            <input id="buscarCompra"
                   type="search"
                   placeholder="Buscar por ID, factura, proveedor, proyecto, fecha o total..."
                   class="w-full rounded-xl border-slate-300 px-4 py-2.5 text-sm">

            <select id="filasPorPagina"
                    class="w-full rounded-xl border-slate-300 px-4 py-2.5 text-sm font-semibold">
                <option value="10">10 filas</option>
                <option value="15">15 filas</option>
                <option value="25">25 filas</option>
                <option value="50">50 filas</option>
            </select>
        </div>

        {{-- Tabla --}}
        <div class="overflow-x-auto border-t border-slate-200">
            <table id="tablaCompras" class="w-full min-w-[900px] text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="w-20 px-4 py-3 text-left text-xs font-extrabold uppercase text-slate-500">ID</th>
                        <th class="w-36 px-4 py-3 text-left text-xs font-extrabold uppercase text-slate-500">Fecha</th>
                        <th class="w-44 px-4 py-3 text-left text-xs font-extrabold uppercase text-slate-500">Factura</th>
                        <th class="px-4 py-3 text-left text-xs font-extrabold uppercase text-slate-500">Proveedor</th>
                        <th class="w-32 px-4 py-3 text-left text-xs font-extrabold uppercase text-slate-500">Proyecto</th>
                        <th class="w-36 px-4 py-3 text-right text-xs font-extrabold uppercase text-slate-500">Total</th>
                        <th class="w-24 px-4 py-3 text-right text-xs font-extrabold uppercase text-slate-500">Acción</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">
                    @forelse($compras as $compra)
                        <tr class="fila-compra transition hover:bg-slate-50"
                            data-search="{{ strtolower(
                                $compra->id.' '.
                                $compra->fecha_compra.' '.
                                $compra->no_factura.' '.
                                $compra->proveedor_nombre.' '.
                                ($compra->proyecto ?? '').' '.
                                number_format($compra->total_factura, 2)
                            ) }}">

                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-extrabold text-slate-700">
                                    #{{ $compra->id }}
                                </span>
                            </td>

                            <td class="whitespace-nowrap px-4 py-3 font-medium text-slate-700">
                                {{ $compra->fecha_compra }}
                            </td>

                            <td class="whitespace-nowrap px-4 py-3 font-extrabold text-slate-900">
                                {{ $compra->no_factura }}
                            </td>

                            <td class="px-4 py-3">
                                <p class="max-w-sm truncate font-bold text-slate-800">
                                    {{ $compra->proveedor_nombre }}
                                </p>
                            </td>

                            <td class="px-4 py-3">
                                @if($compra->proyecto)
                                    <span class="inline-flex rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700">
                                        {{ $compra->proyecto }}
                                    </span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>

                            <td class="whitespace-nowrap px-4 py-3 text-right font-extrabold text-slate-950">
                                {{ number_format($compra->total_factura, 2) }}
                            </td>

                            <td class="px-4 py-3 text-right">
                                <a href="{{ route($routePrefix.'.compras.show', $compra->id) }}"
                                   class="inline-flex rounded-lg border border-blue-200 bg-white px-3 py-2 text-xs font-extrabold text-blue-700 hover:bg-blue-600 hover:text-white">
                                    Ver
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr id="sinRegistrosOriginal">
                            <td colspan="7" class="px-6 py-14 text-center">
                                <p class="font-extrabold text-slate-900">
                                    No hay compras registradas
                                </p>
                                <p class="mt-1 text-sm text-slate-500">
                                    Cuando registres una compra aparecerá aquí.
                                </p>
                            </td>
                        </tr>
                    @endforelse

                    <tr id="sinResultados" class="hidden">
                        <td colspan="7" class="px-6 py-14 text-center">
                            <p class="font-extrabold text-slate-900">
                                No se encontraron resultados
                            </p>
                            <p class="mt-1 text-sm text-slate-500">
                                Prueba buscando por otro dato.
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- Paginación local --}}
        <footer class="flex flex-col gap-3 border-t border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-slate-500">
                Mostrando
                <strong id="desdeRegistro" class="text-slate-900">0</strong>
                a
                <strong id="hastaRegistro" class="text-slate-900">0</strong>
                de
                <strong id="totalRegistros" class="text-slate-900">0</strong>
                compras
            </p>

            <div class="flex items-center gap-2">
                <button type="button" id="btnAnterior"
                        class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-bold text-slate-600 disabled:opacity-40">
                    Anterior
                </button>

                <div id="numerosPagina" class="flex items-center gap-1"></div>

                <button type="button" id="btnSiguiente"
                        class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-bold text-slate-600 disabled:opacity-40">
                    Siguiente
                </button>
            </div>
        </footer>

    </section>
</div>

{{-- Modal --}}
<div id="modalCompra" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm"
         onclick="cerrarModal()"></div>

    <div class="relative mx-auto mt-6 w-[95%] max-w-5xl">
        <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-2xl">
            <header class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                <div>
                    <h2 class="text-lg font-extrabold text-slate-950">
                        Registrar compra
                    </h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Ingresa los datos de la factura y los productos.
                    </p>
                </div>

                <button type="button"
                        onclick="cerrarModal()"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-xl text-slate-500 hover:bg-slate-100">
                    ×
                </button>
            </header>

            <div class="max-h-[80vh] overflow-y-auto p-6">
                @include('admin.compras._form')
            </div>
        </section>
    </div>
</div>

<script>
const modal = document.getElementById('modalCompra');

function abrirModal() {
    modal.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
}

function cerrarModal() {
    modal.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') cerrarModal();
});

document.addEventListener('DOMContentLoaded', () => {
    const search = document.getElementById('buscarCompra');
    const perPageSelect = document.getElementById('filasPorPagina');
    const rows = [...document.querySelectorAll('.fila-compra')];
    const noResults = document.getElementById('sinResultados');
    const previous = document.getElementById('btnAnterior');
    const next = document.getElementById('btnSiguiente');
    const numbers = document.getElementById('numerosPagina');
    const from = document.getElementById('desdeRegistro');
    const to = document.getElementById('hastaRegistro');
    const total = document.getElementById('totalRegistros');

    let page = 1;
    let perPage = Number(perPageSelect.value);

    const normalize = text => text.toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim();

    const filteredRows = () => {
        const value = normalize(search.value);
        return rows.filter(row => normalize(row.dataset.search || '').includes(value));
    };

    function render() {
        const filtered = filteredRows();
        const count = filtered.length;
        const pages = Math.max(1, Math.ceil(count / perPage));

        page = Math.min(page, pages);
        rows.forEach(row => row.classList.add('hidden'));

        const start = (page - 1) * perPage;
        const visible = filtered.slice(start, start + perPage);
        visible.forEach(row => row.classList.remove('hidden'));

        noResults.classList.toggle('hidden', count !== 0 || rows.length === 0);
        from.textContent = count ? start + 1 : 0;
        to.textContent = Math.min(start + perPage, count);
        total.textContent = count;
        previous.disabled = page === 1;
        next.disabled = page >= pages || count === 0;

        numbers.innerHTML = '';

        for (let i = 1; i <= pages && i <= 5; i++) {
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = i;
            button.className = i === page
                ? 'h-9 min-w-9 rounded-lg bg-blue-600 px-3 text-sm font-extrabold text-white'
                : 'h-9 min-w-9 rounded-lg border border-slate-200 bg-white px-3 text-sm font-bold text-slate-600';

            button.onclick = () => {
                page = i;
                render();
            };

            numbers.appendChild(button);
        }
    }

    search.addEventListener('input', () => {
        page = 1;
        render();
    });

    perPageSelect.addEventListener('change', () => {
        perPage = Number(perPageSelect.value);
        page = 1;
        render();
    });

    previous.onclick = () => {
        if (page > 1) {
            page--;
            render();
        }
    };

    next.onclick = () => {
        page++;
        render();
    };

    render();
});
</script>
@endsection
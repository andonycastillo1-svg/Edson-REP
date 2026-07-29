@extends((int) auth()->user()->role_id === 2 ? 'layouts.operador' : 'layouts.admin')

@section('title', 'Nueva solicitud de traslado')

@section('content')
@php
    $routePrefix = (int) auth()->user()->role_id === 2 ? 'operador' : 'admin';
    $esOperador = (int) auth()->user()->role_id === 2;
    $origenId = old(
        'bodega_origen_id',
        $origenId ?? ($esOperador ? auth()->user()->bodega_id : null)
    );
    $lineas = old('lineas', [['producto_codigo' => '', 'cantidad' => 1]]);
@endphp

<div class="mx-auto w-full max-w-5xl">

    @if($errors->any())
        <div class="mb-5 rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-700">
            <p class="font-extrabold">Revisa los siguientes datos:</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form
        method="POST"
        action="{{ route($routePrefix.'.operaciones.traslados.store') }}"
        enctype="multipart/form-data"
        class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm"
    >
        @csrf

        {{-- Encabezado --}}
        <header class="border-b border-slate-200 px-5 py-5 sm:px-7">
            <div class="flex items-center gap-4">
                <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-blue-600 text-white">
                    <svg viewBox="0 0 24 24" fill="currentColor" class="h-6 w-6">
                        <path d="M7.28 3.22a.75.75 0 011.06 1.06L6.62 6h10.63a.75.75 0 010 1.5H6.62l1.72 1.72a.75.75 0 11-1.06 1.06l-3-3a.75.75 0 010-1.06l3-3zM16.72 13.72a.75.75 0 011.06 0l3 3a.75.75 0 010 1.06l-3 3a.75.75 0 11-1.06-1.06L18.44 18H7.75a.75.75 0 010-1.5h10.69l-1.72-1.72a.75.75 0 010-1.06z"/>
                    </svg>
                </span>

                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight text-slate-950">
                        Nueva solicitud de traslado
                    </h1>
                    <p class="mt-1 text-sm text-slate-500">
                        La bodega de destino deberá aprobar o rechazar la solicitud.
                    </p>
                </div>
            </div>
        </header>

        {{-- Información general --}}
        <section class="grid grid-cols-1 gap-5 p-5 sm:p-7 md:grid-cols-2">
            <div>
                <label class="ui-label">Bodega de origen</label>

                @if($esOperador)
                    <input
                        type="hidden"
                        name="bodega_origen_id"
                        value="{{ auth()->user()->bodega_id }}"
                    >

                    <div class="rounded-xl border border-slate-200 bg-slate-100 px-4 py-2.5 text-sm font-semibold text-slate-700">
                        {{ optional($bodegas->firstWhere('id', auth()->user()->bodega_id))->nombre }}
                    </div>
                @else
                    <select
                        id="bodega_origen_id"
                        name="bodega_origen_id"
                        required
                        class="ui-input"
                    >
                        <option value="">Selecciona...</option>
                        @foreach($bodegas as $bodega)
                            <option
                                value="{{ $bodega->id }}"
                                @selected((string) $origenId === (string) $bodega->id)
                            >
                                {{ $bodega->nombre }}
                            </option>
                        @endforeach
                    </select>
                @endif
            </div>

            <div>
                <label class="ui-label">Bodega de destino</label>

                <select
                    id="bodega_destino_id"
                    name="bodega_destino_id"
                    required
                    class="ui-input"
                >
                    <option value="">Selecciona...</option>
                    @foreach($bodegas as $bodega)
                        @if((string) $bodega->id !== (string) $origenId)
                            <option
                                value="{{ $bodega->id }}"
                                @selected((string) old('bodega_destino_id') === (string) $bodega->id)
                            >
                                {{ $bodega->nombre }}
                            </option>
                        @endif
                    @endforeach
                </select>
            </div>

            <div>
                <label class="ui-label">Observación opcional</label>
                <textarea
                    name="observacion"
                    rows="3"
                    class="ui-input resize-y"
                    placeholder="Ejemplo: enviar con guía #123, producto frágil..."
                >{{ old('observacion') }}</textarea>
            </div>

            <div>
                <label class="ui-label">Destinatarios opcionales</label>

                <input
                    type="file"
                    name="archivo_excel"
                    accept=".xlsx,.xls,.csv"
                    class="block w-full rounded-xl border border-slate-200 bg-white text-sm text-slate-600
                           file:mr-4 file:border-0 file:border-r file:border-slate-200
                           file:bg-slate-50 file:px-4 file:py-2.5 file:text-sm
                           file:font-bold file:text-slate-700 hover:file:bg-slate-100"
                >

                <p class="mt-2 text-xs leading-5 text-slate-500">
                    Formatos permitidos: XLSX, XLS o CSV.
                </p>
            </div>
        </section>

        {{-- Productos --}}
        <section class="border-t border-slate-200">
            <div class="flex items-center justify-between px-5 py-4 sm:px-7">
                <div>
                    <h2 class="font-extrabold text-slate-950">Productos</h2>
                    <p class="mt-1 text-xs text-slate-500">
                        Agrega los productos y cantidades que serán trasladados.
                    </p>
                </div>

                <button
                    type="button"
                    id="btnAddLine"
                    class="inline-flex items-center justify-center rounded-xl border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-extrabold text-blue-700 hover:bg-blue-100"
                >
                    + Agregar producto
                </button>
            </div>

            <div class="overflow-x-auto border-t border-slate-200">
                <table class="w-full min-w-[680px] text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-extrabold uppercase text-slate-500">
                                Producto
                            </th>
                            <th class="w-40 px-5 py-3 text-left text-xs font-extrabold uppercase text-slate-500">
                                Cantidad
                            </th>
                            <th class="w-20 px-5 py-3"></th>
                        </tr>
                    </thead>

                    <tbody id="linesBody" class="divide-y divide-slate-100">
                        @foreach($lineas as $i => $linea)
                            <tr class="line-row">
                                <td class="px-5 py-4">
                                    <select
                                        required
                                        name="lineas[{{ $i }}][producto_codigo]"
                                        data-searchable="true"
                                        data-search-placeholder="Buscar producto..."
                                        class="ui-input"
                                    >
                                        <option value="">Selecciona...</option>

                                        @foreach($productos as $producto)
                                            <option
                                                value="{{ $producto->codigo }}"
                                                @selected(($linea['producto_codigo'] ?? '') === $producto->codigo)
                                            >
                                                {{ $producto->descripcion ?: $producto->nombre }}
                                                — {{ $producto->codigo }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>

                                <td class="px-5 py-4">
                                    <input
                                        type="number"
                                        min="1"
                                        required
                                        name="lineas[{{ $i }}][cantidad]"
                                        value="{{ $linea['cantidad'] ?? 1 }}"
                                        class="ui-input"
                                    >
                                </td>

                                <td class="px-5 py-4 text-right">
                                    <button
                                        type="button"
                                        class="btnRemove inline-flex h-10 w-10 items-center justify-center rounded-xl border border-rose-200 bg-rose-50 font-bold text-rose-600 hover:bg-rose-100"
                                        aria-label="Eliminar producto"
                                    >
                                        ×
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Acciones --}}
        <footer class="flex flex-col-reverse gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4 sm:flex-row sm:justify-end sm:px-7">
            <a
                href="{{ route($routePrefix.'.operaciones.traslados.index') }}"
                class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-extrabold text-slate-700 hover:bg-slate-100"
            >
                Cancelar
            </a>

            <button
                type="submit"
                class="inline-flex items-center justify-center rounded-xl !border-blue-600 !bg-blue-600 px-5 py-2.5 text-sm font-extrabold !text-white hover:!bg-blue-700"
            >
                Crear solicitud
            </button>
        </footer>
    </form>
</div>

<template id="lineTemplate">
    <tr class="line-row">
        <td class="px-5 py-4">
            <select
                required
                data-searchable="true"
                data-search-placeholder="Buscar producto..."
                class="ui-input"
            >
                <option value="">Selecciona...</option>
                @foreach($productos as $producto)
                    <option value="{{ $producto->codigo }}">
                        {{ $producto->descripcion ?: $producto->nombre }}
                        — {{ $producto->codigo }}
                    </option>
                @endforeach
            </select>
        </td>

        <td class="px-5 py-4">
            <input type="number" min="1" value="1" required class="ui-input">
        </td>

        <td class="px-5 py-4 text-right">
            <button
                type="button"
                class="btnRemove inline-flex h-10 w-10 items-center justify-center rounded-xl border border-rose-200 bg-rose-50 font-bold text-rose-600 hover:bg-rose-100"
            >
                ×
            </button>
        </td>
    </tr>
</template>

<script>
(() => {
    const body = document.getElementById('linesBody');
    const template = document.getElementById('lineTemplate');
    const addButton = document.getElementById('btnAddLine');
    const origin = document.getElementById('bodega_origen_id');
    const destination = document.getElementById('bodega_destino_id');

    const reindex = () => {
        body.querySelectorAll('.line-row').forEach((row, index) => {
            row.querySelector('select').name = `lineas[${index}][producto_codigo]`;
            row.querySelector('input[type="number"]').name = `lineas[${index}][cantidad]`;
        });
    };

    const bindRemove = row => {
        row.querySelector('.btnRemove').addEventListener('click', () => {
            if (body.querySelectorAll('.line-row').length === 1) return;
            row.remove();
            reindex();
        });
    };

    body.querySelectorAll('.line-row').forEach(bindRemove);

    addButton.addEventListener('click', () => {
        const row = template.content.cloneNode(true).querySelector('.line-row');
        body.appendChild(row);
        bindRemove(row);
        reindex();

        const select = row.querySelector('[data-searchable="true"]');
        window.enhanceSearchableSelect?.(select);
    });

    const syncDestination = () => {
        if (!origin || !destination) return;

        [...destination.options].forEach(option => {
            if (!option.value) return;
            option.disabled = option.value === origin.value;
            option.hidden = option.value === origin.value;
        });

        if (destination.value === origin.value) destination.value = '';
    };

    origin?.addEventListener('change', syncDestination);
    syncDestination();
    reindex();
})();
</script>
@endsection
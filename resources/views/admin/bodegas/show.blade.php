@extends('layouts.app')

@section('title', 'Inventario')

@section('content')
@php
    $prefijo = auth()->user()->rolOperativo() === 2 ? 'operador' : 'admin';
    $ruta = $prefijo.'.bodegas';
@endphp
<div class="space-y-4">
    <x-ui.encabezado :titulo="'Inventario de '.($bodega->nombre ?? 'la bodega')" :descripcion="implode(' · ', array_filter([$bodega->tipo, $bodega->ubicacion]))">
        <x-ui.accion :href="route($ruta.'.index')">Volver a bodegas</x-ui.accion>
        @rutaDisponible($ruta.'.inventario.export')
            <x-ui.accion :href="route($ruta.'.inventario.export', [$bodega->id] + request()->query())" icono="archivo">Descargar Excel</x-ui.accion>
        @endrutaDisponible
        @rutaDisponible('admin.bodegas.entrada')
            <x-ui.accion :href="route('admin.bodegas.entrada', $bodega->id)" variante="primaria">Agregar al inventario</x-ui.accion>
        @endrutaDisponible
    </x-ui.encabezado>

    <x-sistema.enlace-datos modulo="inventario" />
    <dl class="grid gap-3 sm:grid-cols-3">
        <div class="ui-card p-4"><dt class="text-sm text-muted">Productos</dt><dd class="mt-1 text-xl font-semibold tabular-nums">{{ number_format($productosTotal ?? 0) }}</dd></div>
        <div class="ui-card p-4"><dt class="text-sm text-muted">Unidades en la condición elegida</dt><dd class="mt-1 text-xl font-semibold tabular-nums">{{ number_format($stockTotal ?? 0) }}</dd></div>
        <div class="ui-card p-4"><dt class="text-sm text-muted">Valor al último costo de compra</dt><dd class="mt-1 text-xl font-semibold tabular-nums">Q {{ number_format((float) ($costoTotalInventario ?? 0), 2) }}</dd></div>
    </dl>

    <form method="GET" class="ui-filtros sm:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_auto] sm:items-end">
        <x-sistema.conservar-filtros-campos :tabla="$tablaCampos" />
        <label class="ui-label mb-0">Buscar producto
            <input type="search" id="inventarioSearch" name="q" value="{{ request()->query('q') }}" class="ui-input mt-1"
                   placeholder="Código, producto, descripción o unidad">
        </label>
        <label class="ui-label mb-0">Condición del inventario
            <select name="stock_tipo" class="ui-select mt-1">
                @foreach(['' => 'Disponible', 'nuevo' => 'Nuevo', 'usado' => 'Usado', 'danado' => 'Dañado', 'perdido' => 'Perdido', 'baja' => 'Baja'] as $tipo => $label)
                    <option value="{{ $tipo }}" @selected(request()->query('stock_tipo', '') === $tipo)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <div class="flex flex-wrap gap-2">
            <x-ui.accion type="submit" variante="primaria">Filtrar</x-ui.accion>
            <x-ui.accion :href="route($ruta.'.show', $bodega->id)">Limpiar</x-ui.accion>
        </div>
    </form>
    <x-sistema.consulta-campos :tabla="$tablaCampos" :conservar="['q', 'stock_tipo']" />

    <x-sistema.panel titulo="Productos en inventario">
        <p class="mb-3 text-sm" id="inventarioCounter" role="status">{{ number_format($inventarios->count()) }} productos en pantalla</p>
        <div class="ui-table-scroll" tabindex="0" role="region" aria-label="Productos en inventario">
        <table class="ui-table" id="inventarioTable">
            <thead>
                <tr>
                    <th scope="col">Código</th>
                    <th scope="col">Producto</th>
                    <th scope="col">Categoría</th>
                    <th scope="col" class="text-right">Cantidad</th>
                    <th scope="col" class="text-right">Vida útil</th>
                    <th scope="col" class="text-right">Costo unitario</th>
                    <th scope="col" class="text-right">Costo total</th>
                    <x-sistema.cabeceras-campos :tabla="$tablaCampos" />
                </tr>
            </thead>

            <tbody>
                @forelse($inventarios as $inv)
                    <tr class="inv-row">
                        <td>
                            <span class="font-semibold text-ink">{{ $inv->producto_codigo }}</span>
                        </td>

                        <td>
                            <div class="font-semibold text-ink">
                                {{ $inv->nombre ?? '—' }}
                            </div>

                            @if(!empty($inv->descripcion))
                                <div class="mt-1 max-w-sm text-sm text-muted" title="{{ $inv->descripcion }}">
                                    {{ $inv->descripcion }}
                                </div>
                            @else
                                <div class="mt-1 text-sm text-muted">
                                    Sin descripción
                                </div>
                            @endif
                        </td>

                        <td>
                            {{ $inv->categoria ?? '—' }}
                        </td>

                        <td class="text-right">
                            <span class="font-semibold tabular-nums text-ink">
                                {{ number_format($inv->cantidad ?? 0) }}
                            </span>
                        </td>

                        <td class="text-right">
                            @if(is_null($inv->vida_util_meses))
                                <span class="text-muted">—</span>
                            @else
                                <span class="whitespace-nowrap">
                                    {{ number_format($inv->vida_util_meses) }} meses
                                </span>
                            @endif
                        </td>

                        <td class="text-right">
                            <span class="whitespace-nowrap tabular-nums">
                                Q {{ number_format((float)($inv->costo_unitario ?? 0), 2) }}
                            </span>
                        </td>

                        <td class="text-right">
                            <span class="whitespace-nowrap tabular-nums">
                                Q {{ number_format((float)($inv->costo_total ?? 0), 2) }}
                            </span>
                        </td>
                        <x-sistema.celdas-campos :tabla="$tablaCampos" :registro="$inv->producto_codigo" />
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ 7 + $tablaCampos['campos']->count() }}">
                            <div class="ui-empty-state">
                                <div class="font-semibold text-ink">
                                    Esta bodega aún no tiene inventario.
                                </div>
                                <div>
                                    Cuando agregues productos, aparecerán en esta tabla.
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforelse

                <tr id="noSearchResults" style="display:none;">
                    <td colspan="{{ 7 + $tablaCampos['campos']->count() }}">
                        <div class="ui-empty-state">
                            <div class="font-semibold text-ink">
                                No se encontraron resultados.
                            </div>
                            <div>
                                Prueba buscando por otro código, producto o descripción.
                            </div>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
        </div>
        {{ $inventarios->links() }}
    </x-sistema.panel>
</div>

<script>
    const inventarioSearch = document.getElementById('inventarioSearch');
    const inventarioTable = document.getElementById('inventarioTable');
    const inventarioCounter = document.getElementById('inventarioCounter');
    const noSearchResults = document.getElementById('noSearchResults');

    if (inventarioSearch && inventarioTable) {
        inventarioSearch.addEventListener('input', function () {
            const search = this.value.toLowerCase().trim();
            const rows = inventarioTable.querySelectorAll('tbody tr.inv-row');
            let visible = 0;

            rows.forEach((row) => {
                const text = row.innerText.toLowerCase();
                const match = text.includes(search);

                row.style.display = match ? '' : 'none';

                if (match) {
                    visible++;
                }
            });

            if (inventarioCounter) {
                inventarioCounter.textContent = visible + ' productos en pantalla';
            }

            if (noSearchResults) {
                noSearchResults.style.display = visible === 0 && rows.length > 0 ? '' : 'none';
            }
        });
    }
</script>

@endsection

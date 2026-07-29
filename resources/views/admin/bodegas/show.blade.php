@extends((int) auth()->user()->role_id === 2 ? 'layouts.operador' : 'layouts.admin')

@section('title', 'Inventario')

@section('content')
@php
    $esOperador = (int) auth()->user()->role_id === 2;
    $routePrefix = $esOperador ? 'operador' : 'admin';
@endphp

<div class="mx-auto w-full max-w-7xl overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">

    {{-- Encabezado --}}
    <header class="border-b border-slate-200 p-5 sm:p-6">
        <div class="flex flex-col gap-5 xl:flex-row xl:items-center xl:justify-between">

            <div class="flex items-center gap-4">
                <span class="inline-flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-blue-600 text-white shadow-md">
                    <svg viewBox="0 0 24 24" fill="currentColor" class="h-7 w-7">
                        <path d="M12 2.25l8.25 4.5v10.5L12 21.75l-8.25-4.5V6.75L12 2.25zm0 1.71L6.06 7.2 12 10.44l5.94-3.24L12 3.96zm-6.75 4.5v7.9l6 3.27v-7.9l-6-3.27zm7.5 11.17l6-3.27v-7.9l-6 3.27v7.9z"/>
                    </svg>
                </span>

                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight text-slate-950 sm:text-3xl">
                        Inventario — {{ $bodega->nombre ?? 'Bodega #'.$bodega->id }}
                    </h1>

                    <p class="mt-1 text-sm font-medium text-slate-500">
                        Tipo:
                        <strong class="text-slate-700">
                            {{ $bodega->tipo ?? '—' }}
                        </strong>
                        · Ubicación:
                        <strong class="text-slate-700">
                            {{ $bodega->ubicacion ?? '—' }}
                        </strong>
                    </p>
                </div>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="rounded-xl bg-slate-50 px-4 py-2.5">
                    <p class="text-xs font-bold text-slate-500">
                        Costo total del inventario
                    </p>

                    <p class="mt-1 text-xl font-extrabold text-slate-950">
                        Q {{ number_format((float) ($costoTotalInventario ?? 0), 2) }}
                    </p>
                </div>

                <a
                    href="{{ route(
                        $routePrefix.'.bodegas.inventario.export',
                        array_merge([$bodega->id], request()->query())
                    ) }}"
                    class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-extrabold text-slate-700 shadow-sm transition hover:bg-slate-50"
                >
                    Descargar Excel
                </a>

                <a
                    href="{{ route('admin.bodegas.entrada', $bodega->id) }}"
                    class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-extrabold text-white shadow-sm transition hover:bg-blue-700"
                >
                    + Agregar al inventario
                </a>
            </div>

        </div>
    </header>

    {{-- Búsqueda y filtro --}}
    <form
        method="GET"
        class="grid grid-cols-1 gap-3 border-b border-slate-200 p-4 lg:grid-cols-[minmax(280px,1fr)_240px_auto_auto]"
    >
        <input
            id="inventarioSearch"
            type="search"
            placeholder="Buscar por código, producto o descripción..."
            class="w-full rounded-xl border-slate-300 px-4 py-2.5 text-sm"
        >

        <select
            name="stock_tipo"
            class="w-full rounded-xl border-slate-300 px-4 py-2.5 text-sm"
        >
            <option value="">Todos los estados</option>
            <option value="nuevo" @selected(request('stock_tipo') === 'nuevo')>
                Stock nuevo
            </option>
            <option value="usado" @selected(request('stock_tipo') === 'usado')>
                Usado reutilizable
            </option>
            <option value="danado" @selected(request('stock_tipo') === 'danado')>
                Dañado/no reutilizable
            </option>
            <option value="descuento" @selected(request('stock_tipo') === 'descuento')>
                Pendiente de descuento
            </option>
        </select>

        <button
            type="submit"
            class="inline-flex items-center justify-center rounded-xl !border-blue-600 !bg-blue-600 px-5 py-2.5 text-sm font-extrabold !text-white hover:!bg-blue-700"
        >
            Filtrar
        </button>

        <a
            href="{{ route($routePrefix.'.bodegas.show', $bodega->id) }}"
            class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-extrabold text-slate-700 hover:bg-slate-50"
        >
            Limpiar
        </a>
    </form>

    {{-- Tabla --}}
    <div class="overflow-x-auto lg:overflow-visible">
        <table
            id="inventarioTable"
            class="w-full min-w-[900px] table-fixed text-[11px] lg:min-w-0"
        >
            <colgroup>
                <col class="w-[12%]">
                <col class="w-[27%]">
                <col class="w-[9%]">
                <col class="w-[16%]">
                <col class="w-[8%]">
                <col class="w-[8%]">
                <col class="w-[10%]">
                <col class="w-[10%]">
            </colgroup>

            <thead class="border-b border-slate-200 bg-white">
                <tr>
                    <th class="px-3 py-3 text-left text-[10px] font-extrabold uppercase text-slate-500">
                        Código
                    </th>
                    <th class="px-3 py-3 text-left text-[10px] font-extrabold uppercase text-slate-500">
                        Producto
                    </th>
                    <th class="px-3 py-3 text-left text-[10px] font-extrabold uppercase text-slate-500">
                        Categoría
                    </th>
                    <th class="px-3 py-3 text-left text-[10px] font-extrabold uppercase text-slate-500">
                        Disponibilidad
                    </th>
                    <th class="px-3 py-3 text-right text-[10px] font-extrabold uppercase text-slate-500">
                        Cantidad
                    </th>
                    <th class="px-3 py-3 text-right text-[10px] font-extrabold uppercase text-slate-500">
                        Vida útil
                    </th>
                    <th class="px-3 py-3 text-right text-[10px] font-extrabold uppercase text-slate-500">
                        Costo unitario
                    </th>
                    <th class="px-3 py-3 text-right text-[10px] font-extrabold uppercase text-slate-500">
                        Costo total
                    </th>
                </tr>
            </thead>

            <tbody class="divide-y divide-slate-100">
                @forelse($inventarios as $inv)
                    <tr class="inv-row transition hover:bg-slate-50">

                        <td class="break-words px-3 py-3 font-extrabold text-slate-900">
                            {{ $inv->producto_codigo }}
                        </td>

                        <td class="px-3 py-3">
                            <p class="break-words text-xs font-extrabold leading-4 text-slate-900">
                                {{ $inv->nombre ?? '—' }}
                            </p>

                            <p class="mt-1 line-clamp-2 text-[10px] leading-4 text-slate-500">
                                {{ $inv->descripcion ?: 'Sin descripción' }}
                            </p>
                        </td>

                        <td class="break-words px-3 py-3 text-slate-600">
                            {{ $inv->categoria ?? '—' }}
                        </td>

                        <td class="px-3 py-3 leading-4 text-slate-700">
                            <div>
                                <strong>Nuevos:</strong>
                                {{ (int) $inv->nuevos_disponibles }}
                            </div>

                            <div>
                                <strong>Usados:</strong>
                                {{ (int) $inv->usados_disponibles }}
                            </div>

                            <div>
                                <strong>Dañados:</strong>
                                {{ (int) $inv->danados }}
                            </div>

                            @if((int) $inv->perdidos > 0)
                                <div>
                                    <strong>Perdidos:</strong>
                                    {{ (int) $inv->perdidos }}
                                </div>
                            @endif

                            @if((int) $inv->bajas > 0)
                                <div>
                                    <strong>Bajas:</strong>
                                    {{ (int) $inv->bajas }}
                                </div>
                            @endif

                            @if((int) $inv->usados_disponibles > 0)
                                <p class="mt-1 text-[10px] text-slate-500">
                                    Menor vida usada:
                                    {{ is_null($inv->vida_util_restante_meses)
                                        ? 'No aplica'
                                        : $inv->vida_util_restante_meses.' meses' }}
                                </p>
                            @endif
                        </td>

                        <td class="px-3 py-3 text-right">
                            <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 font-extrabold text-slate-900">
                                {{ number_format($inv->cantidad ?? 0) }}
                            </span>
                        </td>

                        <td class="px-3 py-3 text-right font-bold leading-4 text-slate-700">
                            {{ is_null($inv->vida_util_meses)
                                ? '—'
                                : number_format($inv->vida_util_meses).' meses' }}
                        </td>

                        <td class="px-3 py-3 text-right font-extrabold leading-4 text-slate-900">
                            Q {{ number_format((float) ($inv->costo_unitario ?? 0), 2) }}
                        </td>

                        <td class="px-3 py-3 text-right font-extrabold leading-4 text-slate-900">
                            Q {{ number_format((float) ($inv->costo_total ?? 0), 2) }}
                        </td>

                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-6 py-14 text-center">
                            <p class="font-extrabold text-slate-900">
                                Esta bodega aún no tiene inventario.
                            </p>

                            <p class="mt-1 text-sm text-slate-500">
                                Cuando agregues productos aparecerán aquí.
                            </p>
                        </td>
                    </tr>
                @endforelse

                <tr id="noSearchResults" class="hidden">
                    <td colspan="8" class="px-6 py-14 text-center">
                        <p class="font-extrabold text-slate-900">
                            No se encontraron resultados.
                        </p>

                        <p class="mt-1 text-sm text-slate-500">
                            Prueba buscando otro código, producto o descripción.
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- Paginación --}}
    @if(method_exists($inventarios, 'links'))
        <footer class="flex flex-col gap-3 border-t border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm font-medium text-slate-500">
                Mostrando
                <strong class="text-slate-900">
                    {{ $inventarios->firstItem() ?? 0 }}
                </strong>
                a
                <strong class="text-slate-900">
                    {{ $inventarios->lastItem() ?? 0 }}
                </strong>
                de
                <strong class="text-slate-900">
                    {{ $inventarios->total() }}
                </strong>
                resultados
            </p>

            {{ $inventarios->withQueryString()->links() }}
        </footer>
    @endif

</div>

<script>
document.getElementById('inventarioSearch')?.addEventListener('input', function () {
    const rows = document.querySelectorAll('#inventarioTable .inv-row');
    const search = this.value.toLowerCase().trim();
    let visible = 0;

    rows.forEach(row => {
        const match = row.innerText.toLowerCase().includes(search);
        row.style.display = match ? '' : 'none';

        if (match) visible++;
    });

    document.getElementById('noSearchResults')?.classList.toggle(
        'hidden',
        visible > 0 || rows.length === 0
    );
});
</script>
@endsection
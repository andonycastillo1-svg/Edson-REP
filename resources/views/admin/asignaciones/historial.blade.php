@extends('layouts.app')

@section('title', 'Historial de movimientos')
@section('content')
<div class="space-y-4">
    <x-ui.encabezado titulo="Historial de movimientos">
        <x-ui.accion :href="route($routePrefix.'.asignaciones.index')">Volver a asignaciones</x-ui.accion>
    </x-ui.encabezado>

    <form method="GET" class="ui-filtros sm:grid-cols-2 xl:grid-cols-4 xl:items-end">
        <label class="ui-label mb-0">Fecha inicial<input class="ui-input mt-1" type="date" name="desde" value="{{ request('desde') }}"></label>
        <label class="ui-label mb-0">Fecha final<input class="ui-input mt-1" type="date" name="hasta" value="{{ request('hasta') }}"></label>
        <label class="ui-label mb-0">Colaborador<input class="ui-input mt-1" type="search" name="colaborador" value="{{ request('colaborador') }}" placeholder="Nombre o código"></label>
        <label class="ui-label mb-0">Producto<input class="ui-input mt-1" type="search" name="producto" value="{{ request('producto') }}" placeholder="Nombre o código"></label>
        <label class="ui-label mb-0">Tipo de movimiento
            <select class="ui-select mt-1" name="tipo">
                <option value="">Todos</option>
                @foreach($tipos as $tipo)
                    <option value="{{ $tipo }}" @selected(request('tipo') === $tipo)>{{ ['Asignacion' => 'Asignación', 'Devolucion' => 'Devolución'][$tipo] ?? $tipo }}</option>
                @endforeach
            </select>
        </label>
        <label class="ui-label mb-0">Bodega
            <select class="ui-select mt-1" name="bodega">
                <option value="">Todas</option>
                @foreach($bodegas as $bodega)
                    <option value="{{ $bodega->id }}" @selected((string) request('bodega') === (string) $bodega->id)>{{ $bodega->nombre }}</option>
                @endforeach
            </select>
        </label>
        @include('admin.asignaciones.tamano-pagina', ['tamano' => $movimientos->perPage()])
        <div class="flex flex-wrap gap-2">
            <x-ui.accion type="submit" variante="primaria">Filtrar</x-ui.accion>
            <x-ui.accion :href="route($routePrefix.'.asignaciones.historial')">Limpiar</x-ui.accion>
        </div>
    </form>

    <x-sistema.panel titulo="Movimientos registrados">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm">{{ $movimientos->total() }} movimientos</p>
            <x-ui.exportar-excel modulo="asignaciones" texto="Exportar devoluciones" />
        </div>
        <div class="ui-table-scroll" role="region" aria-label="Historial de movimientos" tabindex="0">
            <table class="ui-table">
                <thead><tr><th scope="col">Fecha y hora</th><th scope="col">Movimiento</th><th scope="col">Colaborador</th><th scope="col">Producto</th><th scope="col">Cantidad</th><th scope="col">Responsable y bodega</th><th scope="col">Documentación</th></tr></thead>
                <tbody>
                    @php($documentosMostrados = [])
                    @forelse($movimientos as $fila)
                        @php($asignacion = $fila->asignacion)
                        @php($grupo = $fila->grupo_devolucion)
                        <tr>
                            <td>{{ ($fila->ocurrio_en ?? $fila->created_at)->format('d/m/Y H:i') }}</td>
                            <td>{{ ['Asignacion' => 'Asignación', 'Devolucion' => 'Devolución'][$fila->tipo] ?? $fila->tipo }}</td>
                            <td><strong>{{ $asignacion->colaborador?->nombre ?? $asignacion->colaborador_codigo }}</strong><p>{{ $asignacion->colaborador_codigo }}</p></td>
                            <td>{{ $asignacion->producto?->nombre ?? $asignacion->producto_codigo }}</td>
                            <td>{{ $fila->cantidad }}</td>
                            <td>{{ $fila->user?->name ?? '—' }}<p>{{ ($fila->bodegaRetorno ?? $asignacion->bodega)?->nombre ?? '—' }}</p></td>
                            <td>
                                @if($grupo && isset($gruposDevolucion[$grupo]) && !isset($documentosMostrados[$grupo]))
                                    @php($documentosMostrados[$grupo] = true)
                                    @include('admin.asignaciones.documentos-devolucion', ['accesos' => $gruposDevolucion[$grupo], 'firmado' => $pdfsDevolucionFirmados->get($grupo)])
                                @else
                                    <a class="flujo-enlace" href="{{ route($routePrefix.'.asignaciones.articulo', $asignacion) }}">Ver artículo</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><p class="ui-empty-state">No hay movimientos que coincidan con los filtros.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $movimientos->links() }}
    </x-sistema.panel>
</div>
@endsection

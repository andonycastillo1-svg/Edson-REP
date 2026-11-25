@extends('layouts.app')

@section('title', 'Asignaciones del colaborador')
@section('content')
<div class="space-y-4">
    <x-ui.encabezado :titulo="$colaborador->nombre" :descripcion="$colaborador->codigo.' · '.$colaborador->puesto">
        <x-ui.accion :href="route($routePrefix.'.asignaciones.index')">Volver a asignaciones</x-ui.accion>
        @if($puedeDevolver)
            <x-ui.accion :href="route('devoluciones.formulario', $colaborador->codigo)" variante="primaria">Registrar devolución</x-ui.accion>
        @endif
    </x-ui.encabezado>
    <x-sistema.panel titulo="Productos asignados">
        <form method="GET" class="mb-4 flex flex-wrap items-end justify-between gap-3">
            <p class="text-sm">{{ trans_choice('{1} :count asignación|[0,*] :count asignaciones', $asignaciones->total()) }}</p>
            <div class="flex items-end gap-2">
                @include('admin.asignaciones.tamano-pagina', ['tamano' => $asignaciones->perPage()])
                <x-ui.accion type="submit">Aplicar</x-ui.accion>
            </div>
        </form>
        <div class="ui-table-scroll" role="region" aria-label="Productos asignados" tabindex="0">
            <table class="ui-table">
                <thead><tr><th scope="col">Producto</th><th scope="col">Fecha de entrega</th><th scope="col">Cantidad</th><th scope="col">Vida útil</th><th scope="col">Estado</th><th scope="col" class="text-right">Acción</th></tr></thead>
                <tbody>
                    @foreach($asignaciones as $fila)
                        <tr>
                            <td><strong class="text-ink">{{ $fila->producto?->nombre ?? $fila->producto_codigo }}</strong><p>{{ $fila->producto_codigo }}</p></td>
                            <td>{{ ($fila->entrega?->salida_en ?? $fila->fecha)?->format('d/m/Y') ?? '—' }}</td>
                            <td>{{ $fila->cantidad_asignada }}</td>
                            <td>@include('admin.asignaciones.vida-util', ['segundos' => $vigencias[$fila->id]])</td>
                            <td><x-ui.estado :variante="$fila->estado === 'Activa' && !$fila->sustituida_por_id ? 'exito' : 'neutra'">{{ $fila->custodia_etiqueta }}</x-ui.estado></td>
                            <td class="text-right"><x-ui.accion :href="route($routePrefix.'.asignaciones.articulo', $fila)">Ver detalle</x-ui.accion></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $asignaciones->links() }}
    </x-sistema.panel>
</div>
@endsection

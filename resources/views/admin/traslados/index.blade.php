@extends('layouts.app')

@section('title', 'Solicitudes de traslado')

@section('content')
@php($ruta = \App\Services\AccesoInventarioOperativo::prefijo(auth()->user()).'.operaciones.traslados')

<div class="space-y-4">
    <x-ui.encabezado titulo="Solicitudes de traslado" descripcion="Consulta los traslados relacionados con tus bodegas autorizadas.">
        <x-ui.exportar-excel modulo="traslados" />
        @rutaDisponible($ruta.'.create')
            <x-ui.accion variante="primaria" :href="route($ruta.'.create')">Nueva solicitud</x-ui.accion>
        @endrutaDisponible
    </x-ui.encabezado>
    <x-sistema.enlace-datos modulo="traslados" />
    <x-sistema.consulta-campos :tabla="$tablaCampos" :conservar="['estado', 'origen', 'destino']" />

    <form method="GET" class="ui-filtros sm:grid-cols-2 xl:grid-cols-[repeat(3,minmax(0,1fr))_auto] xl:items-end">
        <x-sistema.conservar-filtros-campos :tabla="$tablaCampos" />
        <label class="ui-label mb-0">Estado
            <select name="estado" class="ui-select mt-1">
                <option value="">Todos</option>
                @foreach(['PENDIENTE' => 'Pendiente', 'APROBADO' => 'Aprobado', 'RECHAZADO' => 'Rechazado'] as $valor => $etiqueta)
                    <option value="{{ $valor }}" @selected($estado === $valor)>{{ $etiqueta }}</option>
                @endforeach
            </select>
        </label>
        @foreach(['origen' => ['Origen', $origen], 'destino' => ['Destino', $destino]] as $nombre => [$etiqueta, $valor])
            <label class="ui-label mb-0">{{ $etiqueta }}
                <select name="{{ $nombre }}" class="ui-select mt-1">
                    <option value="">Todas las bodegas</option>
                    @foreach($bodegas as $bodega)
                        <option value="{{ $bodega->id }}" @selected((string) $valor === (string) $bodega->id)>{{ $bodega->nombre }}</option>
                    @endforeach
                </select>
            </label>
        @endforeach
        <div class="flex flex-wrap gap-2">
            <x-ui.accion type="submit" variante="primaria">Filtrar</x-ui.accion>
            <x-ui.accion :href="route($ruta.'.index')">Limpiar</x-ui.accion>
        </div>
    </form>

    <x-sistema.panel titulo="Listado de solicitudes">
        <div class="ui-table-scroll" tabindex="0" role="region" aria-label="Solicitudes de traslado">
            <table class="ui-table">
                <thead><tr>
                    <th scope="col">ID</th>
                    <th scope="col">Estado</th>
                    <th scope="col">Origen → destino</th>
                    <th scope="col">Creación</th>
                    <x-sistema.cabeceras-campos :tabla="$tablaCampos" />
                    <th scope="col" class="text-right">Acciones</th>
                </tr></thead>
                <tbody>
                    @forelse($operaciones as $op)
                        <tr>
                            <td class="whitespace-nowrap">#{{ $op->id }}</td>
                            <td>@include('admin.traslados.estado', ['estadoTraslado' => $op->estado])</td>
                            <td>{{ $op->bodegaOrigen?->nombre ?? '—' }} → {{ $op->bodegaDestino?->nombre ?? '—' }}</td>
                            <td>
                                <time class="whitespace-nowrap" datetime="{{ $op->created_at->toIso8601String() }}">{{ $op->created_at->format('d/m/Y H:i') }}</time>
                                <p class="mt-1 text-sm">{{ $op->creador?->name ?? '—' }}</p>
                            </td>
                            <x-sistema.celdas-campos :tabla="$tablaCampos" :registro="$op->id" />
                            <td class="text-right"><x-ui.accion :href="route($ruta.'.show', $op)">Ver solicitud</x-ui.accion></td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ 5 + $tablaCampos['campos']->count() }}"><p class="ui-empty-state">No hay solicitudes con estos filtros.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $operaciones->links() }}
    </x-sistema.panel>
</div>
@endsection

@extends('layouts.app')

@section('title', 'Asignaciones y devoluciones')
@section('content')
<div class="space-y-4">
    <x-ui.encabezado titulo="Asignaciones y devoluciones">
        @if($puedeVerMovimientos)
            <x-ui.accion :href="route($routePrefix.'.asignaciones.historial')">Historial de movimientos</x-ui.accion>
        @endif
        @if($puedeCrearAsignaciones)
            <x-ui.accion :href="route($routePrefix.'.asignaciones.create')" variante="primaria">Nueva asignación</x-ui.accion>
        @endif
    </x-ui.encabezado>
    <x-sistema.enlace-datos modulo="asignaciones" />

    <nav class="ui-status-tabs" aria-label="Seguimiento de entregas">
        <a class="flujo-tab" href="{{ route($routePrefix.'.asignaciones.index', request()->only(['q', 'campo', 'por_pagina'])) }}"
           @unless($seguimiento) aria-current="page" @endunless>Asignaciones</a>
        <a class="flujo-tab gap-2" href="{{ route($routePrefix.'.asignaciones.index', request()->only(['q', 'campo', 'por_pagina']) + ['seguimiento' => 'documentacion']) }}"
           @if($seguimiento) aria-current="page" @endif>
            Hojas firmadas faltantes
            @if($faltantes > 0)<x-ui.estado variante="pendiente">{{ $faltantes }}</x-ui.estado>@endif
        </a>
    </nav>

    <form method="GET" class="ui-filtros sm:grid-cols-[minmax(0,1fr)_auto_auto] sm:items-end">
        @if($seguimiento)<input type="hidden" name="seguimiento" value="documentacion">@endif
        <x-sistema.conservar-filtros-campos :tabla="$tablaCampos" />
        <label class="ui-label mb-0">Buscar colaborador
            <input class="ui-input mt-1" name="q" type="search" maxlength="190" value="{{ request('q') }}" placeholder="Nombre o código">
        </label>
        @include('admin.asignaciones.tamano-pagina', ['tamano' => $porPagina])
        <x-ui.accion type="submit" variante="primaria">Buscar</x-ui.accion>
    </form>
    <x-sistema.consulta-campos :tabla="$tablaCampos" :conservar="['q', 'seguimiento', 'por_pagina']" />

    <x-sistema.panel :titulo="$seguimiento ? 'Entregas pendientes de firma' : 'Colaboradores con asignaciones'">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm">{{ $seguimiento
                ? trans_choice('{1} :count entrega|[0,*] :count entregas', $entregas->total())
                : trans_choice('{1} :count colaborador|[0,*] :count colaboradores', $colaboradores->total()) }}</p>
            <x-ui.exportar-excel modulo="asignaciones" />
        </div>
        @if($seguimiento)
            @include('admin.asignaciones.pendientes')
            {{ $entregas->links() }}
        @else
            <div class="ui-table-scroll" role="region" aria-label="Colaboradores con asignaciones" tabindex="0">
                <table class="ui-table">
                    <thead><tr><th scope="col">Colaborador</th><th scope="col">Productos activos</th><th scope="col">Asignaciones</th><th scope="col" class="text-right">Acción</th></tr></thead>
                    <tbody>
                        @forelse($colaboradores as $persona)
                            <tr>
                                <td>
                                    <strong class="text-ink">{{ $persona->nombre }}</strong><p>{{ $persona->codigo }}</p>
                                </td>
                                <td>{{ $persona->cantidad_activa }}</td>
                                <td>{{ $persona->asignaciones_count }}</td>
                                <td class="text-right">
                                    <x-ui.accion :href="route($routePrefix.'.asignaciones.colaborador', $persona->codigo)">Ver detalle</x-ui.accion>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4"><p class="ui-empty-state">No hay colaboradores que coincidan con la búsqueda.</p></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $colaboradores->links() }}
        @endif
    </x-sistema.panel>
</div>
@endsection

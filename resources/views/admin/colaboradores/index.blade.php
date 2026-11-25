@extends('layouts.app')

@section('title', 'Colaboradores')

@section('content')
@php
    $routePrefix = auth()->user()->rolOperativo() === 4 ? 'rrhh' : 'admin';
    $ruta = $routePrefix.'.colaboradores';
    $puedeSeleccionar = app(\App\Sistema\Permisos::class)->ruta(auth()->user(), $ruta.'.fichas_tecnicas_masivas');
@endphp

<div x-data="colaboradoresPage()" class="space-y-4">
    <x-ui.encabezado titulo="Colaboradores" descripcion="Consulta expedientes y descarga fichas técnicas del personal.">
        @rutaDisponible($ruta.'.create')
            <x-ui.accion :href="route($ruta.'.create')" variante="primaria">Nuevo colaborador</x-ui.accion>
        @endrutaDisponible
    </x-ui.encabezado>
    <x-sistema.enlace-datos modulo="colaboradores" />
    <x-sistema.consulta-campos :tabla="$tablaCampos" :conservar="['q']" />

    <form method="GET" class="ui-filtros sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
        <x-sistema.conservar-filtros-campos :tabla="$tablaCampos" />
        <label class="ui-label mb-0">Buscar colaborador
            <input type="search" name="q" value="{{ request('q') }}" class="ui-input mt-1" placeholder="Código, nombre o puesto">
        </label>
        <div class="flex flex-wrap gap-2">
            <x-ui.accion type="submit" variante="primaria">Buscar</x-ui.accion>
            @if(request('q'))
                <x-ui.accion :href="route($ruta.'.index')">Limpiar búsqueda</x-ui.accion>
            @endif
        </div>
    </form>

    <x-sistema.panel titulo="Directorio de colaboradores" class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="ui-status-tabs" role="group" aria-label="Estado del colaborador">
                <button type="button" class="flujo-tab" @click="tab = 'activos'" :aria-pressed="tab === 'activos'">Activos ({{ $activos->total() }})</button>
                <button type="button" class="flujo-tab" @click="tab = 'inactivos'" :aria-pressed="tab === 'inactivos'">Inactivos ({{ $inactivos->total() }})</button>
            </div>
            <x-ui.exportar-excel modulo="colaboradores" :filtros="['estado' => 'Activo']" x-show="tab === 'activos'" />
            <x-ui.exportar-excel modulo="colaboradores" :filtros="['estado' => 'Inactivo']" x-show="tab === 'inactivos'" x-cloak />
        </div>

        @if($puedeSeleccionar)
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-line bg-subtle p-3">
                <p class="text-sm font-semibold" role="status" x-text="selectedCount() === 1 ? '1 colaborador seleccionado' : selectedCount() + ' colaboradores seleccionados'">0 colaboradores seleccionados</p>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="ui-btn-secondary" @click="seleccionarTodosVisibles(tab === 'activos' ? @js($activos->pluck('codigo')->values()) : @js($inactivos->pluck('codigo')->values()))">Seleccionar visibles</button>
                    <x-ui.accion @click="limpiarSeleccion()" x-bind:disabled="selectedCount() === 0">Limpiar selección</x-ui.accion>
                    <form method="POST" action="{{ route($ruta.'.fichas_tecnicas_masivas') }}" x-ref="formFichasSeleccionadas"
                          @submit.prevent="descargarSeleccionados($refs.formFichasSeleccionadas)">
                        @csrf
                        <template x-for="codigo in selectedCodigos" :key="'codigo-selected-' + codigo">
                            <input type="hidden" name="codigos[]" :value="codigo">
                        </template>
                        <x-ui.accion type="submit" icono="archivo" x-bind:disabled="selectedCount() === 0">Descargar fichas en Excel</x-ui.accion>
                    </form>
                </div>
            </div>
        @endif

        @foreach(['activos' => [$activos, true], 'inactivos' => [$inactivos, false]] as $tab => [$listado, $activo])
            <div x-show="tab === '{{ $tab }}'" @if(!$activo) x-cloak @endif>
                <div class="ui-table-scroll" tabindex="0" role="region" aria-label="Colaboradores {{ $tab }}">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                @if($puedeSeleccionar)<th scope="col" class="w-16">Elegir</th>@endif
                                <th scope="col">Código</th>
                                <th scope="col">Colaborador</th>
                                <th scope="col">Puesto</th>
                                <th scope="col">Estado</th>
                                <x-sistema.cabeceras-campos :tabla="$tablaCampos" />
                                <th scope="col" class="text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($listado as $c)
                                <tr>
                                    @if($puedeSeleccionar)
                                        <td>
                                            <label class="flex min-h-11 min-w-11 cursor-pointer items-center">
                                                <input type="checkbox" x-bind:checked="isSelected(@js($c->codigo))" @change="toggleSeleccion(@js($c->codigo))">
                                                <span class="sr-only">Seleccionar a {{ $c->nombre }}</span>
                                            </label>
                                        </td>
                                    @endif
                                    <td>{{ $c->codigo }}</td>
                                    <td class="font-semibold text-ink">{{ $c->nombre }}</td>
                                    <td>{{ $c->puesto }}</td>
                                    <td><x-ui.estado :variante="$activo ? 'exito' : 'neutra'">{{ $activo ? 'Activo' : 'Inactivo' }}</x-ui.estado></td>
                                    <x-sistema.celdas-campos :tabla="$tablaCampos" :registro="$c->codigo" />
                                    <td>
                                        <div class="flex flex-wrap justify-end gap-2">
                                            @rutaDisponible($ruta.'.expediente')
                                                <x-ui.accion class="ui-btn-sm" :href="route($ruta.'.expediente', $c)">Ver expediente</x-ui.accion>
                                            @endrutaDisponible
                                            @rutaDisponible($ruta.'.edit')
                                                <x-ui.accion class="ui-btn-sm" :href="route($ruta.'.edit', $c)">Editar</x-ui.accion>
                                            @endrutaDisponible
                                            @rutaDisponible($ruta.'.baja')
                                                <x-ui.accion class="ui-btn-sm" :href="route($ruta.'.baja', $c)">Baja laboral</x-ui.accion>
                                            @endrutaDisponible
                                            @rutaDisponible($ruta.'.estado')
                                                <form method="POST" action="{{ route($ruta.'.estado', $c) }}"
                                                      data-confirmar="{{ $activo ? '¿Deseas inactivar este colaborador?' : '¿Deseas activar este colaborador?' }}">
                                                    @csrf @method('PATCH')
                                                    <x-ui.accion class="ui-btn-sm" type="submit" :variante="$activo ? 'riesgo' : 'secundaria'">{{ $activo ? 'Inactivar' : 'Activar' }}</x-ui.accion>
                                                </form>
                                            @endrutaDisponible
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="{{ 5 + (int) $puedeSeleccionar + $tablaCampos['campos']->count() }}"><p class="ui-empty-state">No hay colaboradores {{ $tab }}.</p></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $listado->links() }}
            </div>
        @endforeach
    </x-sistema.panel>

</div>

@include('admin.colaboradores.seleccion-script')
@endsection

@props(['titulo', 'notas' => []])

@php($asignaciones = \App\Services\AccesoInventarioOperativo::prefijo(auth()->user()).'.asignaciones')

<div class="space-y-5">
    <x-ui.encabezado :titulo="$titulo" :descripcion="auth()->user()->name">
        @rutaDisponible($asignaciones.'.index')
            @rutaDisponible($asignaciones.'.create')
                <x-ui.accion :href="route($asignaciones.'.create')" variante="primaria">Nueva asignación</x-ui.accion>
            @endrutaDisponible
        @endrutaDisponible
    </x-ui.encabezado>
    @include('partials.modulos-operativos', ['superficie' => 'panel', 'notas' => $notas])
</div>

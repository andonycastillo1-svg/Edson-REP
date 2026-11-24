@props(['titulo', 'volver' => null, 'descripcion' => null, 'configuracion' => false])
<x-app-layout>
    <div {{ $attributes->class('space-y-4') }}>
        <x-ui.encabezado :titulo="$titulo" :descripcion="$descripcion">
            {{ $acciones ?? '' }}
            @if($volver !== false)
                <a href="{{ $volver ?? route('dashboard') }}" class="ui-btn-secondary">Volver</a>
            @endif
        </x-ui.encabezado>
        @if($configuracion)<x-sistema.apartados />@endif
        {{ $slot }}
    </div>
</x-app-layout>

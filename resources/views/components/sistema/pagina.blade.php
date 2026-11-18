@props(['titulo', 'volver' => null, 'descripcion' => null, 'configuracion' => false])
<x-app-layout>
    <div class="space-y-4">
        <header class="ui-page-header ui-page-header-body">
            <div>
                <h1 class="ui-page-title">{{ $titulo }}</h1>
                @if($descripcion)<p class="ui-page-subtitle">{{ $descripcion }}</p>@endif
            </div>
            <a href="{{ $volver ?? route('dashboard') }}" class="ui-btn-secondary">Volver</a>
        </header>
        @if($configuracion)<x-sistema.apartados />@endif
        {{ $slot }}
    </div>
</x-app-layout>

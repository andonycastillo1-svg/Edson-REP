@props(['titulo', 'volver' => null])
<x-app-layout>
    <div class="space-y-4">
        <header class="ui-page-header ui-page-header-body">
            <h1 class="ui-page-title">{{ $titulo }}</h1>
            <a href="{{ $volver ?? route('dashboard') }}" class="ui-btn-secondary">Volver</a>
        </header>
        {{ $slot }}
    </div>
</x-app-layout>

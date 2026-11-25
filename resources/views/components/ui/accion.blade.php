@props(['href' => null, 'variante' => 'secundaria', 'icono' => null, 'type' => 'button'])

@php
    $clase = match ($variante) {
        'primaria' => 'ui-btn-primary',
        'riesgo' => 'ui-btn-danger',
        default => 'ui-btn-secondary',
    };
@endphp

@if($href !== null)
    <a href="{{ $href }}" {{ $attributes->class($clase) }}>
        @if($icono)<x-ui.icono :nombre="$icono" />@endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class($clase) }}>
        @if($icono)<x-ui.icono :nombre="$icono" />@endif
        {{ $slot }}
    </button>
@endif

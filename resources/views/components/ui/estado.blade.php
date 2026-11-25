@props(['variante' => 'neutra'])

@php
    $clase = match ($variante) {
        'exito' => 'ui-badge-success',
        'pendiente' => 'ui-badge-warning',
        'riesgo' => 'ui-badge-danger',
        'informacion' => 'ui-badge-info',
        default => null,
    };
@endphp

<span {{ $attributes->class(['ui-badge', $clase]) }}>{{ $slot }}</span>

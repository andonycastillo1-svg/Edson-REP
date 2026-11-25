@php
    $variante = match ($estadoTraslado) {
        'PENDIENTE' => 'pendiente',
        'APROBADO' => 'exito',
        'RECHAZADO' => 'riesgo',
        default => 'neutra',
    };
@endphp
<x-ui.estado :variante="$variante">{{ ucfirst(mb_strtolower($estadoTraslado)) }}</x-ui.estado>

@props(['nombre'])
@php
    $trazos = [
        'menu' => 'M4 6h16M4 12h16M4 18h16',
        'inicio' => 'm3 10 9-7 9 7M5 10v10h14V10M9 20v-6h6v6',
        'bodega' => 'm3 10 9-6 9 6v10H3V10Zm5 10v-7h8v7M8 16h8',
        'compra' => 'M6 3h12v18l-3-2-3 2-3-2-3 2V3Zm3 5h6m-6 4h6m-6 4h3',
        'caja' => 'M3 7l9-4 9 4v10l-9 4-9-4V7Zm0 0 9 4 9-4m-9 4v10m-4-14 8 4',
        'traslado' => 'M3 8h17m-4-4 4 4-4 4m5 4H4m4-4-4 4 4 4',
        'personas' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m20 0v-2a4 4 0 0 0-3-4m-5-7a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3-3a3 3 0 0 1 0 6',
        'usuario' => 'M5 21v-2a5 5 0 0 1 5-5h4a5 5 0 0 1 5 5v2M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z',
        'vehiculo' => 'M3 16V6h11v10m0-7h4l3 4v3h-7M3 16h2m4 0h7m4 0h2m-13 2a2 2 0 1 1-4 0 2 2 0 0 1 4 0Zm11 0a2 2 0 1 1-4 0 2 2 0 0 1 4 0Z',
        'herramienta' => 'M14 6a5 5 0 0 0-6 6l-5 5a2.1 2.1 0 0 0 3 3l5-5a5 5 0 0 0 6-6l-3 3-3-3 3-3Z',
        'lista' => 'M8 4H5v16h14V4h-3M8 3h8v4H8V3Zm1 8h6m-6 4h6',
        'capas' => 'm3 7 9-4 9 4-9 4-9-4Zm0 5 9 4 9-4m-18 5 9 4 9-4',
        'archivo' => 'M5 3h9l5 5v13H5V3Zm9 0v5h5M8 12h8m-8 4h8m-4-4v4',
        'alerta' => 'm12 3 9 17H3l9-17Zm0 6v4m0 3h.01',
        'flecha' => 'm9 6 6 6-6 6',
        'salir' => 'M10 4H4v16h6m3-12 4 4-4 4m-4-4h12',
    ];
@endphp
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round"
    aria-hidden="true" {{ $attributes->class('ui-icon') }}>
    <path d="{{ $trazos[$nombre] ?? $trazos['archivo'] }}" />
</svg>

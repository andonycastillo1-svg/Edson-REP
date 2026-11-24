@props(['backUrl', 'backRoute' => null])
@php
    $visible = !$backRoute || app(\App\Sistema\Permisos::class)->ruta(auth()->user(), $backRoute);
@endphp

<div {{ $attributes->class(['mb-4 flex flex-wrap items-center gap-2']) }}>
    <a href="{{ $visible ? $backUrl : route('dashboard') }}" class="ui-btn-secondary">
        <x-ui.icono nombre="flecha" class="rotate-180" />
        {{ $visible ? 'Volver' : 'Ir al inicio' }}
    </a>
</div>

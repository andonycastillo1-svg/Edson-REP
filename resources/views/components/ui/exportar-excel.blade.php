@props(['modulo' => null, 'url' => null, 'filtros' => [], 'campos' => [], 'texto' => 'Exportar Excel'])

@php
    $permiso = $modulo.'.datos_exportar';
    $puedeExportar = isset(\App\Sistema\CatalogoPermisos::INVENTARIO_OPERATIVO[$permiso])
        ? app(\App\Services\AccesoInventarioOperativo::class)->tiene(auth()->user(), $permiso)
        : ($modulo === null || app(\App\Sistema\Permisos::class)->permite(auth()->user(), $permiso));
@endphp
@if($puedeExportar)
    <a href="{{ $url ?? request()->fullUrlWithQuery(['page' => null, 'xlsx' => 1, ...$filtros]) }}"
        @if($campos) data-excel-campos="{{ json_encode($campos, JSON_FORCE_OBJECT) }}" @endif
        {{ $attributes->class('ui-btn-download') }}>{{ $texto }}</a>
@endif

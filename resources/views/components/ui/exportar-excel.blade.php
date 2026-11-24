@props(['modulo' => null, 'url' => null, 'filtros' => [], 'campos' => [], 'texto' => 'Exportar Excel'])

@if($modulo === null || app(\App\Sistema\Permisos::class)->permite(auth()->user(), $modulo.'.datos_exportar'))
    <a href="{{ $url ?? request()->fullUrlWithQuery(['page' => null, 'xlsx' => 1, ...$filtros]) }}"
        @if($campos) data-excel-campos="{{ json_encode($campos, JSON_FORCE_OBJECT) }}" @endif
        {{ $attributes->class('ui-btn-download') }}>{{ $texto }}</a>
@endif

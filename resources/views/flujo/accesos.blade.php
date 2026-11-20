@php
    $acceso = app(\App\Services\FlujoAcceso::class);
    $enlaces = [
        'inspecciones' => ['flujo.inspecciones.index', 'Inspecciones y requerimientos'],
        'consolidados' => ['flujo.consolidados.index', 'Consolidados'],
        'compras' => ['flujo.compras.index', 'Necesidades de compra'],
        'importar' => ['flujo.importar.index', 'Importar asignaciones desde Excel'],
        'configurar' => ['flujo.catalogos', 'Catálogos operativos'],
    ];
@endphp
<nav class="mx-auto my-4 flex max-w-6xl flex-wrap gap-3" aria-label="Requerimientos y compras">
    @foreach($enlaces as $permiso => [$ruta, $etiqueta])
        @if($acceso->puede(auth()->user(), $permiso) && ($permiso !== 'importar' || app(\App\Sistema\Permisos::class)->permite(auth()->user(), 'asignaciones.crear')))
            <a class="ui-btn-secondary" href="{{ route($ruta) }}" @if(request()->routeIs($ruta)) aria-current="page" @endif>{{ $etiqueta }}</a>
        @endif
    @endforeach
</nav>

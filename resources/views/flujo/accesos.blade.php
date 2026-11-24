@php
    $acceso = app(\App\Services\FlujoAcceso::class);
    $enlaces = [
        'inspecciones' => ['flujo.inspecciones.index', ['flujo.inspecciones.*', 'flujo.cortes.*'], 'Requerimientos'],
        'consolidados' => ['flujo.consolidados.index', 'flujo.consolidados.*', 'Consolidados'],
        'compras' => ['flujo.compras.index', 'flujo.compras.*', 'Necesidades de compra'],
        'importar' => ['flujo.importar.index', 'flujo.importar.*', 'Asignaciones por Excel'],
        'configurar' => ['flujo.catalogos', 'flujo.catalogos*', 'Catálogos operativos'],
    ];
@endphp
<nav class="flujo-nav lg:hidden" aria-label="Flujo operativo">
    @foreach($enlaces as $permiso => [$ruta, $patron, $etiqueta])
        @if($acceso->puede(auth()->user(), $permiso) && ($permiso !== 'importar' || app(\App\Sistema\Permisos::class)->permite(auth()->user(), 'asignaciones.crear')))
            <a class="flujo-tab" href="{{ route($ruta) }}" @if(request()->routeIs(...(array) $patron)) aria-current="page" @endif>{{ $etiqueta }}</a>
        @endif
    @endforeach
</nav>

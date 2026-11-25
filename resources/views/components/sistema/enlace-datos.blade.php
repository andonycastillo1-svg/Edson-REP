@props(['modulo', 'registro' => null])
@php
    $usuario = auth()->user();
    $modulos = app(\App\Sistema\Modulos::class);
    $disponible = $usuario && app(\App\Sistema\Permisos::class)->instalado() && $modulos->consultar($usuario, $modulo);
    $puedeEditar = $disponible && $modulos->puedeEditarCampos($usuario, $modulo);
@endphp
@if(($disponible && $registro !== null) || $puedeEditar)
    <div class="inline-flex flex-wrap gap-2">
        @if($registro !== null)
            <x-ui.accion :href="route('sistema.datos.ficha', [$modulo, $registro])" class="ui-btn-sm">Ver campos</x-ui.accion>
        @endif
        @if($puedeEditar)
            <x-ui.accion :href="route('sistema.operacion.campos', $modulo)" class="ui-btn-sm">
                Editar campos de {{ mb_strtolower(\App\Sistema\Modulos::definicion($modulo)[0]) }}
            </x-ui.accion>
        @endif
    </div>
@endif

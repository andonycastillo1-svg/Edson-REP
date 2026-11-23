<x-sistema.pagina titulo="Coordinación de requerimientos" :volver="false" descripcion="Revisa los consolidados enviados por bodega central para tu equipo.">
    @include('flujo.accesos')
    @if(app(\App\Services\FlujoAcceso::class)->puede(auth()->user(), 'consolidados'))
        <x-sistema.panel titulo="Revisión de consolidados">
            <p class="mb-4 text-sm">Abre un consolidado para comparar las cantidades originales, registrar ajustes y aprobarlo o devolverlo.</p>
            <a class="ui-btn-primary" href="{{ route('flujo.consolidados.index', ['estado' => 'pendientes']) }}">Ver consolidados en coordinación</a>
        </x-sistema.panel>
    @endif
</x-sistema.pagina>

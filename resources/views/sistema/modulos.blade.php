<x-sistema.pagina titulo="Datos de los módulos" descripcion="Consulta y exporta los registros a los que tiene acceso tu cuenta.">
    <x-sistema.nav-datos />
    @if($modulos)
        <x-sistema.panel>
            <x-sistema.elegir-modulo :modulos="$modulos" :accion="route('sistema.datos')" />
        </x-sistema.panel>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($modulos as $modulo => $spec)
                <a class="dashboard-card flex min-h-14 items-center justify-between gap-3 font-semibold" href="{{ route('sistema.datos.lista', $modulo) }}">
                    {{ $spec[0] }} <span aria-hidden="true" class="text-xl text-blue-700">›</span>
                </a>
            @endforeach
        </div>
    @else
        <p class="ui-empty-state">Tu cuenta no tiene módulos disponibles para consulta.</p>
    @endif
</x-sistema.pagina>

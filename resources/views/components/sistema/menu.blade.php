@auth
    @php
        $permisosMenu = app(\App\Sistema\Permisos::class);
        $usuarioMenu = auth()->user();
        $hayDatos = collect(array_keys(\App\Sistema\Modulos::DEFINICIONES))
            ->contains(fn ($modulo) => app(\App\Sistema\Modulos::class)->consultar($usuarioMenu, $modulo));
        $hayConfiguracion = $permisosMenu->esSuperadministrador($usuarioMenu)
            || collect(array_keys(\App\Sistema\CatalogoPermisos::CONFIGURACION))->contains(fn ($p) => $permisosMenu->permite($usuarioMenu, $p));
    @endphp
    @if($permisosMenu->instalado() && ($hayDatos || $hayConfiguracion))
        <div class="flex flex-wrap items-center gap-2">
            @if($hayDatos)
                <a class="ui-btn-secondary" href="{{ route('sistema.datos') }}" @if(request()->routeIs('sistema.datos*')) aria-current="page" @endif>Datos de los módulos</a>
            @endif
            @if($hayConfiguracion)
                <a class="ui-btn-secondary" href="{{ route('sistema.configuracion') }}" @if(request()->routeIs('sistema.configuracion')) aria-current="page" @endif>Configuración del sistema</a>
            @endif
        </div>
    @endif
@endauth

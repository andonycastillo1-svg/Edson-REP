@props(['navegacion' => false])
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
        <div @class(['ui-rail-group' => $navegacion, 'flex flex-wrap items-center gap-2' => !$navegacion])>
            @if($navegacion)<p>Sistema</p>@endif
            @if($hayDatos)
                <a class="{{ $navegacion ? 'ui-rail-link' : 'ui-btn-secondary' }}" href="{{ route('sistema.datos') }}" @if(request()->routeIs('sistema.datos*')) aria-current="page" @endif>@if($navegacion)<x-ui.icono nombre="archivo" />@endif Datos de los módulos</a>
            @endif
            @if($hayConfiguracion)
                <a class="{{ $navegacion ? 'ui-rail-link' : 'ui-btn-secondary' }}" href="{{ route('sistema.configuracion') }}" @if(request()->routeIs('sistema.configuracion', 'sistema.seguridad', 'sistema.rol', 'sistema.usuario', 'sistema.campos*', 'sistema.presentacion', 'sistema.parametros', 'sistema.notificaciones', 'sistema.auditoria')) aria-current="page" @endif>@if($navegacion)<x-ui.icono nombre="herramienta" />@endif Configuración del sistema</a>
            @endif
        </div>
    @endif
@endauth

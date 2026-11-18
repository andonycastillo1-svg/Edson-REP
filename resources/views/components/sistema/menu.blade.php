@auth
    @php
        $permisosMenu = app(\App\Sistema\Permisos::class);
        $usuarioMenu = auth()->user();
        $configMenu = $permisosMenu->instalado() && ($permisosMenu->esSuperadministrador($usuarioMenu)
            || collect(array_keys(\App\Sistema\CatalogoPermisos::CONFIGURACION))->contains(fn ($p) => $permisosMenu->permite($usuarioMenu, $p)));
    @endphp
    @if($permisosMenu->instalado())
        <nav aria-label="Gestión del sistema" class="flex flex-wrap items-center gap-2 text-sm font-semibold">
            <a class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-blue-700" href="{{ route('sistema.datos') }}">Datos de los módulos</a>
            @if($configMenu)<a class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-blue-700" href="{{ route('sistema.configuracion') }}">Configuración del sistema</a>@endif
        </nav>
    @endif
@endauth

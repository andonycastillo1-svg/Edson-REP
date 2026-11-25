@auth
    <aside id="modulos-operativos" class="ui-rail">
        <nav aria-label="Módulos" class="ui-rail-list">
            <a class="ui-rail-link" href="{{ route('dashboard') }}" @if(request()->routeIs('dashboard', '*.dashboard')) aria-current="page" @endif>
                <x-ui.icono nombre="inicio" /> Inicio
            </a>
            @include('partials.modulos-operativos', ['superficie' => 'menu'])
            <x-sistema.menu navegacion />
        </nav>
    </aside>
@endauth

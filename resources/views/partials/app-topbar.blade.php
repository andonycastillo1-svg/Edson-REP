@auth
    <header class="ui-topbar">
        <div class="ui-topbar-inner">
            <a href="{{ route('dashboard') }}" class="ui-brand" aria-label="Inicio de Grupo NetSolutions">
                <x-logo-image class="ui-brand-logo" alt="" />
                <span class="ui-brand-copy">
                    <strong>Edson-REP</strong>
                    <span>{{ config('app.empresa') ?: 'Grupo NetSolutions' }}</span>
                </span>
            </a>

            <nav aria-label="Navegación principal" class="ui-topbar-actions">
                <button type="button" class="ui-control-icon lg:hidden" data-abrir-modulos aria-controls="modulos-operativos" aria-expanded="false" aria-label="Módulos">
                    <x-ui.icono nombre="menu" />
                </button>
                @include('partials.notificaciones-campana')
                <details class="relative" data-menu-cuenta>
                    <summary class="ui-user-pill cursor-pointer list-none" aria-label="Menú de la cuenta">
                        <span class="ui-avatar"><x-ui.icono nombre="usuario" /></span>
                        <span class="hidden truncate md:inline">{{ auth()->user()->name }}</span>
                    </summary>
                    <div class="ui-menu-panel">
                        <p class="break-words text-sm font-semibold text-slate-900">{{ auth()->user()->name }}</p>
                        <p class="break-all text-sm">{{ auth()->user()->email }}</p>
                        @if(auth()->user()->role?->nombre)
                            <p class="ui-badge ui-badge-info mt-2">{{ auth()->user()->role->nombre }}</p>
                        @endif
                        <form method="POST" class="mt-3 border-t pt-3" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="ui-btn-secondary"><x-ui.icono nombre="salir" />Cerrar sesión</button>
                        </form>
                    </div>
                </details>
            </nav>
        </div>
    </header>
@endauth

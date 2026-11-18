@auth
    <header class="ui-topbar">
        <div class="ui-topbar-inner">
            <a href="{{ route('dashboard') }}" class="ui-brand" aria-label="Inicio de Grupo NetSolutions">
                <x-logo-image class="ui-brand-logo" />
                <span class="ui-brand-name">Grupo NetSolutions</span>
            </a>

            <nav aria-label="Navegación principal" class="flex min-w-0 items-center gap-2">
                @unless(request()->routeIs('dashboard', '*.dashboard'))
                    <a href="{{ route('dashboard') }}" class="ui-dashboard-link" data-enlace-inicio>Inicio</a>
                @endunless
                @include('partials.notificaciones-campana')
                <details class="relative" data-menu-cuenta>
                    <summary class="ui-user-pill min-h-10 cursor-pointer list-none" aria-label="Menú de la cuenta">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="h-5 w-5 shrink-0" aria-hidden="true">
                            <circle cx="12" cy="8" r="3" /><path d="M5 20v-2a7 7 0 0 1 14 0v2" />
                        </svg>
                        <span class="hidden truncate md:inline">{{ auth()->user()->name }}</span>
                    </summary>
                    <div class="absolute right-0 z-50 mt-2 w-64 max-w-[calc(100vw-2rem)] space-y-2 rounded-xl border border-slate-200 bg-white p-3 shadow-lg">
                        <p class="break-words text-sm font-semibold text-slate-900">{{ auth()->user()->name }}</p>
                        <p class="break-all text-xs">{{ auth()->user()->email }}</p>
                        <x-sistema.menu />
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="ui-btn-secondary">Cerrar sesión</button>
                        </form>
                    </div>
                </details>
            </nav>
        </div>
    </header>
@endauth

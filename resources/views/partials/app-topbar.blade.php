<header class="ui-topbar">
    <div class="ui-topbar-inner">

        {{-- Identidad corporativa --}}
        <a
            href="{{ route('dashboard') }}"
            class="ui-brand group"
            aria-label="Ir al panel principal"
        >
            <img
                src="{{ asset('img/logo.png') }}"
                alt="Grupo Net Solutions"
                class="ui-brand-logo"
            >

            <span class="ui-brand-name">
                Grupo Net Solutions
            </span>
        </a>

        {{-- Acciones de la barra superior --}}
        <div class="flex min-w-0 items-center gap-2">

            @include('partials.notificaciones-campana')

            {{-- Regresar al panel principal --}}
            @unless(request()->routeIs('dashboard', '*.dashboard'))

                {{-- Versión para computadora --}}
                <a
                    href="{{ route('dashboard') }}"
                    class="hidden items-center justify-center gap-2 rounded-full border border-blue-200 bg-blue-50 px-4 py-2 text-xs font-extrabold text-blue-700 shadow-sm transition hover:-translate-y-0.5 hover:bg-blue-100 hover:shadow-md focus:outline-none focus:ring-4 focus:ring-blue-200 sm:inline-flex"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="currentColor"
                        class="h-4 w-4"
                    >
                        <path d="M12 3.172L3.172 12H5v8a1 1 0 001 1h4v-6h4v6h4a1 1 0 001-1v-8h1.828L12 3.172z" />
                    </svg>

                    Panel principal
                </a>

                {{-- Versión para teléfono --}}
                <a
                    href="{{ route('dashboard') }}"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-blue-200 bg-blue-50 text-blue-700 shadow-sm transition hover:bg-blue-100 focus:outline-none focus:ring-4 focus:ring-blue-200 sm:hidden"
                    aria-label="Ir al panel principal"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="currentColor"
                        class="h-5 w-5"
                    >
                        <path d="M12 3.172L3.172 12H5v8a1 1 0 001 1h4v-6h4v6h4a1 1 0 001-1v-8h1.828L12 3.172z" />
                    </svg>
                </a>

            @endunless

            {{-- Menú del usuario --}}
            <details class="relative">
                <summary
                    class="inline-flex cursor-pointer list-none items-center gap-2 rounded-full border border-sky-200 bg-white px-2.5 py-2 text-sm font-semibold text-slate-800 shadow-sm transition hover:bg-sky-50 focus:outline-none focus:ring-4 focus:ring-sky-200 sm:px-3"
                >
                    <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-blue-100 text-blue-700">
                        👤
                    </span>

                    <span class="hidden whitespace-nowrap lg:block">
                        {{ auth()->user()->name }}
                    </span>

                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                        class="hidden h-4 w-4 text-slate-400 sm:block"
                    >
                        <path
                            fill-rule="evenodd"
                            d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                            clip-rule="evenodd"
                        />
                    </svg>
                </summary>

                <div class="absolute right-0 z-50 mt-3 w-72 overflow-hidden rounded-2xl border border-sky-100 bg-white shadow-xl ring-1 ring-slate-900/5">

                    <div class="border-b border-sky-100 px-4 py-4">
                        <p class="text-sm font-extrabold text-slate-900">
                            {{ auth()->user()->name }}
                        </p>

                        <p class="mt-1 break-all text-xs font-medium text-slate-500">
                            {{ auth()->user()->email }}
                        </p>
                    </div>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf

                        <button
                            type="submit"
                            class="flex w-full items-center gap-3 px-4 py-3 text-left text-sm font-bold text-rose-600 transition hover:bg-rose-50 focus:outline-none focus:ring-4 focus:ring-inset focus:ring-rose-100"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                class="h-5 w-5"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M15 12H3m0 0l4-4m-4 4l4 4m5-13h5a2 2 0 012 2v14a2 2 0 01-2 2h-5"
                                />
                            </svg>

                            Cerrar sesión
                        </button>
                    </form>

                </div>
            </details>

        </div>
    </div>
</header>
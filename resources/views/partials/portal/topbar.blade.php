@php
    $roleLabels = [
        1 => 'Administrador',
        2 => 'Operador',
        3 => 'Supervisor',
        4 => 'Recursos Humanos',
    ];

    $roleLabel = $roleLabels[(int) auth()->user()->role_id] ?? 'Usuario';
@endphp

<header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 shadow-sm backdrop-blur-xl">
    <div class="mx-auto flex h-20 w-full max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">

        {{-- Identidad corporativa --}}
        <a
            href="{{ route('dashboard') }}"
            class="flex min-w-0 items-center gap-3"
            aria-label="Ir al panel principal"
        >
            <img
                src="{{ asset('img/logo.png') }}"
                alt="Grupo Net Solutions"
                class="h-auto w-16 shrink-0 object-contain sm:w-20"
            >

            <span class="hidden text-sm font-extrabold tracking-tight text-slate-900 sm:block md:text-base">
                Grupo Net Solutions
            </span>
        </a>

        {{-- Acciones --}}
        <div class="flex shrink-0 items-center gap-2">

            @include('partials.notificaciones-campana')

            <details class="relative">

                <summary
                    class="inline-flex cursor-pointer list-none items-center gap-2 rounded-full border border-slate-200 bg-white px-2 py-2 shadow-sm transition hover:border-blue-200 hover:bg-blue-50 focus:outline-none focus:ring-4 focus:ring-blue-100 sm:px-3"
                >
                    <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-100 text-blue-700">
                        <svg
                            viewBox="0 0 24 24"
                            fill="currentColor"
                            class="h-5 w-5"
                            aria-hidden="true"
                        >
                            <path
                                fill-rule="evenodd"
                                d="M7.5 6a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0zM3.75 20.105a8.25 8.25 0 0116.5 0 .9.9 0 01-.45.78A15.72 15.72 0 0112 22.875a15.72 15.72 0 01-7.8-1.99.9.9 0 01-.45-.78z"
                                clip-rule="evenodd"
                            />
                        </svg>
                    </span>

                    <span class="hidden max-w-[240px] truncate text-sm font-bold text-slate-800 md:block">
                        {{ auth()->user()->name }}
                    </span>

                    <svg
                        viewBox="0 0 20 20"
                        fill="currentColor"
                        class="hidden h-4 w-4 text-slate-400 sm:block"
                        aria-hidden="true"
                    >
                        <path
                            fill-rule="evenodd"
                            d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                            clip-rule="evenodd"
                        />
                    </svg>
                </summary>

                <div class="absolute right-0 z-50 mt-3 w-72 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl ring-1 ring-slate-900/5">

                    <div class="border-b border-slate-100 px-4 py-4">
                        <p class="text-sm font-extrabold text-slate-900">
                            {{ auth()->user()->name }}
                        </p>

                        <p class="mt-1 break-all text-xs font-medium text-slate-500">
                            {{ auth()->user()->email }}
                        </p>

                        <span class="mt-3 inline-flex rounded-full bg-blue-50 px-2.5 py-1 text-xs font-extrabold text-blue-700">
                            {{ $roleLabel }}
                        </span>
                    </div>

                    <form
                        method="POST"
                        action="{{ route('logout') }}"
                    >
                        @csrf

                        <button
                            type="submit"
                            class="flex w-full items-center gap-3 !border-0 !bg-transparent px-4 py-3 text-left text-sm font-extrabold !text-rose-600 !shadow-none transition hover:!bg-rose-50"
                        >
                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                class="h-5 w-5"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6A2.25 2.25 0 005.25 5.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3-3H9m0 0l3-3m-3 3l3 3"
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
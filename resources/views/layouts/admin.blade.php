<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>@yield('title', 'Admin - Inventario')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="ui-shell">

    <header class="ui-topbar">
        <div class="ui-topbar-inner">
            <div class="ui-brand">
                <img src="{{ asset('img/logo1.png') }}" alt="Logo" class="ui-brand-logo">
                <span class="ui-brand-name">Grupo NetSolutions</span>
            </div>

            <div class="flex items-center gap-2">
                @include('partials.notificaciones-campana')
                @unless(request()->routeIs('dashboard', '*.dashboard'))
                    <a href="{{ route('dashboard') }}"
                       class="hidden sm:inline-flex items-center justify-center rounded-full border border-emerald-200 bg-emerald-50 px-4 py-2 text-xs font-bold text-emerald-700 shadow-sm transition hover:bg-emerald-100">
                        Panel principal
                    </a>
                @endunless

                <div class="inline-flex items-center gap-2 rounded-full border border-sky-200 bg-white px-3 py-1.5 text-sm font-semibold text-[#1F2937] md:text-base">
                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-blue-100 text-blue-700">👤</span>
                    <span class="max-w-[220px] truncate">{{ auth()->user()->name }}</span>
                </div>
            </div>
        </div>
    </header>

    @unless(request()->routeIs('dashboard', '*.dashboard'))
        <div class="mx-auto mt-4 w-full max-w-7xl px-4 md:hidden">
            <a href="{{ route('dashboard') }}"
               class="inline-flex w-full items-center justify-center rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2 text-xs font-bold text-emerald-700 shadow-sm transition hover:bg-emerald-100">
                Panel principal
            </a>
        </div>
    @endunless

    <main class="mx-auto min-h-[calc(100vh-64px)] w-full max-w-7xl px-4 py-8 md:px-6">
        @yield('content')
    </main>

</body>
</html>

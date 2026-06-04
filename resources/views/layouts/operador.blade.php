<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>@yield('title', 'Operador')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="ui-shell">

    <header class="ui-topbar">
        <div class="ui-topbar-inner">
            <div class="ui-brand">
                <img src="{{ asset('img/logo1.png') }}" alt="Logo" class="ui-brand-logo">
                <span class="ui-brand-name">Grupo NetSolutions</span>
            </div>

            <div class="flex min-w-0 items-center gap-2">
                @unless(request()->routeIs('dashboard', '*.dashboard'))
                    <a href="{{ route('dashboard') }}" class="ui-dashboard-link hidden sm:inline-flex">
                        Panel principal
                    </a>
                @endunless

                <div class="ui-user-pill">
                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-blue-100 text-blue-700">👤</span>
                    <span class="truncate">{{ auth()->user()->name }}</span>
                </div>
            </div>
        </div>
    </header>

    @unless(request()->routeIs('dashboard', '*.dashboard'))
        <div class="mx-auto mt-4 w-full max-w-7xl px-4 md:hidden">
            <a href="{{ route('dashboard') }}" class="ui-dashboard-link w-full rounded-2xl">
                Panel principal
            </a>
        </div>
    @endunless

    <main class="ui-main">
        @yield('content')
    </main>

</body>
</html>

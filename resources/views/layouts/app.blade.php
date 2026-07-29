<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <meta
        name="csrf-token"
        content="{{ csrf_token() }}"
    >

    <title>
        @yield('title', config('app.name', 'Inventario'))
    </title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js'
    ])
</head>

<body class="ui-shell admin-shell font-sans antialiased">

    @if (!View::hasSection('no_nav'))

        @include('layouts.navigation')

    @else

        @auth
            <div class="fixed right-4 top-4 z-50">
                @include('partials.notificaciones-campana')
            </div>
        @endauth

    @endif

    @if (!View::hasSection('no_header'))
        @isset($header)
            <header class="border-b border-sky-100 bg-white/90 shadow-sm backdrop-blur-xl">
                <div class="mx-auto max-w-7xl px-4 py-5 sm:px-6 lg:px-8">
                    {{ $header }}
                </div>
            </header>
        @endisset
    @endif

    <main class="ui-main">
        @isset($slot)
            {{ $slot }}
        @else
            @yield('content')
        @endisset
    </main>

</body>
</html>
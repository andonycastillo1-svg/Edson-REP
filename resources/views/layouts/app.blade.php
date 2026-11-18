<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', config('app.name')) · {{ config('app.empresa') ?: 'Grupo NetSolutions' }}</title>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="antialiased">
        <div class="ui-shell">
            <a class="saltar-contenido ui-btn-primary sr-only focus:not-sr-only" href="#contenido-principal">Saltar al contenido</a>
            @include('layouts.navigation')

            <!-- Page Heading -->
            @if (!View::hasSection('no_header'))
                @isset($header)
                    <header class="border-b border-sky-100 bg-white/80 shadow-sm backdrop-blur">
                        <div class="mx-auto max-w-7xl px-4 py-5 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endisset
            @endif

            <!-- Page Content -->
            <main id="contenido-principal" tabindex="-1" class="ui-main">
                <x-mensajes />
                @isset($slot)
                    {{ $slot }}
                @else
                    @yield('content')
                @endisset
            </main>
        </div>
    </body>
</html>

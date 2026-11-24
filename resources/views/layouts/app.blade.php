<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Edson-REP') · {{ config('app.empresa') ?: 'Grupo NetSolutions' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="ui-shell">
    <a class="saltar-contenido ui-btn-primary sr-only focus:not-sr-only" href="#contenido-principal">Saltar al contenido</a>
    @include('layouts.navigation')
    <div @class(['ui-workspace', 'ui-workspace-auth' => auth()->check()])>
        @include('partials.navegacion-modulos')
        <div class="ui-workarea">
            <main id="contenido-principal" tabindex="-1" class="ui-main">
                @if (!View::hasSection('no_header'))
                    @isset($header)
                        <header class="ui-page-header ui-page-header-body">{{ $header }}</header>
                    @endisset
                @endif
                <x-mensajes />
                @isset($slot)
                    {{ $slot }}
                @else
                    @yield('content')
                @endisset
            </main>
        </div>
    </div>
</body>
</html>

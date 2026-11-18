<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Inventario') · Grupo NetSolutions</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="ui-shell">
    <a class="saltar-contenido ui-btn-primary sr-only focus:not-sr-only" href="#contenido-principal">Saltar al contenido</a>
    @include('partials.app-topbar')

    <main id="contenido-principal" tabindex="-1" class="ui-main">
        <x-mensajes />
        @yield('content')
    </main>
</body>
</html>

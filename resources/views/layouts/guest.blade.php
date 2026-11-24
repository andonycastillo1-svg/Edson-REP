<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Acceso') · {{ config('app.empresa') ?: 'Grupo NetSolutions' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="ui-shell ui-acceso">
    <main class="ui-acceso-grid">
        <div class="ui-acceso-identidad">
            <x-logo-image class="ui-acceso-logo" alt="" />
            <div>
                <p class="ui-acceso-producto">Sistema de Inventario</p>
                <p class="ui-acceso-empresa">{{ config('app.empresa') ?: 'Grupo NetSolutions' }}</p>
            </div>
        </div>
        <div class="ui-acceso-contenido">
            @isset($slot)
                {{ $slot }}
            @else
                @yield('content')
            @endisset
        </div>
    </main>
</body>
</html>

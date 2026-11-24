<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo }} · Grupo NetSolutions</title>
    @if(is_file(public_path('build/manifest.json')) || is_file(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="ui-shell">
    <main class="ui-acceso grid min-h-dvh place-items-center">
        <section class="ui-panel w-full max-w-lg space-y-4 p-6 sm:p-8">
            <x-logo-image class="ui-brand-logo" :configurable="false" />
            <p class="ui-kicker">{{ $codigo }}</p>
            <h1 class="ui-page-title">{{ $titulo }}</h1>
            <p>{{ $mensaje }}</p>
            <a class="ui-btn-secondary" href="{{ url('/') }}">Ir al inicio</a>
        </section>
    </main>
</body>
</html>

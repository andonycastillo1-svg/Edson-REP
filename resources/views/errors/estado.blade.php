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
    <main class="ui-main flex items-start justify-center py-12">
        <section class="ui-panel w-full max-w-lg space-y-3 p-5 sm:p-6">
            <p class="text-sm font-semibold text-blue-800">{{ $codigo }}</p>
            <h1 class="ui-page-title">{{ $titulo }}</h1>
            <p>{{ $mensaje }}</p>
            <a class="ui-btn-secondary" href="{{ url('/') }}">Ir al inicio</a>
        </section>
    </main>
</body>
</html>

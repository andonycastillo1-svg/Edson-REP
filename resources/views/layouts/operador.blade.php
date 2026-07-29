<!DOCTYPE html>
<html lang="es">
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
        @yield('title', 'Inventario')
    </title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js'
    ])
</head>

<body class="ui-shell admin-shell">

    @include('partials.app-topbar')

    <main class="ui-main">
        @yield('content')
    </main>

</body>
</html>
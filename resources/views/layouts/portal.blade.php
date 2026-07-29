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
        @yield('title', 'Sistema de inventario')
    </title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js'
    ])
</head>

<body
    class="min-h-screen font-sans text-slate-900 antialiased"
    style="background-color: var(--ui-bg);"
>

    @include('partials.portal.topbar')

    <main class="mx-auto min-h-[calc(100vh-80px)] w-full max-w-7xl px-4 py-7 sm:px-6 lg:px-8 lg:py-10">
        @yield('content')
    </main>

</body>
</html> 
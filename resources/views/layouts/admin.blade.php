<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>@yield('title', 'Admin - Inventario')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-100 via-sky-50 to-blue-100 text-slate-900">

    <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/90 backdrop-blur">
        <div class="mx-auto flex h-14 max-w-7xl items-center justify-between px-4 md:px-6">
            <div class="flex items-center gap-2 text-slate-700">
                <img src="{{ asset('img/logo1.png') }}" alt="Logo" class="h-8 w-8 rounded-md border border-slate-200 bg-white p-0.5 object-contain">
                <span class="text-sm font-semibold">Sistema de Inventario</span>
            </div>
            <div class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-medium text-slate-700">
                <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-blue-100 text-blue-700">👤</span>
                {{ auth()->user()->name }}
            </div>
        </div>
    </header>

    <main class="mx-auto flex min-h-[calc(100vh-56px)] w-full max-w-7xl items-center justify-center px-4 py-8 md:px-6">
        @yield('content')
    </main>

</body>
</html>

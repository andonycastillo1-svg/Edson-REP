<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>@yield('title', 'Admin - Inventario')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/admin-theme.css') }}">
</head>
<body class="min-h-screen text-slate-900">

    <header class="sticky top-0 z-20 border-b border-sky-200/80 bg-white/90 shadow-sm backdrop-blur-xl">
        <div class="mx-auto flex min-h-20 max-w-7xl items-center justify-between gap-4 px-4 py-3 md:px-6">
            <div class="flex items-center gap-3 text-slate-700">
                <x-logo-image class="h-14 w-14 rounded-2xl border border-sky-200 bg-white p-1.5 object-contain shadow-sm md:h-16 md:w-16" />
                <span class="text-lg font-extrabold tracking-tight text-sky-950 md:text-xl">Grupo NetSolutions</span>
            </div>
            <div class="inline-flex items-center gap-2 rounded-full border border-sky-200 bg-white px-4 py-2 text-sm font-semibold text-slate-800 shadow-sm md:text-base">
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 text-blue-700">👤</span>
                <span class="max-w-[220px] truncate">{{ auth()->user()->name }}</span>
            </div>
        </div>
    </header>

    <main class="mx-auto flex min-h-[calc(100vh-80px)] w-full max-w-7xl items-center justify-center px-4 py-8 md:px-6">
        @yield('content')
    </main>

</body>
</html>

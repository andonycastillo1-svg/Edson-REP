<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>@yield('title', 'Operador')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/admin-theme.css') }}">
</head>
<body class="app-shell min-h-screen text-slate-900">

    <header class="sticky top-0 z-20 border-b border-sky-100 bg-white/85 shadow-sm backdrop-blur-xl">
        <div class="mx-auto flex min-h-16 max-w-7xl items-center justify-between gap-4 px-4 py-2 md:px-6">
            <div class="flex items-center gap-3 text-slate-700">
                <x-logo-image variant="logo1" class="h-12 w-12 rounded-2xl border border-sky-100 bg-white p-1 object-contain shadow-sm md:h-14 md:w-14" />
                <div>
                    <span class="block text-base font-extrabold tracking-wide text-sky-950 md:text-lg">Grupo NetSolutions</span>
                    <span class="text-xs font-semibold uppercase tracking-[0.2em] text-sky-500">Inventario</span>
                </div>
            </div>
            <div class="inline-flex items-center gap-2 rounded-full border border-sky-100 bg-white/90 px-4 py-2 text-sm font-semibold text-slate-800 shadow-sm md:text-base">
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-sky-100 text-sky-700">👤</span>
                <span class="max-w-[220px] truncate">{{ auth()->user()->name }}</span>
            </div>
        </div>
    </header>

    <main class="mx-auto flex min-h-[calc(100vh-72px)] w-full max-w-7xl items-center justify-center px-4 py-8 md:px-6">
        @yield('content')
    </main>

</body>
</html>

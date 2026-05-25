<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>@yield('title', 'Admin - Inventario')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#EAF4FF] text-[#1F2937]">

    <header class="sticky top-0 z-20 border-b border-sky-200 bg-white/90 backdrop-blur">
        <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 md:px-6">
            <div class="flex items-center gap-3 text-[#475569]">
                <img src="{{ asset('img/logo1.png') }}" alt="Logo" class="h-14 w-14 rounded-md border border-sky-200 bg-white p-0.5 object-contain md:h-16 md:w-16">
                <span class="text-base font-bold tracking-wide text-[#1F2937]">Grupo NetSolutions</span>
            </div>

            <div class="inline-flex items-center gap-2 rounded-full border border-sky-200 bg-white px-3 py-1.5 text-sm font-semibold text-[#1F2937] md:text-base">
                <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-blue-100 text-blue-700">👤</span>
                <span class="max-w-[220px] truncate">{{ auth()->user()->name }}</span>
            </div>
        </div>
    </header>

    <main class="mx-auto min-h-[calc(100vh-64px)] w-full max-w-7xl px-4 py-8 md:px-6">
        @yield('content')
    </main>

</body>
</html>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>@yield('title', 'Operador')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="relative min-h-screen text-gray-900 bg-gradient-to-br from-sky-400 to-blue-600">

    <div class="fixed inset-0 flex items-center justify-center pointer-events-none">
        <img src="{{ asset('img/logo.png') }}" alt="Logo"
             class="w-[520px] opacity-10 select-none">
    </div>

    <div class="relative z-10 min-h-screen flex items-center justify-center px-6">

        <div class="absolute top-6 right-8 text-sm text-white/90">
            {{ auth()->user()->name }}
        </div>

        @yield('content')

    </div>

</body>
</html>

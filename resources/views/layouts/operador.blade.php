<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Operador')</title>
    @vite('resources/css/app.css')
</head>
<body class="min-h-screen bg-gradient-to-r from-blue-500 to-blue-700 flex items-center justify-center">

    @yield('content')

</body>
</html>
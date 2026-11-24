<x-guest-layout>
    <h1 class="ui-page-title">Inventario y operaciones</h1>
    @if (Route::has('login'))
        <nav class="mt-5 flex flex-wrap gap-2" aria-label="Acceso">
            @auth
                <a href="{{ url('/dashboard') }}" class="ui-btn-primary">Ir al panel</a>
            @else
                <a href="{{ route('login') }}" class="ui-btn-primary">Iniciar sesión</a>
                @if (Route::has('register'))
                    <a href="{{ route('register') }}" class="ui-btn-secondary">Crear cuenta</a>
                @endif
            @endauth
        </nav>
    @endif
    <nav class="mt-7 flex flex-wrap gap-x-4 gap-y-2 border-t pt-4 text-sm" aria-label="Recursos técnicos">
        <a href="https://laravel.com/docs" target="_blank" rel="noopener" class="flujo-enlace">Documentación de Laravel</a>
        <a href="https://laracasts.com" target="_blank" rel="noopener" class="flujo-enlace">Laracasts</a>
        <a href="https://cloud.laravel.com" target="_blank" rel="noopener" class="flujo-enlace">Laravel Cloud</a>
    </nav>
</x-guest-layout>

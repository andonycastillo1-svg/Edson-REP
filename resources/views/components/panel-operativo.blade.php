@props(['titulo', 'accesos'])
@php
    $usuario = auth()->user();
    $permisos = app(\App\Sistema\Permisos::class);
    $visibles = collect($accesos)->filter(fn ($acceso, $ruta) => ($acceso[3] ?? true) && $permisos->ruta($usuario, $ruta));
@endphp

<div class="mx-auto max-w-6xl space-y-4">
    <header class="ui-page-header ui-page-header-body">
        <div>
            <h1 class="ui-page-title">{{ $titulo }}</h1>
            <p class="ui-page-subtitle">{{ $usuario->name }}</p>
        </div>
        <x-sistema.menu />
    </header>
    @if($visibles->isEmpty())
        <p class="ui-empty-state">Tu cuenta no tiene accesos operativos disponibles en este panel.</p>
    @else
        <nav aria-label="Módulos disponibles" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($visibles as $ruta => [$nombre, $descripcion, $icono])
                <a href="{{ route($ruta) }}" class="dashboard-card group flex min-h-20 min-w-0 items-center gap-3">
                    <span aria-hidden="true" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-lg">{{ $icono }}</span>
                    <span class="min-w-0 grow">
                        <span class="block font-bold text-slate-900">{{ $nombre }}</span>
                        <span class="block text-sm text-slate-600">{{ $descripcion }}</span>
                    </span>
                    <span class="text-xl text-slate-500 group-hover:text-blue-700" aria-hidden="true">›</span>
                </a>
            @endforeach
        </nav>
    @endif
</div>

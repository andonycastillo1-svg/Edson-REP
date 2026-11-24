@props(['titulo', 'accesos'])
@php
    $usuario = auth()->user();
    $permisos = app(\App\Sistema\Permisos::class);
    $visibles = collect($accesos)->filter(fn ($acceso, $ruta) => ($acceso[3] ?? true) && $permisos->ruta($usuario, $ruta));
@endphp

<div class="space-y-4">
    <x-ui.encabezado :titulo="$titulo" :descripcion="$usuario->name">
        <div class="lg:hidden"><x-sistema.menu /></div>
    </x-ui.encabezado>
    @if($visibles->isEmpty())
        <p class="ui-empty-state">Tu cuenta no tiene accesos operativos disponibles en este panel.</p>
    @else
        <section class="space-y-3" aria-labelledby="titulo-modulos">
            <h2 id="titulo-modulos" class="text-base font-semibold">Módulos operativos</h2>
            <nav aria-label="Módulos disponibles" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach($visibles as $ruta => [$nombre, $descripcion, $icono])
                    <a href="{{ route($ruta) }}" class="dashboard-card flex min-h-24 min-w-0 items-center gap-3">
                        <span class="ui-module-icon"><x-ui.icono :nombre="$icono" /></span>
                        <span class="min-w-0 grow">
                            <span class="block font-semibold text-slate-900">{{ $nombre }}</span>
                            <span class="mt-1 block text-sm text-slate-600">{{ $descripcion }}</span>
                        </span>
                        <x-ui.icono nombre="flecha" class="text-slate-500" />
                    </a>
                @endforeach
            </nav>
        </section>
    @endif
</div>

@php($acceso = app(\App\Services\FlujoAcceso::class))
<x-sistema.pagina titulo="Consolidados" :descripcion="$acceso->puede(auth()->user(), 'revisar') ? 'Revisa las cantidades del equipo y aprueba los requerimientos listos.' : 'Consulta el avance de cada período, desde el borrador hasta el cierre de compras.'" :volver="route('dashboard')">
    <x-slot:acciones>
        <x-ui.exportar-excel />
        @if($acceso->puede(auth()->user(), 'consolidar') && $acceso->puede(auth()->user(), 'inspecciones'))
            <a class="ui-btn-primary" href="{{ route('flujo.inspecciones.index') }}">Preparar consolidado</a>
        @endif
    </x-slot:acciones>
    @include('flujo.accesos')
    <x-sistema.panel titulo="Bandeja de consolidados">
        <nav class="flujo-nav mb-3" aria-label="Filtrar por estado">
            @foreach(['' => 'Todos', 'pendientes' => 'En coordinación', 'correccion' => 'Devueltos', 'aprobado' => 'Aprobados', 'cerrado' => 'Compras cerradas'] as $valor => $etiqueta)
                <a class="flujo-tab" href="{{ route('flujo.consolidados.index', $valor ? ['estado' => $valor] : []) }}"
                   @if(request('estado', '') === $valor) aria-current="page" @endif>{{ $etiqueta }}</a>
            @endforeach
        </nav>
        <ul class="divide-y divide-slate-200">
            @forelse($consolidados as $fila)
                <li class="grid gap-2 py-4 sm:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)_auto] sm:items-center">
                    <div>
                        <a class="flujo-enlace" href="{{ route('flujo.consolidados.show', $fila) }}">{{ $fila->nombre }}</a>
                        <p class="mt-1 text-sm">{{ $fila->desde->format('d/m/Y') }} – {{ $fila->hasta->format('d/m/Y') }}</p>
                    </div>
                    <p class="text-sm">{{ $fila->coordinador?->name }}</p>
                    <div>@include('flujo.estado', ['estado' => $fila->estado])</div>
                </li>
            @empty
                <li class="py-3 text-sm">No hay consolidados con este estado.</li>
            @endforelse
        </ul>
        <div class="mt-4">{{ $consolidados->links() }}</div>
    </x-sistema.panel>
</x-sistema.pagina>

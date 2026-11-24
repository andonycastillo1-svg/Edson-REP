<x-sistema.pagina titulo="Historial de cortes" :volver="route('flujo.inspecciones.index')">
    <x-slot:acciones><a class="ui-btn-primary" href="{{ route('flujo.cortes.create') }}">Nuevo corte</a></x-slot:acciones>
    <x-sistema.panel>
        <nav class="flujo-nav mb-3" aria-label="Filtrar cortes por estado">
            @foreach(['' => 'Todos', ...\App\Models\CorteRequerimiento::ESTADOS] as $estado => $etiqueta)
                <a class="flujo-tab" href="{{ route('flujo.cortes.index', $estado ? ['estado' => $estado] : []) }}"
                    @if(request('estado', '') === $estado) aria-current="page" @endif>{{ $etiqueta }}</a>
            @endforeach
        </nav>
        <ul class="divide-y divide-slate-200">
            @forelse($cortes as $corte)
                <li class="grid items-start gap-2 py-3 sm:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)_auto]">
                    <div>
                        <a class="flujo-enlace" href="{{ route('flujo.cortes.show', $corte) }}">#{{ $corte->id }} · {{ $corte->nombre }}</a>
                        <p class="text-sm">{{ $corte->desde->format('d/m/Y') }} – {{ $corte->hasta->format('d/m/Y') }}</p>
                    </div>
                    <div class="text-sm">
                        <p>{{ $corte->operador?->name }} · {{ $corte->created_at->format('d/m/Y H:i') }}</p>
                        <p>{{ $corte->inspecciones_count }} inspecciones · {{ $corte->consolidados_count }} consolidados</p>
                    </div>
                    <div>@include('flujo.cortes.estado')</div>
                </li>
            @empty
                <li class="py-2 text-sm">Todavía no hay cortes con este estado.</li>
            @endforelse
        </ul>
        <div class="mt-3">{{ $cortes->links() }}</div>
    </x-sistema.panel>
</x-sistema.pagina>

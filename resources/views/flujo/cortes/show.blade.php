<x-sistema.pagina :titulo="$corte->nombre" :volver="route('flujo.cortes.index')">
    <dl class="flujo-dato">
        <div><dt>Corte</dt><dd>#{{ $corte->id }}</dd></div>
        <div><dt>Estado</dt><dd>@include('flujo.cortes.estado')</dd></div>
        <div><dt>Período</dt><dd>{{ $corte->desde->format('d/m/Y') }} – {{ $corte->hasta->format('d/m/Y') }}</dd></div>
        <div><dt>Operador</dt><dd>{{ $corte->operador?->name }}</dd></div>
        <div><dt>Creación</dt><dd>{{ $corte->created_at->format('d/m/Y H:i') }}</dd></div>
    </dl>
    <x-sistema.panel titulo="Consolidados generados">
        <ul class="divide-y divide-slate-200">
            @forelse($corte->consolidados as $fila)
                <li class="flex flex-wrap items-start justify-between gap-2 py-3">
                    <div>
                        <a class="flujo-enlace" href="{{ route('flujo.consolidados.show', $fila) }}">{{ $fila->nombre }}</a>
                        <p class="text-sm">{{ $fila->coordinador?->name }}</p>
                    </div>
                    @include('flujo.estado', ['estado' => $fila->estado])
                </li>
            @empty
                <li class="text-sm">El borrador cancelado no generó consolidados.</li>
            @endforelse
        </ul>
    </x-sistema.panel>
    <x-sistema.panel titulo="Inspecciones seleccionadas">
        <ul class="divide-y divide-slate-200">
            @forelse($inspecciones as $fila)
                <li class="py-2 text-sm">
                    <a class="flujo-enlace" href="{{ route('flujo.inspecciones.show', $fila) }}">#{{ $fila->id }} · {{ $fila->colaborador?->nombre }}</a>
                    <span>{{ $fila->fecha->format('d/m/Y') }} · {{ $fila->region?->nombre }}</span>
                    @if($fila->pivot->anterior)<span class="ui-badge ui-badge-info">Anterior al período</span>@endif
                </li>
            @empty
                <li class="text-sm">Se canceló antes de seleccionar inspecciones.</li>
            @endforelse
        </ul>
        <div class="mt-3">{{ $inspecciones->links() }}</div>
    </x-sistema.panel>
</x-sistema.pagina>

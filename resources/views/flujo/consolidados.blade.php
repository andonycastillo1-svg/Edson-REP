<x-sistema.pagina titulo="Consolidados" :volver="route('dashboard')">
    @include('flujo.accesos')
    <nav class="flex flex-wrap gap-2" aria-label="Filtrar por estado">
        @foreach(['' => 'Todos', 'pendientes' => 'En coordinación', 'correccion' => 'Devueltos', 'aprobado' => 'Aprobados', 'cerrado' => 'Cerrados'] as $valor => $etiqueta)
            <a class="ui-btn-secondary" href="{{ route('flujo.consolidados.index', $valor ? ['estado' => $valor] : []) }}"
               @if(request('estado', '') === $valor) aria-current="page" @endif>{{ $etiqueta }}</a>
        @endforeach
    </nav>
    <x-sistema.panel titulo="Bandeja de consolidados">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50"><tr><th class="p-3">Período</th><th class="p-3">Fechas</th><th class="p-3">Coordinador</th><th class="p-3">Estado</th></tr></thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($consolidados as $fila)
                        <tr>
                            <td class="p-3"><a class="font-semibold underline" href="{{ route('flujo.consolidados.show', $fila) }}">{{ $fila->nombre }}</a></td>
                            <td class="p-3">{{ $fila->desde->format('d/m/Y') }} – {{ $fila->hasta->format('d/m/Y') }}</td>
                            <td class="p-3">{{ $fila->coordinador?->name }}</td>
                            <td class="p-3">{{ \App\Models\Consolidado::ESTADOS[$fila->estado] }}</td>
                        </tr>
                    @empty
                        <tr><td class="p-3" colspan="4">No hay consolidados disponibles.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $consolidados->links() }}</div>
    </x-sistema.panel>
</x-sistema.pagina>

<ul class="grid gap-2 lg:grid-cols-2">
    @forelse($paginador as $fila)
        @php($coordinador = $coordinadores->get($fila->region_id))
        <li>
            <input type="hidden" name="visibles[]" value="{{ $fila->id }}">
            <label class="flujo-eleccion cursor-pointer">
                <input class="mt-1" type="checkbox" name="{{ $campo }}[]" value="{{ $fila->id }}" data-inspeccion
                    @checked(old('seleccion_enviada') ? in_array($fila->id, (array) old($campo, [])) : $ids->contains($fila->id))>
                <span class="min-w-0 break-words">
                    <strong class="block">{{ $fila->colaborador?->nombre }} · {{ $fila->colaborador_codigo }}</strong>
                    <span class="block">#{{ $fila->id }} · {{ $fila->fecha->format('d/m/Y') }} · {{ $fila->region?->nombre }}</span>
                    <span class="block text-slate-600">{{ $fila->supervisor?->name }}</span>
                    @if($fila->consolidados_exists)
                        <span class="ui-badge ui-badge-warning">Ya consolidada. Desmarca esta inspección.</span>
                    @elseif($coordinador)
                        <span class="block text-slate-600">Coordina {{ $coordinador->name }}</span>
                    @else
                        <span class="ui-badge ui-badge-warning">Región sin coordinador activo</span>
                    @endif
                </span>
            </label>
            <a class="flujo-enlace mt-1 inline-block px-3 py-1 text-sm" href="{{ route('flujo.inspecciones.show', $fila) }}" target="_blank" rel="noopener">
                Ver inspección<span class="sr-only"> #{{ $fila->id }} (abre en otra pestaña)</span>
            </a>
        </li>
    @empty
        <li class="text-sm">No hay inspecciones en esta página.</li>
    @endforelse
</ul>
@if($paginador->hasPages())
    <nav class="mt-3 flex flex-wrap items-center gap-2" aria-label="{{ $pagina === 'periodo' ? 'Páginas del período' : 'Páginas anteriores al período' }}">
        <button class="ui-btn-secondary" name="{{ $pagina }}" value="{{ $paginador->currentPage() - 1 }}" @disabled($paginador->onFirstPage())>Página anterior</button>
        <span class="text-sm">Página {{ $paginador->currentPage() }} de {{ $paginador->lastPage() }}</span>
        <button class="ui-btn-secondary" name="{{ $pagina }}" value="{{ $paginador->currentPage() + 1 }}" @disabled(!$paginador->hasMorePages())>Página siguiente</button>
    </nav>
@endif

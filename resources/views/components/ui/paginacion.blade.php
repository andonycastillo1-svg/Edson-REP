@props(['paginador', 'elementos' => []])

@if($paginador->hasPages())
    <nav class="ui-paginacion" aria-label="Paginación">
        @if($elementos)
            <p>{{ $paginador->firstItem() ?? $paginador->count() }}–{{ $paginador->lastItem() ?? $paginador->count() }} de {{ $paginador->total() }} resultados</p>
        @endif
        <div class="flex flex-wrap items-center gap-1.5">
            @if($paginador->onFirstPage())
                <span class="ui-pagina" aria-disabled="true">Anterior</span>
            @else
                <a class="ui-pagina" href="{{ $paginador->previousPageUrl() }}" rel="prev">Anterior</a>
            @endif
            @foreach($elementos as $elemento)
                @if(is_string($elemento))
                    <span class="hidden px-1 text-sm sm:inline" aria-hidden="true">{{ $elemento }}</span>
                @else
                    @foreach($elemento as $pagina => $url)
                        @if($paginador->currentPage() === $pagina)
                            <span class="ui-pagina" aria-current="page">{{ $pagina }}</span>
                        @else
                            <a class="ui-pagina hidden sm:inline-flex" href="{{ $url }}" aria-label="Ir a la página {{ $pagina }}">{{ $pagina }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach
            @if($paginador->hasMorePages())
                <a class="ui-pagina" href="{{ $paginador->nextPageUrl() }}" rel="next">Siguiente</a>
            @else
                <span class="ui-pagina" aria-disabled="true">Siguiente</span>
            @endif
        </div>
    </nav>
@endif

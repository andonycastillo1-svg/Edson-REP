@props(['tabla', 'conservar' => []])

@if($tabla['filtros']->isNotEmpty())
    <form method="GET" action="{{ url()->current() }}" class="ui-filtros mb-4" aria-label="Filtros personalizados">
        @foreach(array_filter(request()->only($conservar), 'is_scalar') as $clave => $valor)
            <input type="hidden" name="{{ $clave }}" value="{{ $valor }}">
        @endforeach
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <x-sistema.filtros-campos :filtros="$tabla['filtros']" />
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="submit" class="ui-btn-secondary">Filtrar campos</button>
            @if(request()->filled('campo'))
                <a href="{{ request()->fullUrlWithoutQuery(['campo', 'page']) }}" class="ui-btn-secondary">Quitar filtros de campos</a>
            @endif
        </div>
    </form>
@endif

@props(['tabla', 'conservar' => []])

@if($tabla['filtros']->isNotEmpty())
    <form method="GET" action="{{ url()->current() }}" class="mb-4 space-y-3 rounded-xl border border-slate-200 bg-white p-4">
        @foreach(array_filter(request()->only($conservar), 'is_scalar') as $clave => $valor)
            <input type="hidden" name="{{ $clave }}" value="{{ $valor }}">
        @endforeach
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <x-sistema.filtros-campos :filtros="$tabla['filtros']" />
        </div>
        <button type="submit" class="ui-btn-secondary">Filtrar campos</button>
        @if(request()->filled('campo'))
            <a href="{{ request()->fullUrlWithoutQuery(['campo', 'page']) }}" class="ui-btn-secondary">Quitar filtros de campos</a>
        @endif
    </form>
@endif

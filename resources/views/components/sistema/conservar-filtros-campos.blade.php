@props(['tabla'])

@foreach($tabla['filtros'] as $campo)
    @if(request()->filled('campo.'.$campo->id))
        <input type="hidden" name="campo[{{ $campo->id }}]" value="{{ request('campo.'.$campo->id) }}">
    @endif
@endforeach

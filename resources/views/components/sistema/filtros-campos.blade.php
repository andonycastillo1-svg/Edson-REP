@props(['filtros'])

@foreach($filtros as $campo)
    <label class="min-w-0 text-sm font-semibold">{{ $campo->etiqueta }}
        @if(in_array($campo->tipo, ['lista', 'multiple'], true))
            <select name="campo[{{ $campo->id }}]" aria-label="{{ $campo->etiqueta }}" class="mt-1 w-full">
                <option value="">Todas las opciones</option>
                @foreach($campo->opciones as $opcion)<option value="{{ $opcion->id }}" @selected(request('campo.'.$campo->id) == $opcion->id)>{{ $opcion->etiqueta }}</option>@endforeach
            </select>
        @elseif(in_array($campo->tipo, ['casilla', 'booleano'], true))
            <select name="campo[{{ $campo->id }}]" aria-label="{{ $campo->etiqueta }}" class="mt-1 w-full">
                <option value="">Cualquiera</option>
                <option value="1" @selected(request('campo.'.$campo->id) === '1')>Sí</option>
                <option value="0" @selected(request('campo.'.$campo->id) === '0')>No</option>
            </select>
        @else
            <input name="campo[{{ $campo->id }}]" value="{{ request('campo.'.$campo->id) }}" maxlength="190" class="mt-1 w-full">
        @endif
    </label>
@endforeach

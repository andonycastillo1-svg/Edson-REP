<label class="ui-label mb-0">Filas por página
    <select name="por_pagina" class="ui-select mt-1">
        @foreach([10, 25, 50] as $valor)
            <option value="{{ $valor }}" @selected((int) $tamano === $valor)>{{ $valor }}</option>
        @endforeach
    </select>
</label>

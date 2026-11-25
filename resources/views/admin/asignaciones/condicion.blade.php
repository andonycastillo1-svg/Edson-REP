<div data-condicion class="grid gap-3 sm:grid-cols-2">
    @php
        $previo = fn ($clave) => old(isset($claveAnterior) ? $claveAnterior.'.'.$clave : $clave);
        $campo = fn ($clave) => isset($prefijo) ? $prefijo.'['.$clave.']' : $clave;
    @endphp
    <label class="block text-sm font-semibold">
        {{ $etiquetaCondicion ?? '¿Qué ocurrió con el producto?' }}
        <select class="ui-input mt-1"
            name="{{ $campo('estado_devolucion') }}" data-campo="estado_devolucion" @if(isset($formulario)) form="{{ $formulario }}" @elseif(empty($opcional)) required @endif>
            <option value="">{{ $textoSinCondicion ?? 'Selecciona una condición' }}</option>
            @foreach(\App\Services\ResolverCustodia::CONDICIONES as $valor => $etiqueta)
                <option value="{{ $valor }}" @selected($previo('estado_devolucion') === $valor)>{{ $etiqueta }}</option>
            @endforeach
        </select>
    </label>
    <label class="block text-sm font-semibold">
        Fecha del hecho (si se conoce)
        <input type="datetime-local" name="{{ $campo('fecha_incidente') }}" data-campo="fecha_incidente" value="{{ $previo('fecha_incidente') }}" @isset($formulario) form="{{ $formulario }}" @endisset
            class="ui-input mt-1">
    </label>
    <label data-otra hidden class="block text-sm font-semibold">
        Efecto en inventario
        <select class="ui-input mt-1" name="{{ $campo('resultado_otra') }}" data-campo="resultado_otra" @isset($formulario) form="{{ $formulario }}" @endisset disabled>
            <option value="">Selecciona un efecto</option>
            @foreach(\App\Services\ResolverCustodia::RESULTADOS_OTRA as $valor => $etiqueta)
                <option value="{{ $valor }}" @selected($previo('resultado_otra') === $valor)>{{ $etiqueta }}</option>
            @endforeach
        </select>
    </label>
    <label class="block text-sm font-semibold sm:col-span-2">
        Observaciones (obligatorias para otra incidencia)
        <textarea name="{{ $campo('detalle_devolucion') }}" data-campo="detalle_devolucion" @isset($formulario) form="{{ $formulario }}" @endisset rows="2" maxlength="1000"
            class="ui-input mt-1">{{ $previo('detalle_devolucion') }}</textarea>
    </label>
</div>

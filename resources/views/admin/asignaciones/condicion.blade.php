<div data-condicion class="grid gap-3 sm:grid-cols-2">
    @php
        $campo = fn ($clave) => isset($prefijo) ? $prefijo.'['.$clave.']' : $clave;
    @endphp
    <label class="block text-sm font-semibold">
        ¿Qué ocurrió con el producto?
        <select class="mt-1 w-full rounded-xl border-slate-300 text-sm"
            name="{{ $campo('estado_devolucion') }}" data-campo="estado_devolucion" @if(isset($formulario)) form="{{ $formulario }}" @else required @endif>
            <option value="">Selecciona una condición</option>
            @foreach(\App\Services\ResolverCustodia::CONDICIONES as $valor => $etiqueta)
                <option value="{{ $valor }}">{{ $etiqueta }}</option>
            @endforeach
        </select>
    </label>
    <label class="block text-sm font-semibold">
        Fecha del hecho (si se conoce)
        <input type="datetime-local" name="{{ $campo('fecha_incidente') }}" data-campo="fecha_incidente" @isset($formulario) form="{{ $formulario }}" @endisset
            class="mt-1 w-full rounded-xl border-slate-300 text-sm">
    </label>
    <label data-otra hidden class="block text-sm font-semibold">
        Efecto en inventario
        <select class="mt-1 w-full rounded-xl border-slate-300 text-sm" name="{{ $campo('resultado_otra') }}" data-campo="resultado_otra" @isset($formulario) form="{{ $formulario }}" @endisset disabled>
            <option value="">Selecciona un efecto</option>
            @foreach(\App\Services\ResolverCustodia::RESULTADOS_OTRA as $valor => $etiqueta)
                <option value="{{ $valor }}">{{ $etiqueta }}</option>
            @endforeach
        </select>
    </label>
    <label class="block text-sm font-semibold sm:col-span-2">
        Observaciones (obligatorias para otra incidencia)
        <textarea name="{{ $campo('detalle_devolucion') }}" data-campo="detalle_devolucion" @isset($formulario) form="{{ $formulario }}" @endisset rows="2" maxlength="1000"
            class="mt-1 block w-full rounded-xl border-slate-300 text-sm"></textarea>
    </label>
</div>

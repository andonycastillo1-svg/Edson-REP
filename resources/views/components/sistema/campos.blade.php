@props(['modulo', 'registro' => null, 'prefijo' => 'campos_personalizados', 'plantilla' => false, 'obligar' => true])
@php
    $servicio = app(\App\Sistema\ValoresCampos::class);
    $usuario = auth()->user();
    $campos = $servicio->campos($modulo, $usuario, 'formulario')->filter(fn ($campo) => $servicio->editable($campo, $usuario));
    $actuales = $registro === null ? collect() : ($servicio->cargar($campos, [$registro])->get((string) $registro) ?? collect());
@endphp
@if($campos->isNotEmpty())
    <fieldset class="my-4 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-2" @if($plantilla) data-campos-plantilla disabled @endif>
        <legend class="px-1 text-sm font-bold text-slate-800">Campos personalizados</legend>
        @foreach($campos as $campo)
            @php
                $nombre = $prefijo.'['.$campo->clave.']';
                $valor = $actuales->get($campo->id, $registro === null ? $campo->predeterminado : null);
                $obligatorio = $obligar && $registro === null && $campo->obligatorio;
                if (!$plantilla && $campo->tipo !== 'archivo') {
                    $valor = old($prefijo.'.'.$campo->clave, $valor);
                }
                $estilo = 'mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm';
            @endphp
            <label class="block text-sm font-semibold text-slate-700">
                {{ $campo->etiqueta }}
                @if($obligatorio)<span class="text-xs font-normal text-slate-600">(obligatorio)</span>@endif
                @switch($campo->tipo)
                    @case('texto_largo')
                        <textarea name="{{ $nombre }}" maxlength="5000" rows="3" class="{{ $estilo }}" @required($obligatorio)>{{ $valor }}</textarea>
                        @break
                    @case('lista')
                    @case('multiple')
                        @if($campo->tipo === 'multiple')
                            <input type="hidden" name="{{ $nombre }}[]" value="">
                        @endif
                        <select name="{{ $nombre }}{{ $campo->tipo === 'multiple' ? '[]' : '' }}" aria-label="{{ $campo->etiqueta }}" class="{{ $estilo }}" @if($campo->tipo === 'multiple') multiple size="4" @endif @required($obligatorio)>
                            @if($campo->tipo === 'lista')<option value="">Seleccionar</option>@endif
                            @foreach($campo->opciones as $opcion)
                                @php($seleccion = in_array($opcion->id, (array) $valor))
                                @if($opcion->activo || $seleccion)
                                    <option value="{{ $opcion->id }}" @selected($seleccion)>{{ $opcion->etiqueta }}{{ $opcion->activo ? '' : ' (inactiva)' }}</option>
                                @endif
                            @endforeach
                        </select>
                        @if($campo->tipo === 'multiple')<span class="mt-1 block text-xs font-normal">Puedes elegir varias opciones con Ctrl, Cmd o Mayús.</span>@endif
                        @break
                    @case('casilla')
                        <input type="hidden" name="{{ $nombre }}" value="0"><input type="checkbox" name="{{ $nombre }}" value="1" class="ml-2" @checked($valor)>
                        @break
                    @case('booleano')
                        <select name="{{ $nombre }}" aria-label="{{ $campo->etiqueta }}" class="{{ $estilo }}" @required($obligatorio)>
                            <option value="">Seleccionar</option>
                            <option value="1" @selected($valor === true || $valor === '1')>Sí</option>
                            <option value="0" @selected($valor === false || $valor === '0')>No</option>
                        </select>
                        @break
                    @case('archivo')
                        <input type="file" name="{{ $nombre }}" accept=".pdf,.png,.jpg,.jpeg,.webp,.xlsx,.docx,.txt" class="mt-1 block w-full" @required($obligatorio)>
                        <span class="mt-1 block text-xs font-normal">Hasta 10 MB. Si el guardado falla, vuelve a adjuntar el archivo.</span>
                        @if($valor)<span class="mt-1 block text-xs text-slate-600">Actual: {{ $valor['nombre'] }}</span>@endif
                        @break
                    @default
                        <input name="{{ $nombre }}" type="{{ match($campo->tipo) { 'entero','decimal' => 'number', 'fecha_hora' => 'datetime-local', 'fecha' => 'date', 'correo' => 'email', 'telefono' => 'tel', default => 'text' } }}"
                               value="{{ $valor }}" @if($campo->tipo === 'decimal') step="0.0001" @endif class="{{ $estilo }}" @required($obligatorio)>
                @endswitch
                @if($campo->ayuda)<span class="mt-1 block text-xs font-normal text-slate-600">{{ $campo->ayuda }}</span>@endif
            </label>
        @endforeach
    </fieldset>
@endif

<x-sistema.pagina :titulo="$campo ? $campo->etiqueta : 'Nuevo campo personalizado'" :volver="route('sistema.campos')" configuracion>
    <x-sistema.panel>
        @if($campo?->archivado_en)
            <p class="text-sm text-slate-600">Archivado el {{ $campo->archivado_en->format('d/m/Y H:i') }}. Se conserva el historial de valores.</p>
        @else
            @php
                $tipo = old('tipo', $campo?->tipo ?? array_key_first(\App\Sistema\ValoresCampos::TIPOS));
                $opciones = array_filter((array) old('opciones', $campo?->opciones?->toArray() ?? []), 'is_array');
                $esLista = in_array($tipo, ['lista', 'multiple'], true);
                $esBooleano = in_array($tipo, ['casilla', 'booleano'], true);
                $default = $campo?->predeterminado;
                $defaultSimple = old('predeterminado', is_bool($default) ? (int) $default : $default);
                $defaultSimple = is_scalar($defaultSimple) ? (string) $defaultSimple : '';
                $seleccion = old('seleccion_predeterminada', session()->hasOldInput() ? [] : array_keys(array_filter(
                    $opciones, fn ($opcion) => isset($opcion['id']) && in_array($opcion['id'], (array) $default)
                )));
            @endphp
            <form method="POST" action="{{ $campo ? route('sistema.campos.actualizar', $campo) : route('sistema.campos.guardar') }}" class="space-y-5" data-formulario-campo>
                @csrf @if($campo) @method('PUT') @endif
                <p class="text-sm">{{ $campo ? 'Edita la etiqueta, las opciones y la visibilidad. El módulo y el tipo se conservan con el historial.' : 'Elige dónde se usará el campo, qué dato acepta y cómo se llamará en los formularios.' }}</p>
                <fieldset class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <legend class="mb-2 font-semibold">Datos del campo</legend>
                    <label class="text-sm font-semibold">Módulo (obligatorio)
                        @if($campo)<input type="hidden" name="modulo" value="{{ $campo->modulo }}">@endif
                        <select aria-label="Módulo" name="{{ $campo ? '' : 'modulo' }}" @disabled($campo) required class="mt-1 w-full rounded-xl border-slate-300">
                            @foreach(\App\Sistema\Modulos::DEFINICIONES as $key => $spec)<option value="{{ $key }}" @selected(old('modulo', $campo?->modulo ?? request('modulo')) === $key)>{{ $spec[0] }}</option>@endforeach
                        </select>
                    </label>
                    <label for="campo-etiqueta" class="text-sm font-semibold">Nombre del campo (obligatorio)
                        <input id="campo-etiqueta" name="etiqueta" value="{{ old('etiqueta', $campo?->etiqueta) }}" maxlength="100" required class="mt-1 w-full">
                    </label>
                    <label class="text-sm font-semibold">Tipo de dato (obligatorio)
                        @if($campo)<input type="hidden" name="tipo" value="{{ $campo->tipo }}">@endif
                        <select id="sistema-tipo-campo" aria-label="Tipo de dato" name="{{ $campo ? '' : 'tipo' }}" @disabled($campo) required class="mt-1 w-full rounded-xl border-slate-300">
                            @foreach(\App\Sistema\ValoresCampos::TIPOS as $key => $etiqueta)<option value="{{ $key }}" @selected($tipo === $key)>{{ $etiqueta }}</option>@endforeach
                        </select>
                    </label>
                    <label data-default-texto @if($esLista || $esBooleano || $tipo === 'archivo') hidden @endif class="text-sm font-semibold">Valor predeterminado
                        <input name="predeterminado" @disabled($esLista || $esBooleano || $tipo === 'archivo') value="{{ $defaultSimple }}" class="mt-1 w-full">
                    </label>
                    <label data-default-booleano @unless($esBooleano) hidden @endunless class="text-sm font-semibold">Valor predeterminado
                        <select name="predeterminado" @disabled(!$esBooleano) class="mt-1 w-full">
                            <option value="">Sin valor predeterminado</option>
                            <option value="1" @selected($defaultSimple === '1')>Sí</option>
                            <option value="0" @selected($defaultSimple === '0')>No</option>
                        </select>
                    </label>
                    <label class="text-sm font-semibold sm:col-span-2 lg:col-span-3">Instrucciones para quien complete este campo<textarea name="ayuda" maxlength="500" rows="2" class="mt-1 w-full rounded-xl border-slate-300">{{ old('ayuda', $campo?->ayuda) }}</textarea></label>
                </fieldset>
                <section id="sistema-opciones-panel" @unless($esLista) hidden @endunless>
                    <h2 class="font-bold">Opciones de la lista</h2>
                    <p class="mb-3 text-sm">Desactiva una opción para retirarla de nuevas selecciones. Sus valores anteriores se conservan.</p>
                    <div id="sistema-opciones" class="space-y-2">
                        @foreach($opciones as $i => $opcion)
                            @include('sistema.opcion', compact('i', 'opcion'))
                        @endforeach
                    </div>
                    <template id="sistema-opcion-plantilla">@include('sistema.opcion', ['i' => 'NUEVA_OPCION', 'opcion' => ['activo' => true]])</template>
                    <button type="button" id="sistema-agregar-opcion" class="ui-btn-secondary mt-3">Agregar opción</button>
                    <label data-default-lista-etiqueta class="mt-4 block max-w-md text-sm font-semibold" for="sistema-default-lista">Valor predeterminado</label>
                    <select id="sistema-default-lista" name="seleccion_predeterminada[]" aria-describedby="sistema-default-ayuda"
                            class="mt-1 block w-full max-w-md" @disabled(!$esLista) @if($tipo === 'multiple') multiple size="4" @endif>
                        @unless($tipo === 'multiple')<option value="">Sin valor predeterminado</option>@endunless
                        @foreach($opciones as $i => $opcion)
                            @if(!blank($opcion['etiqueta'] ?? null) && !empty($opcion['activo']))
                                <option value="{{ $i }}" @selected(in_array($i, (array) $seleccion))>{{ $opcion['etiqueta'] }}</option>
                            @endif
                        @endforeach
                    </select>
                    <fieldset data-default-multiple aria-describedby="sistema-default-ayuda" class="mt-4" hidden>
                        <legend class="mb-1 text-sm font-semibold">Valores predeterminados</legend>
                        <div data-default-opciones class="sistema-opciones"></div>
                    </fieldset>
                    <p id="sistema-default-ayuda" class="mt-1 text-xs">Selecciona entre las opciones activas.</p>
                    <button type="button" data-limpiar-default class="ui-btn-secondary mt-2" hidden>Quitar selección</button>
                </section>
                <fieldset class="rounded-lg border border-slate-200 p-3">
                    <legend class="px-1 text-sm font-bold">Dónde aparece el campo</legend>
                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach(['activo' => 'Activo', 'obligatorio' => 'Obligatorio en registros nuevos', 'formulario' => 'Mostrar en formularios', 'tabla' => 'Mostrar en tabla', 'filtro' => 'Habilitar filtro', 'exportacion' => 'Incluir al exportar'] as $key => $titulo)
                            <x-sistema.casilla :name="$key" :id="'campo-'.$key" :marcada="old($key, $campo?->{$key} ?? in_array($key, ['activo','formulario']))">{{ $titulo }}</x-sistema.casilla>
                        @endforeach
                    </div>
                    <p class="mt-2 text-xs">Marca los lugares donde quieres usarlo. Un campo obligatorio debe mostrarse en el formulario. Los textos largos y los archivos no admiten filtros.</p>
                </fieldset>
                <details class="rounded-lg border border-slate-200" @if($errors->has('clave') || $errors->has('current_password') || $errors->has('permiso_lectura') || $errors->has('permiso_edicion')) open @endif>
                    <summary class="sistema-desplegable">Opciones avanzadas</summary>
                    <div class="sistema-formulario p-3">
                        <label for="campo-identificador" class="text-sm font-semibold">Identificador del campo
                            <input id="campo-identificador" name="clave" value="{{ old('clave', $campo?->clave) }}" pattern="[a-z][a-z0-9_]{1,59}" @readonly($campo) class="mt-1 w-full">
                            <span class="mt-1 block text-xs font-normal">{{ $campo ? 'Se conserva para identificar los valores históricos.' : 'Si lo dejas vacío, se genera a partir de la etiqueta. Puedes usar letras minúsculas, números o guion bajo.' }}</span>
                        </label>
                        <label class="text-sm font-semibold">Posición en el módulo
                            <input name="orden" type="number" min="0" max="65000" value="{{ old('orden', $campo?->orden ?? 0) }}" required class="mt-1 w-full">
                            <span class="mt-1 block text-xs font-normal">Las posiciones menores aparecen primero.</span>
                        </label>
                    @if(app(\App\Sistema\Permisos::class)->esSuperadministrador(auth()->user()))
                    @foreach(['permiso_lectura' => 'Permiso adicional de lectura', 'permiso_edicion' => 'Permiso adicional de edición'] as $key => $titulo)
                        <label class="text-sm font-semibold">{{ $titulo }}
                            <select name="{{ $key }}" class="mt-1 w-full rounded-xl border-slate-300">
                                <option value="">Usar acceso del módulo</option>
                                @foreach($permisos as $valor)<option value="{{ $valor }}" @selected(old($key, $campo?->{$key}) === $valor)>{{ \App\Sistema\CatalogoPermisos::presentacion($valor)['titulo'] }}</option>@endforeach
                            </select>
                        </label>
                    @endforeach
                        <label class="text-sm font-semibold">Contraseña actual si cambia el acceso
                            <input name="current_password" type="password" autocomplete="current-password" class="mt-1 w-full rounded-xl border-slate-300">
                        </label>
                    @endif
                    </div>
                </details>
                <button class="ui-btn-primary" type="submit">Guardar campo</button>
            </form>
        @endif
    </x-sistema.panel>
    @if($campo && !$campo->archivado_en)
        <form method="POST" action="{{ route('sistema.campos.archivar', $campo) }}" data-confirmar="¿Archivar este campo? Dejará de mostrarse en nuevos registros y se conservarán sus valores históricos.">
            @csrf @method('PATCH')
            <button class="ui-btn-danger" type="submit">Archivar campo</button>
        </form>
    @endif
</x-sistema.pagina>

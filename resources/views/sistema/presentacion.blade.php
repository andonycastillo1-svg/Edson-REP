<x-sistema.pagina titulo="Personalización de formularios y tablas" :volver="route('sistema.configuracion')">
    <x-sistema.panel :titulo="$definicion[0]">
        <form method="GET" class="mb-5 flex flex-wrap gap-2">
            <select name="modulo" aria-label="Módulo" class="rounded-xl border-slate-300">
                @foreach(\App\Sistema\Modulos::DEFINICIONES as $key => $spec)<option value="{{ $key }}" @selected($modulo === $key)>{{ $spec[0] }}</option>@endforeach
            </select>
            <button class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-semibold">Cambiar módulo</button>
        </form>
        <form method="POST" action="{{ route('sistema.presentacion.guardar', $modulo) }}" class="space-y-5">
            @csrf @method('PUT')
            <div class="ui-table-scroll" tabindex="0" role="region" aria-label="Configuración de columnas">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-100"><tr><th class="p-3">Columna</th><th class="p-3">Etiqueta</th><th class="p-3">Orden</th><th class="p-3">Visible</th><th class="p-3">Filtro</th><th class="p-3">Búsqueda</th></tr></thead>
                    <tbody class="divide-y divide-slate-200">
                        @foreach($definicion[3] as $key => $etiqueta)
                            @php($col = (array) old('columnas.'.$key, $presentacion['columnas'][$key] ?? []))
                            <tr>
                                <td class="p-3">{{ $etiqueta }}</td>
                                <td class="p-3"><input name="columnas[{{ $key }}][etiqueta]" aria-label="Etiqueta de {{ $etiqueta }}" value="{{ $col['etiqueta'] ?? $etiqueta }}" maxlength="100" class="rounded-xl border-slate-300"></td>
                                <td class="p-3"><input type="number" name="columnas[{{ $key }}][orden]" aria-label="Orden de {{ $etiqueta }}" value="{{ $col['orden'] ?? $loop->index }}" min="0" max="65000" class="w-20 rounded-xl border-slate-300"></td>
                                @foreach(['visible' => true, 'filtro' => false, 'buscar' => false] as $flag => $default)
                                    <td class="p-3">
                                        @if($flag === 'visible' && in_array($key, \App\Sistema\Modulos::esenciales($modulo), true))
                                            <input type="hidden" name="columnas[{{ $key }}][{{ $flag }}]" value="1">Siempre
                                        @else
                                            <input type="hidden" name="columnas[{{ $key }}][{{ $flag }}]" value="0">
                                            <input type="checkbox" name="columnas[{{ $key }}][{{ $flag }}]" aria-label="{{ $flag }} · {{ $etiqueta }}" value="1" @checked($col[$flag] ?? $default)>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="grid gap-3 sm:grid-cols-3">
                <label class="text-sm font-semibold">Registros por página
                    <select name="pagina" class="mt-1 w-full rounded-xl border-slate-300">
                        @foreach([10,15,20,30,50,100] as $numero)<option value="{{ $numero }}" @selected($numero === (int) old('pagina', $presentacion['pagina'] ?? 20))>{{ $numero }}</option>@endforeach
                    </select>
                </label>
                <label class="text-sm font-semibold">Ordenar por
                    <select name="ordenar" class="mt-1 w-full rounded-xl border-slate-300">
                        @foreach($definicion[3] as $key => $etiqueta)<option value="{{ $key }}" @selected($key === old('ordenar', $presentacion['ordenar'] ?? $definicion[2]))>{{ $etiqueta }}</option>@endforeach
                    </select>
                </label>
                <label class="text-sm font-semibold">Dirección
                    <select name="direccion" class="mt-1 w-full rounded-xl border-slate-300">
                        <option value="desc">Descendente</option><option value="asc" @selected(old('direccion', $presentacion['direccion'] ?? '') === 'asc')>Ascendente</option>
                    </select>
                </label>
            </div>
            @if($nativos)
                <h3 class="font-bold">Campos nativos configurables</h3>
                @foreach($nativos as $key => [$etiqueta])
                    @php($campo = (array) old('formulario.'.$key, $presentacion['formulario'][$key] ?? []))
                    <fieldset class="grid gap-3 rounded-xl bg-slate-50 p-3 sm:grid-cols-2">
                        <legend class="font-semibold">{{ $etiqueta }}</legend>
                        <label class="text-sm">Etiqueta<input name="formulario[{{ $key }}][etiqueta]" value="{{ $campo['etiqueta'] ?? $etiqueta }}" maxlength="100" class="mt-1 w-full rounded-xl border-slate-300"></label>
                        <label class="text-sm">Ayuda<input name="formulario[{{ $key }}][ayuda]" value="{{ $campo['ayuda'] ?? '' }}" maxlength="500" class="mt-1 w-full rounded-xl border-slate-300"></label>
                        <label class="text-sm">Orden<input type="number" name="formulario[{{ $key }}][orden]" value="{{ $campo['orden'] ?? $loop->index }}" min="0" class="mt-1 w-full rounded-xl border-slate-300"></label>
                        <div class="flex flex-wrap items-center gap-2 text-sm">
                            @foreach(['visible' => 'Visible', 'obligatorio' => 'Obligatorio para registros nuevos'] as $flag => $label)
                                <x-sistema.casilla :name="'formulario['.$key.']['.$flag.']'" :id="'presentacion-'.$key.'-'.$flag" :marcada="$campo[$flag] ?? $flag === 'visible'">{{ $label }}</x-sistema.casilla>
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach
            @endif
            <div class="flex flex-wrap gap-3">
                <button type="submit" class="ui-btn-primary">Guardar presentación</button>
                <button type="submit" name="restaurar" value="1" class="ui-btn-secondary" data-confirmar="¿Restaurar la configuración de formularios y tablas de este módulo para todas las cuentas?">Restaurar valores predeterminados</button>
            </div>
            <p class="text-sm text-slate-600">Los campos personalizados se incorporan a la tabla si tienen habilitada esa opción y el usuario puede consultarlos.</p>
        </form>
    </x-sistema.panel>
</x-sistema.pagina>

@php
    $retornoDatos = app(\App\Sistema\Modulos::class)->consultar(auth()->user(), $modulo)
        ? route('sistema.datos.lista', $modulo) : route('sistema.datos');
    $camposTabla = $personalizadas->mapWithKeys(function ($campo) use ($presentacion) {
        $ajuste = (array) old('campos.'.$campo->id, $presentacion['campos'][$campo->id] ?? []);
        return ['campo_'.$campo->id => [
            'id' => $campo->id, 'etiqueta' => $campo->etiqueta, 'orden' => $ajuste['orden'] ?? $campo->orden,
            'visible' => $ajuste['visible'] ?? $campo->tabla,
        ]];
    });
    $filasColumnas = collect($columnas)->merge($camposTabla)->sortBy('orden');
@endphp
<x-sistema.pagina titulo="Presentación de formularios y tablas" descripcion="Estos cambios se aplican a todas las cuentas del módulo seleccionado."
                 :volver="$desdeDatos ? $retornoDatos : route('sistema.configuracion')"
                 :configuracion="!$desdeDatos">
    @if($desdeDatos)<x-sistema.nav-datos :modulo="$modulo" configurar />@endif
    <x-sistema.panel>
        <x-sistema.elegir-modulo :modulos="\App\Sistema\Modulos::DEFINICIONES" :seleccionado="$modulo"
                                 :accion="route('sistema.presentacion')" texto="Cambiar módulo" :origen="$desdeDatos ? 'datos' : null" />
    </x-sistema.panel>

    <form method="POST" action="{{ route('sistema.presentacion.guardar', $modulo) }}" class="space-y-4" data-presentacion>
        @csrf @method('PUT')
        <x-sistema.panel :titulo="'Tabla de '.$definicion[0]">
            <p class="mb-3 text-sm">Ordena las columnas con las flechas o su posición. Las esenciales siempre se muestran. Los campos personalizados conservan los filtros y exportaciones de su definición. La búsqueda y los filtros nativos se aplican a columnas visibles.</p>
            <div class="space-y-2" data-lista-orden>
                @foreach($filasColumnas as $clave => $columna)
                    @if(isset($columna['id']))
                        <fieldset class="sistema-orden" data-fila-orden>
                            <legend class="px-1 text-sm font-bold">{{ $columna['etiqueta'] }}</legend>
                            <div class="space-y-2">
                                <p class="text-xs text-slate-600">Campo personalizado</p>
                                <x-sistema.casilla :name="'campos['.$columna['id'].'][visible]'" :id="'columna-visible-'.$clave"
                                    :marcada="$columna['visible']">Mostrar</x-sistema.casilla>
                            </div>
                            <x-sistema.control-orden :name="'campos['.$columna['id'].'][orden]'" :valor="$columna['orden']" :etiqueta="$columna['etiqueta']" />
                        </fieldset>
                        @continue
                    @endif
                    @php
                        $col = (array) old('columnas.'.$clave, $columna);
                        $etiqueta = $definicion[3][$clave];
                    @endphp
                    <fieldset class="sistema-orden" data-fila-orden>
                        <legend class="px-1 text-sm font-bold">{{ $etiqueta }}</legend>
                        <div class="space-y-2">
                            <label class="block max-w-md text-sm font-semibold">Etiqueta pública
                                <input name="columnas[{{ $clave }}][etiqueta]" aria-label="Etiqueta de {{ $etiqueta }}" value="{{ $col['etiqueta'] ?? $etiqueta }}" maxlength="100" class="mt-1 w-full">
                            </label>
                            <div class="flex flex-wrap gap-1">
                                @if($columna['esencial'])
                                    <input type="hidden" name="columnas[{{ $clave }}][visible]" value="1">
                                    <span class="ui-badge ui-badge-info">Siempre visible</span>
                                @else
                                    <x-sistema.casilla :name="'columnas['.$clave.'][visible]'" :id="'columna-visible-'.$clave" :marcada="$col['visible'] ?? true">Mostrar</x-sistema.casilla>
                                @endif
                                <x-sistema.casilla :name="'columnas['.$clave.'][filtro]'" :id="'columna-filtro-'.$clave" :marcada="$col['filtro'] ?? false">Permitir filtro</x-sistema.casilla>
                                <x-sistema.casilla :name="'columnas['.$clave.'][buscar]'" :id="'columna-buscar-'.$clave" :marcada="$col['buscar'] ?? false">Incluir en búsqueda</x-sistema.casilla>
                            </div>
                        </div>
                        <x-sistema.control-orden :name="'columnas['.$clave.'][orden]'" :valor="$col['orden'] ?? $columna['orden']" :etiqueta="$etiqueta" />
                    </fieldset>
                @endforeach
                <p class="sr-only" role="status" data-orden-aviso></p>
            </div>

            <fieldset class="mt-5 grid gap-3 sm:grid-cols-3">
                <legend class="mb-2 font-semibold">Vista inicial de la consulta</legend>
                <label for="pagina-datos" class="text-sm font-semibold">Registros por página
                    <select id="pagina-datos" name="pagina" class="mt-1 w-full">
                        @foreach([10, 15, 20, 30, 50, 100] as $numero)<option value="{{ $numero }}" @selected($numero === (int) old('pagina', $presentacion['pagina'] ?? 20))>{{ $numero }}</option>@endforeach
                    </select>
                </label>
                <label for="orden-datos" class="text-sm font-semibold">Ordenar por
                    <select id="orden-datos" name="ordenar" class="mt-1 w-full">
                        @foreach($columnas as $clave => $columna)<option value="{{ $clave }}" @selected($clave === old('ordenar', $presentacion['ordenar'] ?? $definicion[2]))>{{ $columna['etiqueta'] }}</option>@endforeach
                    </select>
                </label>
                <label for="direccion-datos" class="text-sm font-semibold">Sentido inicial
                    <select id="direccion-datos" name="direccion" class="mt-1 w-full">
                        <option value="asc" @selected(old('direccion', $presentacion['direccion'] ?? 'desc') === 'asc')>Ascendente</option>
                        <option value="desc" @selected(old('direccion', $presentacion['direccion'] ?? 'desc') === 'desc')>Descendente</option>
                    </select>
                </label>
            </fieldset>
        </x-sistema.panel>

        @if($formularios)
            <x-sistema.panel>
                <details @if($errors->has('formulario') || $errors->get('formulario.*')) open @endif>
                    <summary class="sistema-desplegable">Campos del formulario de {{ $definicion[0] }}</summary>
                    <p class="my-3 text-sm">Edita etiquetas y ayudas de los campos disponibles. Para exigir un campo en registros nuevos, déjalo visible.</p>
                    <div class="space-y-2" data-lista-orden>
                        @foreach($formularios as $clave => $original)
                            @php
                                $campo = (array) old('formulario.'.$clave, $original);
                                $etiqueta = $nativos[$clave][0];
                            @endphp
                            <fieldset class="sistema-orden" data-fila-orden>
                                <legend class="px-1 text-sm font-bold">{{ $etiqueta }}</legend>
                                <div class="space-y-2">
                                    <div class="sistema-formulario">
                                        <label class="text-sm font-semibold">Etiqueta
                                            <input name="formulario[{{ $clave }}][etiqueta]" value="{{ $campo['etiqueta'] ?? $etiqueta }}" maxlength="100" class="mt-1 w-full">
                                        </label>
                                        <label class="text-sm font-semibold">Ayuda
                                            <input name="formulario[{{ $clave }}][ayuda]" value="{{ $campo['ayuda'] ?? '' }}" maxlength="500" class="mt-1 w-full">
                                        </label>
                                    </div>
                                    <div class="flex flex-wrap gap-1">
                                        <x-sistema.casilla :name="'formulario['.$clave.'][visible]'" :id="'form-visible-'.$clave" :marcada="$campo['visible'] ?? true">Mostrar</x-sistema.casilla>
                                        <x-sistema.casilla :name="'formulario['.$clave.'][obligatorio]'" :id="'form-obligatorio-'.$clave" :marcada="$campo['obligatorio'] ?? false">Obligatorio en registros nuevos</x-sistema.casilla>
                                    </div>
                                </div>
                                <x-sistema.control-orden :name="'formulario['.$clave.'][orden]'" :valor="$campo['orden'] ?? $original['orden']" :etiqueta="$etiqueta" />
                            </fieldset>
                        @endforeach
                        <p class="sr-only" role="status" data-orden-aviso></p>
                    </div>
                </details>
            </x-sistema.panel>
        @endif
        <button type="submit" class="ui-btn-primary">Guardar presentación</button>
    </form>

    <x-sistema.panel titulo="Restaurar presentación">
        <p class="mb-3 text-sm">Vuelve a la configuración original de los formularios y listados de este módulo para todas las cuentas.</p>
        <form method="POST" action="{{ route('sistema.presentacion.guardar', $modulo) }}"
              data-confirmar="¿Restaurar la presentación de este módulo para todas las cuentas?">
            @csrf @method('PUT')
            <button type="submit" name="restaurar" value="1" class="ui-btn-secondary">Restaurar valores predeterminados</button>
        </form>
    </x-sistema.panel>
</x-sistema.pagina>

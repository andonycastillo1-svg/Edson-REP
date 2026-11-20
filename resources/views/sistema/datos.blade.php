@php
    $lectura = array_filter($columnas, fn ($columna) => $columna['buscar']);
    $filtrosNativos = array_filter($columnas, fn ($columna) => $columna['filtro']);
    $ruta = route($operativa ? 'sistema.operacion.campos' : 'sistema.datos.lista', $modulo);
    $consulta = request()->query();
    $criterios = request()->only('q', 'nativo', 'campo', 'orden', 'direccion');
    $lector = app(\App\Sistema\ValoresCampos::class);
    $acciones = app(\App\Sistema\Modulos::class);
@endphp
<x-sistema.pagina :titulo="$operativa ? 'Campos operativos · '.$nombre : $nombre" :volver="route($operativa ? 'dashboard' : 'sistema.datos')"
                 :descripcion="$operativa ? 'Selecciona un registro para editar sus campos personalizados.' : 'Consulta registros autorizados y genera reportes.'">
    @unless($operativa)<x-sistema.nav-datos :modulo="$modulo" />@endunless
    <x-sistema.panel>
        <div class="flex flex-wrap items-end justify-between gap-3">
            @unless($operativa)
                <x-sistema.elegir-modulo :modulos="$modulos" :seleccionado="$modulo" :accion="route('sistema.datos')" texto="Cambiar módulo" />
            @endunless
            @if($puedeExportar)
                <a href="{{ route('sistema.datos.exportar', ['modulo' => $modulo, ...$criterios]) }}"
                   class="ui-btn-secondary">Exportar resultados (CSV)</a>
            @endif
        </div>
        <form method="GET" action="{{ $ruta }}" aria-label="Buscar, filtrar y ordenar {{ $nombre }}" class="mt-4 space-y-3">
            <div class="grid items-end gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @if($lectura)
                    <label for="buscar-registros" class="min-w-0 text-sm font-semibold">Buscar registros
                        <input id="buscar-registros" type="search" name="q" value="{{ request('q') }}" maxlength="120" class="mt-1 w-full" placeholder="Escribe lo que buscas">
                    </label>
                @endif
                <label for="orden-registros" class="min-w-0 text-sm font-semibold">Ordenar por
                    <select id="orden-registros" name="orden" class="mt-1 w-full">
                        @foreach($columnas as $clave => $columna)<option value="{{ $clave }}" @selected($orden === $clave)>{{ $columna['etiqueta'] }}</option>@endforeach
                        @foreach($distribucion as $columna)
                            @if($columna['campo']?->ordenable())
                                @php $claveOrden = 'campo_'.$columna['clave']; @endphp
                                <option value="{{ $claveOrden }}" @selected($orden === $claveOrden)>{{ $columna['etiqueta'] }}</option>
                            @endif
                        @endforeach
                    </select>
                </label>
                <label for="sentido-registros" class="min-w-0 text-sm font-semibold">Sentido
                    <select id="sentido-registros" name="direccion" class="mt-1 w-full">
                        <option value="asc" @selected($direccion === 'asc')>Ascendente</option>
                        <option value="desc" @selected($direccion === 'desc')>Descendente</option>
                    </select>
                </label>
            </div>
            @if($filtrosNativos || $filtros->isNotEmpty())
                <details class="rounded-lg border border-slate-200" @if(!empty($consulta['nativo']) || !empty($consulta['campo'])) open @endif>
                    <summary class="sistema-desplegable">Filtros del módulo</summary>
                    <div class="grid gap-3 p-3 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach($filtrosNativos as $clave => $columna)
                            <label class="min-w-0 text-sm font-semibold">{{ $columna['etiqueta'] }}
                                @if(isset($opcionesNativas[$clave]))
                                    <select name="nativo[{{ $clave }}]" aria-label="{{ $columna['etiqueta'] }}" class="mt-1 w-full">
                                        <option value="">Cualquiera</option>
                                        @foreach($opcionesNativas[$clave] as $opcion)<option value="{{ $opcion }}" @selected(request('nativo.'.$clave) === $opcion)>{{ ucfirst($opcion) }}</option>@endforeach
                                    </select>
                                @else
                                    <input name="nativo[{{ $clave }}]" value="{{ request('nativo.'.$clave) }}" maxlength="190" class="mt-1 w-full">
                                @endif
                            </label>
                        @endforeach
                        <x-sistema.filtros-campos :filtros="$filtros" />
                    </div>
                </details>
            @endif
            <div class="flex flex-wrap gap-2">
                <button type="submit" class="ui-btn-primary">Aplicar consulta</button>
                @if($consulta)<a href="{{ $ruta }}" class="ui-btn-secondary">Restablecer consulta</a>@endif
            </div>
        </form>
    </x-sistema.panel>

    <x-sistema.panel titulo="Resultados">
        <p class="mb-3 text-sm">{{ number_format($registros->total(), thousands_separator: ',') }} {{ $registros->total() === 1 ? 'registro' : 'registros' }}</p>
        <div class="ui-table-scroll" tabindex="0" role="region" aria-label="Listado de {{ $nombre }}">
            <table class="ui-table">
                <thead><tr>
                    @foreach($distribucion as $columna)
                        @php
                            $claveOrden = $columna['campo'] ? 'campo_'.$columna['clave'] : $columna['clave'];
                            $ordenada = $claveOrden === $orden;
                            $sentido = $ordenada && $direccion === 'asc' ? 'desc' : 'asc';
                        @endphp
                        <th scope="col" @if($ordenada) aria-sort="{{ $direccion === 'asc' ? 'ascending' : 'descending' }}" @endif>
                            @if($operativa || ($columna['campo'] && !$columna['campo']->ordenable()))
                                {{ $columna['etiqueta'] }}
                            @else
                                <a class="inline-flex min-h-8 items-center gap-1 rounded text-blue-900 hover:underline"
                                   data-orden-columna="{{ $ordenada ? $direccion : '' }}"
                                   aria-label="Ordenar por {{ $columna['etiqueta'] }}, {{ $sentido === 'asc' ? 'ascendente' : 'descendente' }}"
                                   href="{{ route('sistema.datos.lista', ['modulo' => $modulo, ...$criterios, 'orden' => $claveOrden, 'direccion' => $sentido]) }}">
                                    {{ $columna['etiqueta'] }}
                                </a>
                            @endif
                        </th>
                    @endforeach
                    <th scope="col">{{ $operativa ? 'Acciones' : 'Detalle' }}</th>
                </tr></thead>
                <tbody>
                    @forelse($registros as $registro)
                        @php($actuales = $valores->get((string) $registro->{$id}) ?? collect())
                        <tr>
                            @foreach($distribucion as $columna)
                                @php($valor = $columna['campo'] ? $lector->mostrar($columna['campo'], $actuales->get($columna['clave'])) : \App\Sistema\Modulos::valorNativo($modulo, $registro, $columna['clave']))
                                <td>
                                    @if(!$columna['campo'] && $columna['clave'] === 'estado' && filled($valor))
                                        <span class="ui-badge ui-badge-info">{{ $valor }}</span>
                                    @else
                                        {{ filled($valor) ? $valor : '—' }}
                                    @endif
                                </td>
                            @endforeach
                            <td>
                                @unless($operativa)
                                    <a class="ui-btn-secondary ui-btn-sm whitespace-nowrap"
                                       href="{{ route('sistema.datos.ficha', ['modulo' => $modulo, 'registro' => $registro->{$id}, ...$criterios, ...request()->only('page')]) }}">Ver registro</a>
                                @else
                                    <div class="flex flex-wrap gap-2">
                                        @if($acciones->editar(auth()->user(), $modulo, $registro))
                                            <a class="ui-btn-secondary ui-btn-sm whitespace-nowrap" href="{{ route('sistema.operacion.campos.editar', [$modulo, $registro->{$id}]) }}">Editar campos</a>
                                        @endif
                                        @if($operativo = $acciones->enlaceOperativo(auth()->user(), $modulo, $registro))
                                            <a class="ui-btn-secondary ui-btn-sm whitespace-nowrap" href="{{ $operativo }}">Abrir módulo</a>
                                        @endif
                                    </div>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($distribucion) + 1 }}"><p class="ui-empty-state">No hay registros para esta consulta.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $registros->links() }}</div>
    </x-sistema.panel>
</x-sistema.pagina>

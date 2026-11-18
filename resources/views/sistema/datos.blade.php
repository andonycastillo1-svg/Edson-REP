<x-sistema.pagina :titulo="$nombre" :volver="route('sistema.datos')">
    <x-sistema.panel>
        <form method="GET" class="mb-4 flex flex-wrap items-end gap-3" aria-label="Filtros de {{ $nombre }}">
            @if(collect($columnas)->keys()->contains(fn ($c) => $presentacion[$c]['buscar'] ?? false))
                <label class="max-w-full text-sm font-semibold">Buscar<input type="search" name="q" value="{{ request('q') }}" maxlength="100" class="mt-1 block max-w-full"></label>
            @endif
            @foreach($columnas as $key => $etiqueta)
                @if($presentacion[$key]['filtro'] ?? false)
                    <label class="text-sm font-semibold">{{ $etiqueta }}<input name="nativo[{{ $key }}]" value="{{ request('nativo.'.$key) }}" class="mt-1 block rounded-xl border-slate-300"></label>
                @endif
            @endforeach
            @foreach($filtros as $campo)
                <label class="text-sm font-semibold">{{ $campo->etiqueta }}
                    @if(in_array($campo->tipo, ['lista','multiple']))
                        <select name="campo[{{ $campo->id }}]" class="mt-1 block rounded-xl border-slate-300">
                            <option value="">Todas las opciones</option>
                            @foreach($campo->opciones as $opcion)<option value="{{ $opcion->id }}" @selected(request('campo.'.$campo->id) == $opcion->id)>{{ $opcion->etiqueta }}</option>@endforeach
                        </select>
                    @elseif(in_array($campo->tipo, ['casilla','booleano']))
                        <select name="campo[{{ $campo->id }}]" class="mt-1 block rounded-xl border-slate-300">
                            <option value="">Cualquiera</option><option value="1" @selected(request('campo.'.$campo->id) === '1')>Sí</option><option value="0" @selected(request('campo.'.$campo->id) === '0')>No</option>
                        </select>
                    @else
                        <input name="campo[{{ $campo->id }}]" value="{{ request('campo.'.$campo->id) }}" class="mt-1 block rounded-xl border-slate-300">
                    @endif
                </label>
            @endforeach
            @if($filtros->isNotEmpty() || collect($columnas)->keys()->contains(fn ($c) => ($presentacion[$c]['buscar'] ?? false) || ($presentacion[$c]['filtro'] ?? false)))
                <button type="submit" class="ui-btn-primary">Aplicar filtros</button>
                <a href="{{ route('sistema.datos.lista', $modulo) }}" class="ui-btn-secondary">Limpiar filtros</a>
            @endif
            @if($puedeExportar)
                <a href="{{ route('sistema.datos.exportar', ['modulo' => $modulo, ...request()->only('q','nativo','campo')]) }}" class="ui-btn-secondary sm:ml-auto">Exportar CSV</a>
            @endif
        </form>
        @php($lector = app(\App\Sistema\ValoresCampos::class))
        @php($enlaces = app(\App\Sistema\Modulos::class))
        <div class="ui-table-scroll" tabindex="0" role="region" aria-label="Listado de {{ $nombre }}">
            <table class="ui-table">
                <thead class="bg-slate-100">
                    <tr>
                        @foreach($distribucion as $columna)<th scope="col">{{ $columna['etiqueta'] }}</th>@endforeach
                        <th scope="col">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($registros as $registro)
                        <tr>
                            @php($actuales = $valores->get((string) $registro->{$id}) ?? collect())
                            @foreach($distribucion as $columna)
                                @php($valor = $columna['campo'] ? $lector->mostrar($columna['campo'], $actuales->get($columna['clave'])) : $registro->{$columna['clave']})
                                <td>
                                    @if(!$columna['campo'] && $columna['clave'] === 'estado' && filled($valor))
                                        <span class="ui-badge ui-badge-info">{{ $valor }}</span>
                                    @else
                                        {{ filled($valor) ? $valor : '—' }}
                                    @endif
                                </td>
                            @endforeach
                            <td>
                                <div class="flex flex-wrap gap-2">
                                    <a class="ui-btn-secondary ui-btn-sm" href="{{ route('sistema.datos.ficha', [$modulo, $registro->{$id}]) }}">Ver campos</a>
                                    @if($operativo = $enlaces->enlaceOperativo(auth()->user(), $modulo, $registro))
                                        <a class="ui-btn-secondary ui-btn-sm" href="{{ $operativo }}">Abrir módulo</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty<tr><td colspan="{{ count($distribucion) + 1 }}"><p class="ui-empty-state">No hay registros para estos filtros.</p></td></tr>@endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $registros->links() }}</div>
    </x-sistema.panel>
</x-sistema.pagina>

<x-sistema.pagina :titulo="$nombre" :volver="route('sistema.datos')">
    <x-sistema.panel>
        <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
            @if(collect($columnas)->keys()->contains(fn ($c) => $presentacion[$c]['buscar'] ?? false))
                <label class="text-sm font-semibold">Buscar<input name="q" value="{{ request('q') }}" class="mt-1 block rounded-xl border-slate-300"></label>
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
                <button class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-semibold">Aplicar filtros</button>
                <a href="{{ route('sistema.datos.lista', $modulo) }}" class="px-2 py-2 text-sm font-semibold text-blue-700">Limpiar</a>
            @endif
            @if($puedeExportar)
                <a href="{{ route('sistema.datos.exportar', ['modulo' => $modulo, ...request()->only('q','nativo','campo')]) }}" class="ml-auto rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-blue-700">Exportar CSV</a>
            @endif
        </form>
        @php($lector = app(\App\Sistema\ValoresCampos::class))
        @php($enlaces = app(\App\Sistema\Modulos::class))
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-100">
                    <tr>
                        @foreach($distribucion as $columna)<th class="p-3">{{ $columna['etiqueta'] }}</th>@endforeach
                        <th class="p-3">Detalles</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($registros as $registro)
                        <tr>
                            @php($actuales = $valores->get((string) $registro->{$id}) ?? collect())
                            @foreach($distribucion as $columna)
                                <td class="p-3">{{ $columna['campo'] ? $lector->mostrar($columna['campo'], $actuales->get($columna['clave'])) : $registro->{$columna['clave']} }}</td>
                            @endforeach
                            <td class="flex flex-wrap gap-3 p-3">
                                <a class="whitespace-nowrap font-semibold text-blue-700" href="{{ route('sistema.datos.ficha', [$modulo, $registro->{$id}]) }}">Ver campos</a>
                                @if($operativo = $enlaces->enlaceOperativo(auth()->user(), $modulo, $registro))
                                    <a class="whitespace-nowrap font-semibold text-blue-700" href="{{ $operativo }}">Abrir módulo</a>
                                @endif
                            </td>
                        </tr>
                    @empty<tr><td colspan="{{ count($columnas) + $campos->count() + 1 }}" class="p-3 text-slate-600">No hay registros para estos filtros.</td></tr>@endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $registros->links() }}</div>
    </x-sistema.panel>
</x-sistema.pagina>

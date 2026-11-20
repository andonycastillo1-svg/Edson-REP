<x-sistema.pagina titulo="Inspecciones y requerimientos" :volver="route('dashboard')">
    @include('flujo.accesos')
    @php($acceso = app(\App\Services\FlujoAcceso::class))
    @if($acceso->puede(auth()->user(), 'capturar'))
        <a href="{{ route('flujo.inspecciones.create') }}" class="ui-btn-primary">Nueva inspección</a>
    @endif
    <x-sistema.panel titulo="Buscar requerimientos">
        <form class="grid items-end gap-3 md:grid-cols-3 lg:grid-cols-6" method="GET">
            <label class="text-sm">Desde<input class="mt-1 block w-full rounded-xl border-slate-300" type="date" name="desde" value="{{ request('desde') }}"></label>
            <label class="text-sm">Hasta<input class="mt-1 block w-full rounded-xl border-slate-300" type="date" name="hasta" value="{{ request('hasta') }}"></label>
            <label class="text-sm">Región
                <select name="region" class="mt-1 block w-full rounded-xl border-slate-300">
                    <option value="">Todas</option>
                    @foreach($regiones as $region)
                        <option value="{{ $region->id }}" @selected((int) request('region') === $region->id)>{{ $region->nombre }}</option>
                    @endforeach
                </select>
            </label>
            @foreach(['supervisor' => ['Supervisor', $supervisores], 'coordinador' => ['Coordinador', $coordinadores]] as $campo => [$label, $ids])
                @if($ids->count() > 1)
                    <label class="text-sm">{{ $label }}
                        <select name="{{ $campo }}" class="mt-1 block w-full rounded-xl border-slate-300">
                            <option value="">Todos</option>
                            @foreach($ids as $persona)
                                <option value="{{ $persona }}" @selected((int) request($campo) === (int) $persona)>{{ $personas[$persona]->name ?? 'Usuario' }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
            @endforeach
            <button class="ui-btn-primary">Filtrar</button>
        </form>
    </x-sistema.panel>
    @php($puedeConsolidar = $acceso->puede(auth()->user(), 'consolidar'))
    @if($puedeConsolidar)
        <x-sistema.panel titulo="Preparar consolidado">
            <p class="mb-3 text-sm">Selecciona requerimientos de un mismo equipo en la tabla.</p>
            <form method="POST" action="{{ route('flujo.consolidados.store') }}" id="consolidar" class="grid items-end gap-3 sm:grid-cols-4">
                @csrf
                <label class="text-sm">Nombre del período<input name="nombre" maxlength="160" class="mt-1 block w-full rounded-xl border-slate-300" value="{{ old('nombre') }}" required></label>
                <label class="text-sm">Desde<input name="desde" type="date" class="mt-1 block w-full rounded-xl border-slate-300" value="{{ old('desde', request('desde')) }}" required></label>
                <label class="text-sm">Hasta<input name="hasta" type="date" class="mt-1 block w-full rounded-xl border-slate-300" value="{{ old('hasta', request('hasta')) }}" required></label>
                <button class="ui-btn-primary">Crear borrador</button>
            </form>
        </x-sistema.panel>
    @endif
    <x-sistema.panel titulo="Requerimientos">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50"><tr>
                    @if($puedeConsolidar)<th class="p-3" scope="col">Incluir</th>@endif
                    <th class="p-3" scope="col">Fecha</th><th class="p-3" scope="col">Colaborador</th><th class="p-3" scope="col">Región</th>
                    <th class="p-3" scope="col">Supervisor</th><th class="p-3" scope="col">Coordinador</th><th class="p-3" scope="col">Estado</th>
                </tr></thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($inspecciones as $fila)
                        @php($consolidado = $fila->consolidados->first())
                        <tr>
                            @if($puedeConsolidar)
                                <td class="p-3">
                                    @unless($consolidado)
                                        <input type="checkbox" name="inspecciones[]" form="consolidar" value="{{ $fila->id }}"
                                               aria-label="Incluir inspección {{ $fila->id }}" @checked(in_array($fila->id, old('inspecciones', [])))>
                                    @endunless
                                </td>
                            @endif
                            <td class="p-3">{{ $fila->fecha->format('d/m/Y') }}</td>
                            <td class="p-3"><a class="font-semibold underline" href="{{ route('flujo.inspecciones.show', $fila) }}">{{ $fila->colaborador?->nombre }}</a></td>
                            <td class="p-3">{{ $fila->region?->nombre }}</td>
                            <td class="p-3">{{ $fila->supervisor?->name }}</td>
                            <td class="p-3">{{ $fila->supervisor?->coordinador?->name ?? 'Sin asignar' }}</td>
                            <td class="p-3">{{ $consolidado ? \App\Models\Consolidado::ESTADOS[$consolidado->estado] : 'Recibido' }}</td>
                        </tr>
                    @empty
                        <tr><td class="p-3" colspan="{{ $puedeConsolidar ? 7 : 6 }}">No hay requerimientos con estos filtros.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $inspecciones->links() }}</div>
    </x-sistema.panel>
</x-sistema.pagina>

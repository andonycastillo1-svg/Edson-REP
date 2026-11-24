@php
    $acceso = app(\App\Services\FlujoAcceso::class);
    $puedeConsolidar = $acceso->puedeCortes(auth()->user());
@endphp
<x-sistema.pagina titulo="Inspecciones y requerimientos" :descripcion="$puedeConsolidar ? 'Consulta los requerimientos recibidos y prepara un corte cuando lo necesites.' : 'Registra las necesidades de cada colaborador y consulta su avance.'" :volver="route('dashboard')">
    <x-slot:acciones>
        @if($puedeConsolidar)
            <a class="ui-btn-primary" href="{{ route('flujo.cortes.create') }}">Nuevo corte</a>
            <a class="ui-btn-secondary" href="{{ route('flujo.cortes.index') }}">Historial de cortes</a>
        @endif
        <x-ui.exportar-excel />
        @if($acceso->puede(auth()->user(), 'capturar'))
            <a href="{{ route('flujo.inspecciones.create') }}" class="ui-btn-primary">Nueva inspección</a>
        @endif
    </x-slot:acciones>
    @include('flujo.accesos')
    <details class="flujo-lectura" @if(request()->anyFilled(['desde', 'hasta', 'region', 'supervisor', 'coordinador'])) open @endif>
        <summary>Filtrar requerimientos</summary>
        <form class="grid items-end gap-3 p-4 sm:grid-cols-2 lg:grid-cols-3" method="GET">
            <label class="ui-label">Desde<input class="ui-input mt-1" type="date" name="desde" value="{{ request('desde') }}"></label>
            <label class="ui-label">Hasta<input class="ui-input mt-1" type="date" name="hasta" value="{{ request('hasta') }}"></label>
            <label class="ui-label">Región
                <select name="region" class="ui-input mt-1">
                    <option value="">Todas</option>
                    @foreach($regiones as $region)
                        <option value="{{ $region->id }}" @selected((int) request('region') === $region->id)>{{ $region->nombre }}</option>
                    @endforeach
                </select>
            </label>
            @foreach(['supervisor' => ['Supervisor', $supervisores], 'coordinador' => ['Coordinador', $coordinadores]] as $campo => [$etiqueta, $ids])
                @if($ids->count() > 1 || ($puedeConsolidar && $campo === 'coordinador' && $ids->isNotEmpty()))
                    <label class="ui-label">{{ $etiqueta }}
                        <select name="{{ $campo }}" class="ui-input mt-1">
                            <option value="">Todos</option>
                            @foreach($ids as $persona)
                                <option value="{{ $persona }}" @selected((int) request($campo) === (int) $persona)>{{ $personas[$persona]->name ?? 'Usuario' }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
            @endforeach
            <div class="flex flex-wrap gap-2">
                <button class="ui-btn-primary">Aplicar filtros</button>
                <a class="flujo-tab" href="{{ route('flujo.inspecciones.index') }}">Limpiar</a>
            </div>
        </form>
    </details>
    <x-sistema.panel>
        <div class="flujo-cabecera-panel">
            <h2 class="text-lg font-bold">Requerimientos registrados</h2>
            <span class="ui-badge ui-badge-info">{{ $inspecciones->total() }} resultados</span>
        </div>
        <ul class="divide-y divide-slate-200" aria-label="Requerimientos">
            @forelse($inspecciones as $fila)
                @php($consolidado = $fila->consolidados->first())
                @php($responsable = $coordinadores->contains($fila->region?->coordinador_id) && $fila->region?->activa ? $fila->region?->coordinador : null)
                <li class="grid grid-cols-[minmax(0,1fr)_auto] items-start gap-3 py-3 sm:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)_auto] sm:items-center">
                    <div class="flex min-w-0 items-start gap-3">
                        <div class="min-w-0">
                            <a class="flujo-enlace" href="{{ route('flujo.inspecciones.show', $fila) }}">{{ $fila->colaborador?->nombre }}</a>
                            <p class="mt-1 text-sm">{{ $fila->fecha->format('d/m/Y') }} · {{ $fila->region?->nombre }}</p>
                        </div>
                    </div>
                    <p class="col-start-1 row-start-2 text-sm sm:col-start-2 sm:row-start-1">
                        {{ $fila->supervisor?->name }}
                        @if($puedeConsolidar)<span class="block text-xs">{{ $responsable ? 'Coordina '.$responsable->name : 'Región sin coordinador activo' }}</span>@endif
                    </p>
                    <div class="col-start-2 row-start-1 sm:col-start-3">
                        @include('flujo.estado', ['estado' => $consolidado?->estado ?? 'recibido'])
                    </div>
                </li>
            @empty
                <li class="py-2 text-sm">No hay requerimientos con estos filtros.</li>
            @endforelse
        </ul>
        <div class="mt-4">{{ $inspecciones->links() }}</div>
    </x-sistema.panel>
</x-sistema.pagina>

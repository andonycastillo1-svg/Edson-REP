@php
    $acceso = app(\App\Services\FlujoAcceso::class);
    $puedeConsolidar = $acceso->puede(auth()->user(), 'consolidar');
@endphp
<x-sistema.pagina titulo="Inspecciones y requerimientos" :descripcion="$puedeConsolidar ? 'Selecciona requerimientos de un mismo equipo para preparar su consolidado.' : 'Registra las necesidades de cada colaborador y consulta su avance.'" :volver="route('dashboard')">
    <x-slot:acciones>
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
                @if($ids->count() > 1)
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
                <li class="grid grid-cols-[minmax(0,1fr)_auto] items-start gap-3 py-3 sm:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)_auto] sm:items-center">
                    <div class="flex min-w-0 items-start gap-3">
                        @if($puedeConsolidar && !$consolidado)
                            <label class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-50">
                                <input type="checkbox" name="inspecciones[]" form="consolidar" value="{{ $fila->id }}" data-fecha="{{ $fila->fecha->toDateString() }}"
                                    aria-label="Incluir requerimiento de {{ $fila->colaborador?->nombre }} del {{ $fila->fecha->format('d/m/Y') }}"
                                    @checked(in_array($fila->id, old('inspecciones', [])))>
                            </label>
                        @endif
                        <div class="min-w-0">
                            <a class="flujo-enlace" href="{{ route('flujo.inspecciones.show', $fila) }}">{{ $fila->colaborador?->nombre }}</a>
                            <p class="mt-1 text-sm">{{ $fila->fecha->format('d/m/Y') }} · {{ $fila->region?->nombre }}</p>
                        </div>
                    </div>
                    <p class="col-start-1 row-start-2 text-sm sm:col-start-2 sm:row-start-1">
                        {{ $fila->supervisor?->name }}
                        @if($puedeConsolidar)<span class="block text-xs">Coordina {{ $fila->supervisor?->coordinador?->name ?? 'sin asignar' }}</span>@endif
                    </p>
                    <div class="col-start-2 row-start-1 sm:col-start-3">
                        @include('flujo.estado', ['estado' => $consolidado?->estado ?? 'recibido'])
                    </div>
                </li>
            @empty
                <li class="py-2 text-sm">No hay requerimientos con estos filtros.</li>
            @endforelse
        </ul>
        @if($puedeConsolidar && $inspecciones->contains(fn ($fila) => $fila->consolidados->isEmpty()))
            <form method="POST" action="{{ route('flujo.consolidados.store') }}" id="consolidar" class="mt-4 rounded-xl border border-blue-200 bg-blue-50 p-4">
                @csrf
                <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                    <h3 class="font-bold">Preparar consolidado</h3>
                    <p id="inspecciones-seleccionadas" role="status" class="text-sm"></p>
                </div>
                <div class="grid items-end gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <label class="ui-label">Nombre del período<input name="nombre" maxlength="160" class="ui-input mt-1" value="{{ old('nombre') }}" required></label>
                    <label class="ui-label">Desde<input name="desde" type="date" class="ui-input mt-1" value="{{ old('desde', request('desde')) }}" required></label>
                    <label class="ui-label">Hasta<input name="hasta" type="date" class="ui-input mt-1" value="{{ old('hasta', request('hasta')) }}" required></label>
                    <button class="ui-btn-primary">Crear borrador</button>
                </div>
            </form>
            <script>
                (() => {
                    const formulario = document.getElementById('consolidar');
                    const casillas = [...formulario.elements].filter(campo => campo.name === 'inspecciones[]');
                    const total = document.getElementById('inspecciones-seleccionadas');
                    const periodo = [formulario.elements.desde, formulario.elements.hasta];
                    const automatico = periodo.every(campo => !campo.value);
                    function actualizar() {
                        const fechas = casillas.filter(casilla => casilla.checked).map(casilla => casilla.dataset.fecha).sort();
                        total.textContent = fechas.length + (fechas.length === 1 ? ' seleccionado' : ' seleccionados');
                        formulario.querySelector('button').disabled = !fechas.length;
                        if (automatico) periodo.forEach((campo, i) => {
                            if (!campo.dataset.manual) campo.value = (i ? fechas.at(-1) : fechas[0]) ?? '';
                        });
                    }
                    periodo.forEach(campo => campo.addEventListener('change', () => campo.dataset.manual = '1'));
                    casillas.forEach(casilla => casilla.addEventListener('change', actualizar));
                    actualizar();
                })();
            </script>
        @endif
        <div class="mt-4">{{ $inspecciones->links() }}</div>
    </x-sistema.panel>
</x-sistema.pagina>

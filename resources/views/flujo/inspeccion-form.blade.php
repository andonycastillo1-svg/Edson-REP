<x-sistema.pagina titulo="Nueva inspección" descripcion="Selecciona al colaborador y registra únicamente los productos que necesita." :volver="route('flujo.inspecciones.index')">
    @include('flujo.accesos')
    @if(!$tieneCoordinador)
        <p class="ui-alert-warning">Pide a Administración que asigne un coordinador a tu cuenta para registrar inspecciones.</p>
    @else
        <form action="{{ route('flujo.inspecciones.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <input type="hidden" name="solicitud_id" value="{{ old('solicitud_id', (string) \Illuminate\Support\Str::uuid()) }}">
            <x-sistema.panel titulo="Datos de la inspección">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <p class="text-sm"><strong>Supervisor</strong><br>{{ auth()->user()->name }}</p>
                    <label class="text-sm font-semibold">Fecha<input class="ui-input mt-1" type="date" name="fecha" value="{{ old('fecha', today()->toDateString()) }}" required></label>
                    <label class="text-sm font-semibold">Región
                        <select id="inspeccion-region" name="region_id" class="ui-input mt-1" required>
                            <option value="">Selecciona una región</option>
                            @foreach($regiones as $region)
                                <option value="{{ $region->id }}" data-url="{{ route('flujo.inspecciones.colaboradores', $region) }}" @selected((int) old('region_id') === $region->id)>{{ $region->nombre }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="text-sm font-semibold">Colaborador
                        <select id="inspeccion-persona" name="colaborador_codigo" class="ui-input mt-1" required disabled>
                            <option value="">Selecciona primero una región</option>
                        </select>
                    </label>
                    <label class="text-sm font-semibold">Puesto
                        <input id="inspeccion-puesto" class="mt-1 block w-full rounded-xl border-slate-300 bg-slate-50" readonly>
                    </label>
                    <p id="inspeccion-estado" class="text-sm text-slate-600" role="status"></p>
                </div>
            </x-sistema.panel>
            <x-sistema.panel titulo="Productos solicitados">
                <p class="mb-3 text-sm">Indica las cantidades por categoría. Deja en cero los artículos que no se necesitan.</p>
                <div class="space-y-2">
                    @forelse($secciones->filter(fn ($seccion) => $articulos->has($seccion->clave)) as $seccion)
                        <details class="flujo-lectura" @if($loop->first || old('cantidades')) open @endif>
                            <summary>{{ $seccion->nombre }}</summary>
                            <div class="grid gap-2 p-3 sm:grid-cols-2">
                                @foreach($articulos->get($seccion->clave) as $articulo)
                                    <label class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 p-3 text-sm">
                                        <span><strong>{{ $articulo->etiqueta }}</strong>
                                            @if($articulo->descripcion)<span class="block text-slate-600">{{ $articulo->descripcion }}</span>@endif
                                        </span>
                                        <input type="number" name="cantidades[{{ $articulo->id }}]" min="0" step="1"
                                               aria-label="Cantidad de {{ $articulo->etiqueta }}" value="{{ old('cantidades.'.$articulo->id, 0) }}" class="w-24 rounded-xl border-slate-300">
                                    </label>
                                @endforeach
                            </div>
                        </details>
                    @empty
                        <p class="text-sm">Todavía no hay artículos configurados.</p>
                    @endforelse
                </div>
            </x-sistema.panel>
            <x-sistema.panel titulo="Fotografías">
                <label class="block text-sm font-semibold">Adjuntar imágenes (opcional)
                    <input class="mt-2 block w-full" type="file" name="fotos[]" accept="image/jpeg,image/png,image/webp" multiple>
                </label>
                <p class="mt-1 text-xs text-slate-600">Hasta 8 imágenes JPG, PNG o WebP, de 5 MB cada una.</p>
            </x-sistema.panel>
            <div class="flex justify-end"><button class="ui-btn-primary w-full sm:w-auto">Registrar inspección</button></div>
        </form>
        <script>
            const region = document.getElementById('inspeccion-region');
            const persona = document.getElementById('inspeccion-persona');
            const puesto = document.getElementById('inspeccion-puesto');
            const estado = document.getElementById('inspeccion-estado');
            let consulta;
            function mostrarPuesto() {
                puesto.value = persona.selectedOptions[0]?.dataset.puesto ?? '';
            }
            async function cargar(elegido = '') {
                consulta?.abort();
                persona.replaceChildren(new Option('Selecciona un colaborador', ''));
                persona.disabled = true;
                mostrarPuesto();
                estado.textContent = '';
                const url = region.selectedOptions[0]?.dataset.url;
                if (!url) return;
                estado.textContent = 'Buscando colaboradores…';
                const controlador = new AbortController();
                consulta = controlador;
                try {
                    const respuesta = await fetch(url, {signal: controlador.signal, headers: {Accept: 'application/json'}});
                    if (!respuesta.ok) throw new Error();
                    const personas = await respuesta.json();
                    if (controlador.signal.aborted) return;
                    for (const fila of personas) {
                        const opcion = new Option(fila.nombre + ' · ' + fila.codigo, fila.codigo);
                        opcion.dataset.puesto = fila.puesto ?? '';
                        persona.add(opcion);
                    }
                    persona.value = elegido;
                    mostrarPuesto();
                    persona.disabled = personas.length === 0;
                    estado.textContent = persona.disabled ? 'La región no tiene colaboradores activos.' : '';
                } catch (error) {
                    if (error.name !== 'AbortError') estado.textContent = 'No se pudo consultar esta región. Vuelve a seleccionarla.';
                }
            }
            region.addEventListener('change', () => cargar());
            persona.addEventListener('change', mostrarPuesto);
            region.form.addEventListener('submit', event => {
                if (persona.disabled) { event.preventDefault(); return; }
                for (const campo of region.form.querySelectorAll('[name^="cantidades["]')) {
                    if (Number(campo.value) === 0) campo.disabled = true;
                }
            });
            cargar(@json(old('colaborador_codigo', '')));
        </script>
    @endif
</x-sistema.pagina>

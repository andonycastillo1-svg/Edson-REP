<x-sistema.pagina :titulo="$corte->nombre" :volver="route('flujo.cortes.index')">
    @include('flujo.cortes.pasos', ['paso' => 2])
    <dl class="flujo-cifras">
        <div><dt>Período</dt><dd class="!text-sm">{{ $corte->desde->format('d/m/Y') }} – {{ $corte->hasta->format('d/m/Y') }}</dd></div>
        <div><dt>Pendientes del período</dt><dd>{{ $pendientes['periodo'] }}</dd></div>
        <div><dt>Anteriores pendientes</dt><dd>{{ $pendientes['anteriores'] }}</dd></div>
        <div><dt id="etiqueta-seleccion">Selección total</dt><dd id="total-seleccion" role="status" aria-labelledby="etiqueta-seleccion" data-fuera="{{ $fueraDePagina }}">{{ $ids->count() }}</dd></div>
    </dl>
    <form id="seleccion-corte" method="POST" action="{{ route('flujo.cortes.seleccion.guardar', $corte) }}" class="space-y-4">
        @csrf
        @method('PUT')
        <input type="hidden" name="seleccion_enviada" value="1">
        @foreach($consultas as $clave => $paginador)
            <input type="hidden" name="{{ $clave }}_actual" value="{{ $paginador->currentPage() }}">
        @endforeach
        <x-sistema.panel titulo="Inspecciones del período">
            <p class="mb-3 text-sm text-slate-600">Marca las inspecciones que incluirás. Cambiar de página guarda las casillas visibles en el borrador.</p>
            @include('flujo.cortes.filas', ['paginador' => $consultas['periodo'], 'pagina' => 'periodo', 'campo' => 'dentro'])
        </x-sistema.panel>
        <x-sistema.panel>
            <details class="flujo-lectura" @if($consultas['anteriores']->currentPage() > 1 || $ids->intersect($consultas['anteriores']->pluck('id'))->isNotEmpty() || (array) old('antiguas', [])) open @endif>
                <summary>Inspecciones anteriores pendientes <span class="ml-2 font-medium">{{ $pendientes['anteriores'] }}</span></summary>
                <div class="p-3">
                    <p class="mb-3 text-sm text-slate-600">Selecciona aquí cada inspección anterior al período que quieras incorporar.</p>
                    @include('flujo.cortes.filas', ['paginador' => $consultas['anteriores'], 'pagina' => 'anteriores', 'campo' => 'antiguas'])
                </div>
            </details>
        </x-sistema.panel>
        <div class="flex flex-wrap items-center gap-3">
            <button class="ui-btn-primary">Revisar selección</button>
            <button class="ui-btn-secondary" type="submit" form="cancelar-corte">Cancelar borrador</button>
        </div>
    </form>
    @include('flujo.cortes.cancelar')
    <script>
        (() => {
            const formulario = document.getElementById('seleccion-corte');
            const total = document.getElementById('total-seleccion');
            const actualizar = () => total.textContent = Number(total.dataset.fuera) + formulario.querySelectorAll('[data-inspeccion]:checked').length;
            formulario.addEventListener('change', actualizar);
            actualizar();
        })();
    </script>
</x-sistema.pagina>

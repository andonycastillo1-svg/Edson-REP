<x-sistema.pagina titulo="Existencias autorizadas" :volver="route('dashboard')">
    <x-slot:acciones>
        @if($exportar)<x-ui.exportar-excel />@endif
    </x-slot:acciones>
    <x-sistema.panel titulo="Bodega">
        <form class="flex flex-wrap items-end gap-3" method="GET">
            <label class="ui-label">Bodega
                <select class="ui-input mt-1" name="bodega">
                    <option value="">Todas las autorizadas</option>
                    @foreach($bodegas as $bodega)
                        <option value="{{ $bodega->id }}" @selected((int) request('bodega') === $bodega->id)>{{ $bodega->nombre }}</option>
                    @endforeach
                </select>
            </label>
            <button class="ui-btn-primary">Consultar</button>
        </form>
    </x-sistema.panel>
    <x-sistema.panel titulo="Existencias">
        <ul class="divide-y divide-slate-200">
            @forelse($existencias as $fila)
                <li class="flex flex-wrap justify-between gap-2 py-3">
                    <span><strong>{{ $fila->producto?->nombre ?? $fila->producto_codigo }}</strong><small class="block">{{ $fila->bodega->nombre }} · {{ ucfirst($fila->stock_tipo) }}</small></span>
                    <span>{{ $fila->cantidad }} unidades</span>
                </li>
            @empty
                <li class="py-2 text-sm">No hay existencias para las bodegas autorizadas.</li>
            @endforelse
        </ul>
        {{ $existencias->links() }}
    </x-sistema.panel>
    @if($movimientos)
        <x-sistema.panel titulo="Movimientos de la bodega">
            <ul class="divide-y divide-slate-200">
                @forelse($movimientos as $fila)
                    <li class="flex flex-wrap justify-between gap-2 py-3 text-sm">
                        <span>{{ $fila->fecha }} · {{ $fila->producto_codigo }}</span>
                        <span>{{ $fila->tipo_movimiento }} · {{ $fila->cantidad }} unidades</span>
                    </li>
                @empty
                    <li class="py-2 text-sm">No hay movimientos para esta bodega.</li>
                @endforelse
            </ul>
            {{ $movimientos->links() }}
        </x-sistema.panel>
    @endif
</x-sistema.pagina>

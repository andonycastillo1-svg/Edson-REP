<x-sistema.pagina :titulo="$consolidado->nombre" :volver="route('flujo.consolidados.index')">
    @php
        $version = $consolidado->eventos->max('id');
        $editar = $acciones->first(fn ($accion) => in_array($accion, ['ajustar', 'corregir'], true));
        $etiquetas = [
            'enviar' => 'Enviar a coordinación', 'ajustar' => 'Guardar ajustes', 'corregir' => 'Guardar correcciones',
            'revisar' => 'Iniciar revisión', 'aprobar' => 'Aprobar consolidado', 'devolver' => 'Devolver para corrección',
            'retirar' => 'Retirar para corrección', 'reabrir' => 'Reabrir aprobación', 'cerrar' => 'Cerrar consolidado',
        ];
    @endphp
    <p class="text-sm font-semibold">{{ \App\Models\Consolidado::ESTADOS[$consolidado->estado] }} · {{ $consolidado->desde->format('d/m/Y') }} – {{ $consolidado->hasta->format('d/m/Y') }} · {{ $consolidado->coordinador?->name }}</p>
    @if($consolidado->aprobado_en)
        <p class="text-sm">Aprobado por {{ $consolidado->aprobador?->name }} el {{ $consolidado->aprobado_en->format('d/m/Y H:i') }}.</p>
    @endif
    <x-sistema.panel titulo="Cantidades del consolidado">
        @if($editar)
            <form id="ajustes-consolidado" action="{{ route('flujo.consolidados.transicion', [$consolidado, $editar]) }}" method="POST">
                @csrf
                <input type="hidden" name="version" value="{{ $version }}">
            </form>
        @endif
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50"><tr><th class="p-3">Producto</th><th class="p-3">Original</th><th class="p-3">Cantidad vigente</th><th class="p-3">Diferencia registrada</th></tr></thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($consolidado->lineas as $linea)
                        <tr>
                            <td class="p-3">{{ $linea->producto?->nombre }} · {{ $linea->producto_codigo }}</td>
                            <td class="p-3">{{ $linea->original }}</td>
                            <td class="p-3">
                                @if($editar)
                                    <input aria-label="Cantidad de {{ $linea->producto?->nombre }}" class="w-28 rounded-xl border-slate-300"
                                           name="cantidades[{{ $linea->id }}]" form="ajustes-consolidado" type="number" min="0" max="2000000"
                                           value="{{ old('cantidades.'.$linea->id, $linea->cantidad) }}" required>
                                @else
                                    {{ $linea->cantidad }}
                                @endif
                            </td>
                            <td class="p-3">{{ $linea->cantidad - $linea->original }}</td>
                        </tr>
                    @empty
                        <tr><td class="p-3" colspan="4">Las inspecciones no registraron cantidades solicitadas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($editar)
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <label class="text-sm">Agregar un producto del catálogo
                    <input name="producto_codigo" list="productos-consolidado" form="ajustes-consolidado" class="mt-1 block w-full rounded-xl border-slate-300" value="{{ old('producto_codigo') }}">
                    <datalist id="productos-consolidado">
                        @foreach($productos as $producto)
                            <option value="{{ $producto->codigo }}">{{ $producto->nombre }}</option>
                        @endforeach
                    </datalist>
                </label>
                <label class="text-sm">Cantidad del producto agregado
                    <input type="number" min="1" max="2000000" name="cantidad_nueva" form="ajustes-consolidado" class="mt-1 block w-full rounded-xl border-slate-300" value="{{ old('cantidad_nueva') }}">
                </label>
                <label class="text-sm sm:col-span-2">Observaciones o justificación de ajustes
                    <textarea name="nota" form="ajustes-consolidado" rows="2" maxlength="4000" class="mt-1 block w-full rounded-xl border-slate-300">{{ old('nota') }}</textarea>
                </label>
                <div class="flex flex-wrap gap-3 sm:col-span-2">
                    <button form="ajustes-consolidado" class="ui-btn-secondary">{{ $etiquetas[$editar] }}</button>
                    @if($acciones->contains('aprobar'))
                        <button form="ajustes-consolidado" class="ui-btn-primary"
                                formaction="{{ route('flujo.consolidados.transicion', [$consolidado, 'aprobar']) }}">{{ $etiquetas['aprobar'] }}</button>
                    @endif
                </div>
            </div>
        @endif
    </x-sistema.panel>
    @if($acciones->diff(['ajustar', 'corregir', 'aprobar'])->isNotEmpty())
        <x-sistema.panel titulo="Acciones del consolidado">
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach($acciones->diff(['ajustar', 'corregir', 'aprobar']) as $accion)
                    <form action="{{ route('flujo.consolidados.transicion', [$consolidado, $accion]) }}" method="POST" class="rounded-xl border border-slate-200 p-3">
                        @csrf
                        <input type="hidden" name="version" value="{{ $version }}">
                        @if(in_array($accion, ['devolver', 'retirar', 'reabrir'], true))
                            <label class="block text-sm">Motivo
                                <textarea name="nota" required maxlength="4000" rows="2" class="mt-1 block w-full rounded-xl border-slate-300"></textarea>
                            </label>
                        @endif
                        <button class="ui-btn-secondary mt-2">{{ $etiquetas[$accion] }}</button>
                    </form>
                @endforeach
            </div>
        </x-sistema.panel>
    @endif
    <x-sistema.panel titulo="Solicitudes originales">
        <ul class="divide-y divide-slate-200 text-sm">
            @foreach($consolidado->inspecciones as $inspeccion)
                <li class="py-2">
                    <a class="font-semibold underline" href="{{ route('flujo.inspecciones.show', $inspeccion) }}">{{ $inspeccion->colaborador?->nombre }}</a>
                    · {{ $inspeccion->supervisor?->name }} · {{ $inspeccion->region?->nombre }} · {{ $inspeccion->fecha->format('d/m/Y') }}
                </li>
            @endforeach
        </ul>
    </x-sistema.panel>
    <x-sistema.panel titulo="Historial">
        <ul class="divide-y divide-slate-200 text-sm">
            @foreach($consolidado->eventos->sortByDesc('id') as $evento)
                <li class="py-3">
                    <p class="font-semibold">{{ $etiquetas[$evento->accion] ?? 'Crear borrador' }} · {{ $evento->user?->name }} · {{ $evento->created_at->format('d/m/Y H:i:s') }}</p>
                    @if($evento->nota)<p class="whitespace-pre-wrap">{{ $evento->nota }}</p>@endif
                    @foreach($evento->cambios['productos'] ?? [] as $cambio)
                        <p>{{ $cambio['producto'] }} · {{ $cambio['antes'] }} → {{ $cambio['nuevo'] }}</p>
                    @endforeach
                </li>
            @endforeach
        </ul>
    </x-sistema.panel>
    @if($seguimiento)
        <x-sistema.panel titulo="Trámite de compra">
            @php($prefijo = \App\Sistema\CatalogoPermisos::prefijo(auth()->user()->rolOperativo()))
            <a class="ui-btn-secondary" href="{{ route('flujo.compras.index', ['estado' => $consolidado->estado, 'consolidados' => [$consolidado->id]]) }}">Ver seguimiento de compra</a>
            @include('flujo.facturas-consolidado')
            @foreach($constancias as $constancia)
                <p class="py-1 text-sm">Constancia de Gerencia · {{ $constancia->fecha->format('d/m/Y') }} · {{ $constancia->medio }}</p>
            @endforeach
        </x-sistema.panel>
    @endif
</x-sistema.pagina>

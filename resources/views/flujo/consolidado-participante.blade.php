@php($recuperar = (int) old('participante_id') === $persona->id)
@php($pendiente = $persona->necesidades->sum(fn ($fila) => max(0, $fila->cantidad - $fila->asignaciones_count)))
@php($aprobado = in_array($consolidado->estado, ['aprobado', 'cerrado'], true))
<details class="flujo-lectura" @if($loop->first || $recuperar) open @endif>
    <summary>
        {{ $persona->colaborador?->nombre }} · {{ $persona->colaborador_codigo }}
        <span class="font-normal">{{ $persona->region?->nombre }}</span>
        @if($persona->agregado_por)<span class="ui-badge ui-badge-info">Incorporado por coordinación</span>@endif
    </summary>
    <form class="space-y-2 p-3" method="POST" action="{{ route('flujo.consolidados.transicion', [$consolidado, 'ajustar']) }}">
        @if($editar)
            @csrf
            <input type="hidden" name="version" value="{{ $version }}">
            <input type="hidden" name="participante_id" value="{{ $persona->id }}">
        @endif
        @foreach($inspecciones as $inspeccion)
            <p class="text-sm"><strong>Supervisor</strong> {{ $inspeccion->supervisor?->name }} · {{ $inspeccion->fecha->format('d/m/Y') }}
                @if($inspeccion->observaciones)<span class="block whitespace-pre-wrap break-words">{{ $inspeccion->observaciones }}</span>@endif
            </p>
        @endforeach
        @if($aprobado && $pendiente === 0 && $persona->necesidades->sum('cantidad') > 0)
            <x-ui.estado variante="exito">Entrega completada</x-ui.estado>
        @endif
        @if($aprobado && $pendiente > 0 && app(\App\Services\FlujoAcceso::class)->puede(auth()->user(), 'compras'))
            @php($rutaAsignar = \App\Sistema\CatalogoPermisos::prefijo(auth()->user()->rolOperativo()).'.asignaciones.create')
            @rutaDisponible($rutaAsignar)
                <a class="ui-btn-secondary" href="{{ route($rutaAsignar, ['consolidado_id' => $consolidado->id, 'colaborador_codigo' => $persona->colaborador_codigo]) }}">Registrar entrega</a>
            @endrutaDisponible
        @endif
        <div class="ui-table-scroll" tabindex="0" role="region" aria-label="Productos de {{ $persona->colaborador?->nombre }}">
            <table class="ui-table min-w-[380px]">
                <thead><tr><th>Producto</th><th>Original</th><th>{{ $aprobado ? 'Aprobado' : 'Propuesto' }}</th><th>Diferencia</th>@if($aprobado)<th>Entregado</th><th>Pendiente</th>@endif</tr></thead>
                <tbody>
                    @forelse($persona->necesidades as $necesidad)
                        <tr>
                            <th scope="row">{{ $necesidad->producto?->nombre }} <small>{{ $necesidad->producto_codigo }}</small>
                                @if($facturasPorProducto->has($necesidad->producto_codigo))
                                    <small class="block font-normal">Compras para el consolidado {{ $facturasPorProducto[$necesidad->producto_codigo]->map(fn ($factura) => $factura->no_factura.' ('.$factura->cantidad.')')->implode(', ') }}</small>
                                @endif
                            </th>
                            <td>{{ $necesidad->original }}</td>
                            <td>@if($editar)
                                <input type="number" min="0" step="1" required class="ui-input w-24" aria-label="Cantidad de {{ $necesidad->producto?->nombre }}"
                                    name="cantidades[{{ $necesidad->id }}]" value="{{ $recuperar ? old('cantidades.'.$necesidad->id, $necesidad->cantidad) : $necesidad->cantidad }}">
                            @else
                                {{ $necesidad->cantidad }}
                            @endif</td>
                            <td>{{ sprintf('%+d', $necesidad->cantidad - $necesidad->original) }}</td>
                            @if($aprobado)<td>{{ $necesidad->asignaciones_count }}</td><td>{{ max(0, $necesidad->cantidad - $necesidad->asignaciones_count) }}</td>@endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ $aprobado ? 6 : 4 }}">Sin productos registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($editar)
            <div class="grid gap-3 sm:grid-cols-2">
                @include('flujo.consolidado-producto', ['obligatorio' => false])
                <label class="ui-label sm:col-span-2">Justificación
                    <textarea name="nota" class="ui-input mt-1" required rows="2" maxlength="4000">{{ $recuperar ? old('nota') : '' }}</textarea>
                </label>
            </div>
            <button class="ui-btn-primary">Guardar ajustes</button>
        @endif
    </form>
</details>

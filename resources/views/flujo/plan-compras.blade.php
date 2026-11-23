<x-sistema.pagina titulo="Necesidades de compra" :volver="route('dashboard')">
    @include('flujo.accesos')
    @php
        $prefijo = \App\Sistema\CatalogoPermisos::prefijo(auth()->user()->rolOperativo());
        $porConsolidado = $lineas->groupBy('consolidado_id');
        $lineasPlan = $lineas->whereIn('consolidado_id', $planificables->modelKeys());
    @endphp
    @if(!$bodega)
        <p class="ui-alert-warning">Configura la bodega central para consultar las existencias y registrar planes.</p>
    @endif
    <x-sistema.panel :titulo="$estado === 'aprobado' ? 'Consolidados aprobados' : 'Consolidados cerrados'">
        <div class="mb-3 flex flex-wrap gap-2">
            <a class="{{ $estado === 'aprobado' ? 'ui-btn-primary' : 'ui-btn-secondary' }}" href="{{ route('flujo.compras.index') }}">Aprobados</a>
            <a class="{{ $estado === 'cerrado' ? 'ui-btn-primary' : 'ui-btn-secondary' }}" href="{{ route('flujo.compras.index', ['estado' => 'cerrado']) }}">Cerrados</a>
        </div>
        <form method="GET" class="space-y-3">
            <input type="hidden" name="estado" value="{{ $estado }}">
            <fieldset class="grid gap-2 sm:grid-cols-2">
                @forelse($disponibles as $fila)
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="consolidados[]" value="{{ $fila->id }}" @checked($seleccion->contains('id', $fila->id))>
                        <span>{{ $fila->nombre }} · {{ $fila->coordinador?->name }}</span>
                    </label>
                @empty
                    <p class="text-sm">Todavía no hay consolidados para este estado.</p>
                @endforelse
            </fieldset>
            @if($disponibles->isNotEmpty())<button class="ui-btn-secondary">Actualizar selección</button>@endif
        </form>
    </x-sistema.panel>
    @if($seleccion->isNotEmpty())
        <x-sistema.panel titulo="Seguimiento por consolidado">
            <p class="text-sm text-slate-600">Las cantidades recibidas provienen de las líneas de factura distribuidas. Sin plan registrado, el pendiente se calcula sobre la cantidad aprobada.</p>
            <div class="mt-4 space-y-4">
                @foreach($seleccion as $consolidado)
                    @php
                        $filas = $porConsolidado->get($consolidado->id, collect());
                        $vinculadas = $facturas->get($consolidado->id, collect());
                        $porConciliar = $vinculadas->contains('distribucion_confirmada_en', null);
                        $pendientes = $filas->contains(fn ($fila) => $fila['pendiente'] > 0);
                        $sinCobertura = $filas->contains(fn ($fila) => $fila['sin_cobertura'] > 0);
                    @endphp
                    <section class="rounded-xl border border-slate-200 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <h3 class="font-semibold">{{ $consolidado->nombre }}</h3>
                                @if($porConciliar)
                                    <p class="text-sm font-semibold text-amber-800">Concilia las facturas históricas para confirmar sus cantidades y continuar.</p>
                                @elseif(!$pendientes && !$sinCobertura)
                                    <p class="text-sm font-semibold text-emerald-800">Requerimiento cubierto</p>
                                @endif
                            </div>
                            @if($estado === 'aprobado' && $bodega && $pendientes && !$porConciliar)
                                @rutaDisponible($prefijo.'.compras.create')
                                    <a class="ui-btn-secondary" href="{{ route($prefijo.'.compras.create', ['consolidados' => [$consolidado->id]]) }}">Registrar nueva compra</a>
                                @endrutaDisponible
                            @endif
                        </div>
                        <div class="ui-table-scroll mt-3" tabindex="0" role="region" aria-label="Seguimiento de {{ $consolidado->nombre }}">
                            <table class="ui-table">
                                <thead>
                                    <tr><th>Producto</th><th>Aprobada</th><th>Stock del plan</th><th>Decidida</th><th>Comprada y recibida</th><th>Pendiente</th><th>Sin cobertura en el plan</th>@if($bodega)<th>Saldo central actual</th>@endif</tr>
                                </thead>
                                <tbody>
                                    @foreach($filas as $fila)
                                        <tr>
                                            <th scope="row">{{ $fila['producto'] }} · {{ $fila['codigo'] }}</th>
                                            <td>{{ $fila['aprobada'] }}</td><td>{{ $fila['considerada'] ?? 'Sin plan' }}</td>
                                            <td>{{ $fila['decidida'] ?? 'Sin plan' }}</td><td>{{ $fila['comprada'] }}</td>
                                            <td>{{ $fila['pendiente'] }}</td><td>{{ $fila['decidida'] === null ? '—' : $fila['sin_cobertura'] }}</td>
                                            @if($bodega)<td>{{ $fila['central'] }}</td>@endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <h4 class="mt-3 text-sm font-semibold">Facturas vinculadas</h4>
                        @include('flujo.facturas-consolidado', ['facturas' => $vinculadas])
                    </section>
                @endforeach
            </div>
        </x-sistema.panel>
    @endif
    @if($estado === 'aprobado' && $bodega && $lineasPlan->isNotEmpty())
        <x-sistema.panel titulo="Plan y constancia de autorización externa">
            <p class="mb-3 text-sm text-slate-600">Confirma la cantidad que se cubrirá con existencias de central y la compra total decidida para cada producto, incluidas las unidades ya recibidas. El plan utiliza el saldo como referencia.</p>
            <form method="POST" enctype="multipart/form-data" action="{{ route('flujo.compras.constancia') }}" class="space-y-4">
                @csrf
                @foreach($planificables as $fila)<input type="hidden" name="consolidados[]" value="{{ $fila->id }}">@endforeach
                <div class="ui-table-scroll" tabindex="0" role="region" aria-label="Líneas del plan">
                    <table class="ui-table">
                        <thead>
                            <tr><th>Consolidado</th><th>Producto</th><th>Aprobada</th><th>Recibida</th><th>Compra adicional sugerida</th><th>Stock a considerar</th><th>Compra total decidida</th></tr>
                        </thead>
                        <tbody>
                            @foreach($lineasPlan as $fila)
                                @php($nombre = $seleccion->find($fila['consolidado_id'])->nombre)
                                <tr>
                                    <td>{{ $nombre }}</td><th scope="row">{{ $fila['producto'] }} · {{ $fila['codigo'] }}</th>
                                    <td>{{ $fila['aprobada'] }}</td><td>{{ $fila['comprada'] }}</td><td>{{ $fila['sugerida'] }}</td>
                                    <td><input type="number" min="0" max="{{ $fila['aprobada'] - $fila['comprada'] }}" required class="w-24 rounded-xl border-slate-300"
                                        aria-label="Stock a considerar para {{ $fila['codigo'] }} en {{ $nombre }}" name="propuestas[{{ $fila['id'] }}][existencia]"
                                        value="{{ old('propuestas.'.$fila['id'].'.existencia', $fila['considerada_sugerida']) }}"></td>
                                    <td><input type="number" min="{{ $fila['comprada'] }}" max="{{ $fila['aprobada'] }}" required class="w-24 rounded-xl border-slate-300"
                                        aria-label="Compra total decidida para {{ $fila['codigo'] }} en {{ $nombre }}" name="propuestas[{{ $fila['id'] }}][compra]"
                                        value="{{ old('propuestas.'.$fila['id'].'.compra', $fila['comprada'] + $fila['sugerida']) }}"></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="text-sm">Medio<select name="medio" required class="mt-1 block w-full rounded-xl border-slate-300"><option>Correo</option><option @selected(old('medio') === 'WhatsApp')>WhatsApp</option></select></label>
                    <label class="text-sm">Fecha<input name="fecha" type="date" required value="{{ old('fecha', today()->toDateString()) }}" class="mt-1 block w-full rounded-xl border-slate-300"></label>
                    <label class="text-sm">Persona que autorizó<input name="autorizo" required maxlength="160" value="{{ old('autorizo') }}" class="mt-1 block w-full rounded-xl border-slate-300"></label>
                    <label class="text-sm">Adjunto (opcional)<input name="documento" type="file" accept="application/pdf,image/jpeg,image/png,image/webp" class="mt-1 block w-full"></label>
                    <label class="text-sm sm:col-span-2">Referencia u observaciones<textarea name="referencia" required maxlength="4000" rows="2" class="mt-1 block w-full rounded-xl border-slate-300">{{ old('referencia') }}</textarea></label>
                </div>
                <button class="ui-btn-primary">Registrar constancia</button>
            </form>
        </x-sistema.panel>
    @endif
    <x-sistema.panel titulo="Constancias registradas">
        <ul class="divide-y divide-slate-200 text-sm">
            @forelse($constancias as $fila)
                <li class="py-3">
                    <details>
                        <summary class="cursor-pointer font-semibold">{{ $fila->fecha->format('d/m/Y') }} · {{ $fila->autorizo }} · {{ $fila->medio }}</summary>
                        <p class="my-2 whitespace-pre-wrap">{{ $fila->referencia }}</p>
                        @if($fila->ruta)<a class="underline" href="{{ route('flujo.compras.documento', $fila) }}">Abrir adjunto</a>@endif
                        @if(!isset($fila->plan[0]['consolidado_id']))<p class="mt-2 text-amber-800">Constancia histórica con cantidades de planificación sin desglose confirmado.</p>
                            <p>{{ $fila->consolidados->pluck('nombre')->join(', ') }}</p>
                        @endif
                        <ul class="mt-2">
                            @foreach($fila->plan as $linea)
                                <li>{{ $fila->consolidados->find($linea['consolidado_id'] ?? null)?->nombre }} {{ $linea['producto'] }} · {{ $linea['propuesta'] }} unidades propuestas</li>
                            @endforeach
                        </ul>
                    </details>
                </li>
            @empty
                <li class="py-3">No hay constancias registradas.</li>
            @endforelse
        </ul>
        {{ $constancias->links() }}
    </x-sistema.panel>
</x-sistema.pagina>

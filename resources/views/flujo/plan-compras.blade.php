@php
    $puedeVincularCompra = app(\App\Services\AccesoInventarioOperativo::class)->tiene(auth()->user(), 'compras.vincular');
    $prefijo = \App\Services\AccesoInventarioOperativo::prefijo(auth()->user());
    $porConsolidado = $lineas->groupBy('consolidado_id');
    $lineasPlan = $lineas->whereIn('consolidado_id', $planificables->modelKeys());
    $comprables = $lineasPlan->where('pendiente', '>', 0)->pluck('consolidado_id')->unique()->values()->all();
    $porConciliar = $facturas->collapse()->whereNull('distribucion_confirmada_en')->unique('id');
@endphp
<x-sistema.pagina titulo="Necesidades de compra" descripcion="Compara lo aprobado con el saldo central y las facturas recibidas." :volver="route('dashboard')">
    <x-slot:acciones>
        @if(app(\App\Services\FlujoAcceso::class)->puede(auth()->user(), 'exportar_compras'))<x-ui.exportar-excel />@endif
        @if($estado === 'aprobado' && $bodega && $comprables)
            @if($puedeVincularCompra)
                <a class="ui-btn-primary" href="{{ route($prefijo.'.compras.create', ['consolidados' => $comprables]) }}">Registrar compra</a>
            @endif
        @endif
    </x-slot:acciones>
    @include('flujo.accesos')
    @unless($bodega)
        <p class="ui-alert-warning">Configura la bodega central para consultar existencias y calcular compras.</p>
    @endunless

    <x-sistema.panel>
        <div class="flujo-cabecera-panel">
            <h2 class="text-lg font-bold">Consolidados</h2>
            <nav class="flex flex-wrap gap-1" aria-label="Estado de compra">
                @foreach(['aprobado' => 'Aprobados', 'cerrado' => 'Compras cerradas'] as $clave => $nombre)
                    <a class="flujo-tab" href="{{ route('flujo.compras.index', ['estado' => $clave]) }}" @if($estado === $clave) aria-current="page" @endif>{{ $nombre }}</a>
                @endforeach
            </nav>
        </div>
        @if($disponibles->isEmpty())
            <p class="text-sm">No hay consolidados en este estado.</p>
        @else
            <details class="flujo-lectura" @if($seleccion->isEmpty() || request()->has('consolidados')) open @endif>
                <summary>Cambiar selección <span class="ml-2 font-medium text-slate-600">{{ $seleccion->count() }} de {{ $disponibles->count() }} seleccionados</span></summary>
                <form method="GET" class="space-y-3 p-4">
                    <input type="hidden" name="estado" value="{{ $estado }}">
                    <input type="hidden" name="seleccion_manual" value="1">
                    <fieldset class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        <legend class="sr-only">Consolidados para el resumen</legend>
                        @foreach($disponibles as $fila)
                            <label class="flujo-eleccion">
                                <input class="mt-1" type="checkbox" name="consolidados[]" value="{{ $fila->id }}" @checked($seleccion->contains('id', $fila->id))>
                                <span class="min-w-0 break-words"><strong class="block text-slate-900">{{ $fila->nombre }}</strong>{{ $fila->coordinador?->name }}</span>
                            </label>
                        @endforeach
                    </fieldset>
                    <button class="ui-btn-primary">Actualizar resumen</button>
                </form>
            </details>
        @endif
    </x-sistema.panel>

    @if($seleccion->isNotEmpty())
        <x-sistema.panel>
            <div class="flujo-cabecera-panel">
                <div>
                    <h2 class="text-lg font-bold">Resumen por producto</h2>
                    <p class="mt-1 max-w-3xl text-sm">La compra total suma lo recibido y lo pendiente. El saldo actual aparece una vez por producto. Cuando existe un plan guardado, el pendiente sigue sus cantidades.</p>
                </div>
                @if($bodega)<span class="ui-badge ui-badge-info">{{ $bodega->nombre }}</span>@endif
            </div>
            @if($porConciliar->isNotEmpty())
                <div class="ui-alert-warning mb-4">
                    <p class="font-semibold">Hay facturas históricas pendientes de conciliación. Sus unidades aún no se suman a las recibidas.</p>
                    @include('flujo.facturas-consolidado', ['facturas' => $porConciliar])
                </div>
            @endif
            @if($resumen->isNotEmpty())
                <dl class="flujo-resumen mb-4">
                    <div><dt>Consolidados</dt><dd>{{ $seleccion->count() }}</dd></div>
                    <div><dt>Productos</dt><dd>{{ $resumen->count() }}</dd></div>
                    <div><dt>Con compra pendiente</dt><dd>{{ $bodega ? $resumen->where('pendiente', '>', 0)->count() : '—' }}</dd></div>
                    <div><dt>Facturas vinculadas</dt><dd>{{ $facturas->collapse()->unique('id')->count() }}</dd></div>
                </dl>
                <div class="grid gap-3">
                    @foreach($resumen as $fila)
                        <article class="flujo-card">
                            <div class="mb-3 flex flex-wrap items-center gap-x-3 gap-y-1">
                                <h3 class="break-words text-base font-bold">{{ $fila['producto'] }}</h3>
                                <span class="text-xs font-semibold text-slate-600">{{ $fila['codigo'] }}</span>
                                @if($fila['sin_cobertura'] > 0)
                                    <span class="ui-badge ui-badge-warning">{{ $fila['sin_cobertura'] }} sin plan de cobertura</span>
                                @endif
                            </div>
                            <dl class="flujo-cifras">
                                <div><dt>Requerimiento aprobado</dt><dd>{{ $fila['aprobada'] }}</dd></div>
                                <div><dt>Saldo central actual</dt><dd>{{ $fila['central'] ?? '—' }}</dd></div>
                                <div><dt>Compra total prevista</dt><dd>{{ $fila['compra_total'] ?? '—' }}</dd></div>
                                <div><dt>Comprado y recibido</dt><dd>{{ $fila['comprada'] }}</dd></div>
                                <div @class(['flujo-pendiente' => $fila['pendiente'] > 0])><dt>Pendiente de compra</dt><dd>{{ $fila['pendiente'] ?? '—' }}</dd></div>
                            </dl>
                            @if($fila['facturas']->isNotEmpty())
                                <ul class="mt-3 flex flex-wrap gap-x-4 gap-y-2 text-sm" aria-label="Facturas de {{ $fila['producto'] }}">
                                    @foreach($fila['facturas'] as $reparto)
                                        <li>
                                            @rutaDisponible($prefijo.'.compras.show')
                                                <a class="flujo-enlace" href="{{ route($prefijo.'.compras.show', $reparto['factura']->id) }}">Factura {{ $reparto['factura']->no_factura }}</a>
                                            @else
                                                <strong>Factura {{ $reparto['factura']->no_factura }}</strong>
                                            @endrutaDisponible
                                            <span class="text-slate-600"> · {{ $reparto['factura']->proveedor }} · {{ $reparto['cantidad'] }} u.</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </article>
                    @endforeach
                </div>
            @else
                <p class="text-sm">Los consolidados seleccionados no tienen productos.</p>
            @endif
        </x-sistema.panel>

        <x-sistema.panel titulo="Seguimiento por consolidado">
            <div class="grid gap-2">
                @foreach($seleccion as $consolidado)
                    @php
                        $filas = $porConsolidado->get($consolidado->id, collect());
                        $vinculadas = $facturas->get($consolidado->id, collect());
                        $requiereConciliar = $vinculadas->contains('distribucion_confirmada_en', null);
                        $cubierto = !$requiereConciliar && !$filas->contains(fn ($fila) => $fila['pendiente'] > 0 || $fila['sin_cobertura'] > 0);
                    @endphp
                    <details class="flujo-lectura" @if($seleccion->count() === 1) open @endif>
                        <summary>{{ $consolidado->nombre }}
                            @if($cubierto) <span class="ui-badge ui-badge-success ml-1">Requerimiento cubierto</span>
                            @elseif($requiereConciliar) <span class="ui-badge ui-badge-warning ml-1">Por conciliar</span>
                            @endif
                        </summary>
                        <div class="space-y-3 p-4">
                            <div class="ui-table-scroll" tabindex="0" role="region" aria-label="Cantidades de {{ $consolidado->nombre }}">
                                <table class="ui-table min-w-[550px]">
                                    <thead><tr><th>Producto</th><th>Aprobado</th><th>Stock del plan</th><th>Compra decidida</th><th>Recibido</th><th>Pendiente de compra</th></tr></thead>
                                    <tbody>
                                        @foreach($filas as $fila)
                                            <tr>
                                                <th scope="row">{{ $fila['producto'] }} <span class="text-xs text-slate-600">{{ $fila['codigo'] }}</span></th>
                                                <td>{{ $fila['aprobada'] }}</td><td>{{ $fila['considerada'] ?? '—' }}</td><td>{{ $fila['decidida'] ?? '—' }}</td>
                                                <td>{{ $fila['comprada'] }}</td><td>{{ $bodega ? $fila['sugerida'] : '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @if($filas->contains(fn ($fila) => $fila['decidida'] === null))
                                <p class="text-sm">El pendiente de las líneas sin plan se estima con el saldo actual en consolidados aprobados.</p>
                            @endif
                            @include('flujo.facturas-consolidado', ['facturas' => $vinculadas])
                            @if($estado === 'aprobado' && $bodega && in_array($consolidado->id, $comprables))
                                @if($puedeVincularCompra)
                                    <a class="ui-btn-secondary" href="{{ route($prefijo.'.compras.create', ['consolidados' => [$consolidado->id]]) }}">Registrar nueva compra</a>
                                @endif
                            @endif
                        </div>
                    </details>
                @endforeach
            </div>
        </x-sistema.panel>

        @if(app(\App\Services\FlujoAcceso::class)->puede(auth()->user(), 'plan_compras') && $estado === 'aprobado' && $bodega && $lineasPlan->isNotEmpty())
            <details class="flujo-lectura" @if($errors->has('propuestas') || $errors->has('propuestas.*')) open @endif>
                <summary>Ajustar plan de compra</summary>
                <form method="POST" action="{{ route('flujo.compras.plan') }}" class="space-y-4 p-4">
                    @csrf
                    @foreach($planificables as $fila)<input type="hidden" name="consolidados[]" value="{{ $fila->id }}">@endforeach
                    <p class="text-sm">Guarda el plan si quieres fijar el saldo que se usará y la compra total. Registra la autorización de Gerencia en la factura de compra.</p>
                    <div class="ui-table-scroll" tabindex="0" role="region" aria-label="Líneas del plan">
                        <table class="ui-table min-w-[530px]">
                            <thead><tr><th>Consolidado y producto</th><th>Aprobado</th><th>Recibido</th><th>Stock a usar</th><th>Compra total</th></tr></thead>
                            <tbody>
                                @foreach($lineasPlan as $fila)
                                    @php($nombre = $seleccion->find($fila['consolidado_id'])->nombre)
                                    <tr>
                                        <th scope="row">{{ $fila['producto'] }} · {{ $fila['codigo'] }}<span class="block text-xs text-slate-600">{{ $nombre }}</span></th>
                                        <td>{{ $fila['aprobada'] }}</td><td>{{ $fila['comprada'] }}</td>
                                        <td><input type="number" min="0" max="{{ $fila['aprobada'] - $fila['comprada'] }}" required class="ui-input w-24"
                                            aria-label="Stock a usar para {{ $fila['codigo'] }} en {{ $nombre }}" name="propuestas[{{ $fila['id'] }}][existencia]"
                                            value="{{ old('propuestas.'.$fila['id'].'.existencia', $fila['considerada_sugerida']) }}"></td>
                                        <td><input type="number" min="{{ $fila['comprada'] }}" max="{{ $fila['aprobada'] }}" required class="ui-input w-24"
                                            aria-label="Compra total para {{ $fila['codigo'] }} en {{ $nombre }}" name="propuestas[{{ $fila['id'] }}][compra]"
                                            value="{{ old('propuestas.'.$fila['id'].'.compra', $fila['comprada'] + $fila['sugerida']) }}"></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <button class="ui-btn-secondary">Guardar plan de compra</button>
                </form>
            </details>
        @endif
    @endif

    @if($constancias->total())
        <details class="flujo-lectura" @if(request()->has('page')) open @endif>
            <summary>Constancias históricas</summary>
            <div class="px-4 pb-4">
                <ul class="divide-y divide-slate-200 text-sm">
                    @foreach($constancias as $fila)
                        <li class="py-3">
                            <details>
                                <summary class="cursor-pointer font-semibold">{{ $fila->fecha->format('d/m/Y') }} · {{ $fila->autorizo }} · {{ $fila->medio }}</summary>
                                <p class="my-2 whitespace-pre-wrap">{{ $fila->referencia }}</p>
                                @if($fila->ruta)<a class="flujo-enlace" href="{{ route('flujo.compras.documento', $fila) }}">Abrir adjunto</a>@endif
                                @if(!isset($fila->plan[0]['consolidado_id']))
                                    <p class="mt-2 text-amber-800">Constancia histórica con cantidades de planificación sin desglose confirmado.</p>
                                    <p>{{ $fila->consolidados->pluck('nombre')->join(', ') }}</p>
                                @endif
                                <ul class="mt-2">
                                    @foreach($fila->plan as $detalle)
                                        <li>{{ $fila->consolidados->find($detalle['consolidado_id'] ?? null)?->nombre }} {{ $detalle['producto'] }} · {{ $detalle['propuesta'] }} unidades propuestas</li>
                                    @endforeach
                                </ul>
                            </details>
                        </li>
                    @endforeach
                </ul>
                {{ $constancias->links() }}
            </div>
        </details>
    @endif
</x-sistema.pagina>

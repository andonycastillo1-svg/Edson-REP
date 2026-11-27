@extends('layouts.app')

@section('title', 'Expediente del colaborador')
@section('content')
@php
    $enlace = fn ($destino) => route($ruta.'.expediente', [$colaborador, 'seccion' => $destino]);
    $pestanas = ['resumen' => 'Resumen', 'productos' => 'Productos asignados', 'incidencias' => 'Incidencias'];
    if ($puedeHistorial) $pestanas['historial'] = 'Historial';
    if (config('modulos.vehiculos')) $pestanas['vehiculo'] = 'Vehículo';
@endphp
<div class="space-y-4">
    <x-ui.encabezado :titulo="$colaborador->nombre" descripcion="Expediente del colaborador">
        <x-ui.accion :href="route($ruta.'.index')">Volver a colaboradores</x-ui.accion>
        @if($puedeDevolver)
            <x-ui.accion :href="route('devoluciones.formulario', $colaborador->codigo)" variante="primaria">Registrar devolución</x-ui.accion>
        @endif
        @rutaDisponible($ruta.'.ficha_tecnica')
            <x-ui.accion :href="route($ruta.'.ficha_tecnica', $colaborador)" icono="archivo">Descargar ficha Excel</x-ui.accion>
        @endrutaDisponible
    </x-ui.encabezado>

    <div class="ui-panel px-4 py-3">
        <dl class="grid grid-cols-2 gap-3 text-sm lg:grid-cols-4">
            <div><dt class="text-muted">Código</dt><dd class="break-words font-semibold">{{ $colaborador->codigo }}</dd></div>
            <div><dt class="text-muted">Puesto</dt><dd class="break-words font-semibold">{{ $colaborador->puesto }}</dd></div>
            <div><dt class="text-muted">Región</dt><dd class="font-semibold">{{ $ficha['colaborador']['region'] ?? 'Sin región registrada' }}</dd></div>
            <div><dt class="text-muted">Estado laboral</dt><dd><x-ui.estado :variante="$colaborador->estado === 'Activo' ? 'exito' : 'neutra'">{{ $colaborador->estado }}</x-ui.estado></dd></div>
        </dl>
    </div>

    <nav class="ui-status-tabs" aria-label="Secciones del expediente">
        @foreach($pestanas as $clave => $titulo)
            <a href="{{ $enlace($clave) }}" class="flujo-tab"
               @if($seccion === $clave) aria-current="page" @endif>{{ $titulo }}</a>
        @endforeach
    </nav>

    @if($seccion === 'resumen')
        <x-sistema.panel titulo="Resumen de custodia">
            <dl class="grid gap-3 divide-y divide-line text-sm sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                <div class="py-2 sm:pr-3"><dt>Unidades bajo custodia</dt><dd class="mt-1 text-lg font-semibold tabular-nums">{{ $ficha['total_custodia'] }}</dd></div>
                <div class="py-2 sm:px-3"><dt>Incidencias registradas</dt><dd class="mt-1 text-lg font-semibold tabular-nums">{{ $ficha['total_incidencias'] }}</dd></div>
                <div class="py-2 sm:pl-3"><dt>Valor del equipo registrado</dt><dd class="mt-1 text-lg font-semibold tabular-nums">Q {{ number_format($ficha['total_general'], 2) }}</dd></div>
            </dl>
        </x-sistema.panel>
    @elseif(in_array($seccion, ['productos', 'historial'], true))
        <x-sistema.panel :titulo="$seccion === 'historial' ? 'Historial de productos' : 'Productos bajo custodia'">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <p class="text-sm">{{ trans_choice('{1} :count asignación|[0,*] :count asignaciones', $ficha['paginador']->total()) }}</p>

            </div>
            <ul class="divide-y divide-line">
                @forelse($ficha['asignaciones'] as $fila)
                    <li class="py-3">
                        <details class="group">
                            <summary class="grid min-h-11 cursor-pointer list-none items-center gap-2 sm:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)_minmax(0,1.5fr)_auto]">
                                <span class="min-w-0"><strong class="break-words">{{ $fila['producto'] }}</strong><span class="block text-sm text-muted">{{ $fila['producto_codigo'] }}</span></span>
                                <span class="text-sm">{{ $fila['fecha_asignacion'] ?? 'Fecha sin registrar' }}<span class="block">{{ $fila['cantidad'] }} bajo custodia</span></span>
                                <span class="flex flex-wrap gap-1">
                                    <x-ui.estado>{{ $fila['custodia'] }}</x-ui.estado>
                                    <x-ui.estado :variante="$fila['estado_vida_util'] === 'Vigente' ? 'exito' : ($fila['estado_vida_util'] === 'Vencida' ? 'pendiente' : 'neutra')">{{ $fila['estado_vida_util'] }}</x-ui.estado>
                                </span>
                                <span class="text-sm font-semibold text-brand"><span class="group-open:hidden">Ver detalle</span><span class="hidden group-open:inline">Ocultar detalle</span></span>
                            </summary>
                            <div class="mt-3 space-y-3 rounded-lg border border-line bg-subtle p-3">
                                <dl class="flujo-dato">
                                    <div><dt>Bodega</dt><dd>{{ $fila['bodega'] }}</dd></div>
                                    <div><dt>Entregadas</dt><dd>{{ $fila['cantidad_entregada'] }}</dd></div>
                                    <div><dt>Movimiento inicial</dt><dd>{{ $fila['tipo_entrega'] }} · {{ $fila['condicion_salida'] }}</dd></div>
                                    <div><dt>Vencimiento estimado</dt><dd>{{ $fila['fecha_vencimiento'] ?? '—' }}</dd></div>
                                    <div><dt>Costo unitario</dt><dd>Q {{ number_format($fila['costo_unitario'], 2) }}</dd></div>
                                    @if($fila['motivo_registrado'])<div><dt>Motivo</dt><dd>{{ $fila['motivo_registrado'] }}</dd></div>@endif
                                </dl>
                                @if($fila['retornos']->isNotEmpty() || $fila['incidencias']->isNotEmpty())
                                    <ul class="space-y-1 text-sm">
                                        @foreach($fila['retornos']->concat($fila['incidencias']) as $hecho)
                                            <li>{{ $hecho['condicion'] }} · {{ $hecho['cantidad'] }} unidad(es) · {{ $hecho['fecha'] ?? 'Fecha sin registrar' }} · {{ $hecho['responsable'] ?? '—' }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                                @if($fila['documentos']->isNotEmpty())
                                    <p class="text-sm"><strong>Documentación</strong> · {{ $fila['documentos']->pluck('tipo')->unique()->join(', ') }}</p>
                                @endif
                                @if($fila['url'])
                                    <x-ui.accion :href="$fila['url']">Ver artículo y documentos</x-ui.accion>
                                @endif
                            </div>
                        </details>
                    </li>
                @empty
                    <li class="ui-empty-state">No hay productos en esta sección.</li>
                @endforelse
            </ul>
            {{ $ficha['paginador']->links() }}
        </x-sistema.panel>
        @if($seccion === 'historial' && $hojasDevolucion && $hojasDevolucion->isNotEmpty())
            <x-sistema.panel titulo="Hojas de devolución">
                <ul class="divide-y divide-line">
                    @foreach($hojasDevolucion as $hoja)
                        <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                            <span>{{ \Illuminate\Support\Carbon::parse($hoja->fecha)->format('d/m/Y H:i') }}</span>
                            <div class="flex flex-wrap gap-2">
                                <x-ui.accion :href="route('devoluciones.vista_previa', $hoja->grupo_devolucion)" target="_blank" rel="noopener" icono="archivo">Ver hoja de devolución</x-ui.accion>
                                <x-ui.accion :href="route('devoluciones.pdf', [$hoja->grupo_devolucion, 'descargar' => 1])">Descargar PDF</x-ui.accion>
                            </div>
                        </li>
                    @endforeach
                </ul>
                {{ $hojasDevolucion->links() }}
            </x-sistema.panel>
        @endif
    @elseif($seccion === 'incidencias')
        <x-sistema.panel titulo="Incidencias registradas">
            <ul class="divide-y divide-line">
                @forelse($ficha['incidencias'] as $incidencia)
                    <li class="space-y-2 py-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div><strong>{{ $incidencia['motivo'] }} · {{ $incidencia['producto'] ?? $incidencia['producto_codigo'] }}</strong>
                            <p class="text-sm">{{ $incidencia['fecha_incidente'] ?? 'Fecha sin registrar' }} · {{ $incidencia['bodega'] ?? 'Bodega sin identificar' }}</p></div>
                            <x-ui.estado>{{ $incidencia['estado'] }}</x-ui.estado>
                        </div>
                        @if($incidencia['resolucion'])<p class="whitespace-pre-wrap text-sm"><strong>Resolución</strong> {{ $incidencia['resolucion'] }}</p>@endif
                        @if($incidencia['detalle'])<p class="whitespace-pre-wrap text-sm">{{ $incidencia['detalle'] }}</p>@endif
                        @if($incidencia['url'])<a class="flujo-enlace" href="{{ $incidencia['url'] }}">Consultar incidencia</a>@endif
                    </li>
                @empty
                    <li class="ui-empty-state">No hay incidencias disponibles.</li>
                @endforelse
            </ul>
            {{ $ficha['pagina_incidencias']->links() }}
        </x-sistema.panel>
    @elseif($seccion === 'vehiculo')
        <x-sistema.panel titulo="Vehículo y productos asociados">
            @if($ficha['vehiculo_asignado'])
                @php($vehiculo = $ficha['vehiculo_asignado'])
                <dl class="flujo-dato">
                    <div><dt>Vehículo</dt><dd>{{ $vehiculo['marca'] }} {{ $vehiculo['modelo'] }}</dd></div>
                    <div><dt>Placa</dt><dd>{{ $vehiculo['placa'] }}</dd></div>
                    <div><dt>VIN</dt><dd class="break-all">{{ $vehiculo['vin'] ?? '—' }}</dd></div>
                    <div><dt>Asignación</dt><dd>{{ $vehiculo['fecha_asignacion'] }}</dd></div>
                    <div><dt>Estado</dt><dd>{{ $vehiculo['estado'] }}</dd></div>
                </dl>
                <details class="flujo-lectura mt-3">
                    <summary>{{ $ficha['productos_vehiculo']->count() }} artículos · Q {{ number_format($ficha['total_productos_vehiculo'], 2) }}</summary>
                    <ul class="divide-y divide-line">
                        @foreach($ficha['productos_vehiculo'] as $producto)
                            <li class="py-3 text-sm">
                                <strong>{{ $producto['producto'] }}</strong><p>{{ $producto['producto_codigo'] }} · {{ $producto['bodega'] }}</p>
                                <p>{{ $producto['cantidad'] }} unidades · Q {{ number_format($producto['costo_unitario'], 2) }} por unidad · Q {{ number_format($producto['total'], 2) }} total</p>
                                <p>{{ $producto['fecha'] }} · {{ $producto['motivo'] }} · {{ $producto['estado'] }}</p>
                            </li>
                        @endforeach
                    </ul>
                </details>
            @else
                <p class="ui-empty-state">Sin vehículo asignado.</p>
            @endif
        </x-sistema.panel>
    @endif
</div>
@endsection

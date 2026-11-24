@extends('layouts.admin')

@section('title', 'Detalle Compra')

@section('content')
@php($routePrefix = auth()->user()->rolOperativo() == 2 ? 'operador' : 'admin')
<div class="w-full max-w-6xl bg-white/90 rounded-xl shadow-lg p-8">
    <x-internal-navigation :back-url="route($routePrefix . '.compras.index')" />
    <div class="flex items-start justify-between gap-4 mb-6">
        <div>
            <h1 class="ui-page-title text-slate-800">Compra #{{ $compra->id }}</h1>
            <p class="text-slate-500 text-sm">
                Proveedor: <span class="font-semibold text-slate-700">{{ $compra->proveedor_nombre }}</span> ·
                Factura: <span class="font-semibold text-slate-700">{{ $compra->no_factura }}</span> ·
                Fecha de compra: <span class="font-semibold text-slate-700">{{ $compra->fecha_compra }}</span>
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="p-4 rounded-xl bg-white border border-slate-200">
            <div class="text-slate-500 text-sm">Proyecto</div>
            <div class="font-semibold text-slate-800">{{ $compra->proyecto ?? '—' }}</div>
        </div>
        <div class="p-4 rounded-xl bg-white border border-slate-200">
            <div class="text-slate-500 text-sm">Forma de pago</div>
            <div class="font-semibold text-slate-800">{{ $compra->forma_pago ?? '—' }}</div>
        </div>
        @php($autorizacion = app(\App\Sistema\Presentacion::class)->nativos('compras')['autorizado_por'])
        @if($autorizacion['visible'])
            <div class="p-4 rounded-xl bg-white border border-slate-200">
                <div class="text-slate-500 text-sm">{{ $autorizacion['etiqueta'] }}</div>
                <div class="font-semibold text-slate-800">{{ $compra->autorizado_por ?? 'Sin registrar' }}</div>
            </div>
        @endif
        <div class="p-4 rounded-xl bg-white border border-slate-200">
            <div class="text-slate-500 text-sm">Total factura</div>
            <div class="text-2xl font-bold text-slate-800">{{ number_format($compra->total_factura, 2) }}</div>
        </div>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white overflow-x-auto mb-6">
        <table class="ui-table min-w-full text-sm">
            <thead class="bg-slate-50 text-slate-600">
                <tr>
                    <th class="px-4 py-3">Código</th>
                    <th class="px-4 py-3">Producto</th>
                    <th class="px-4 py-3">Categoría</th>
                    <th class="px-4 py-3 text-right">Cantidad</th>
                    <th class="px-4 py-3 text-right">Precio U.</th>
                    <th class="px-4 py-3 text-right">Valor</th>
                    <x-sistema.cabeceras-campos :tabla="$tablaCampos" />
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($detalles as $d)
                    <tr>
                        <td class="px-4 py-3 font-semibold">{{ $d->producto_codigo }}</td>
                        <td class="px-4 py-3">{{ $d->producto_nombre }}</td>
                        <td class="px-4 py-3">{{ $d->categoria ?? '—' }}</td>
                        <td class="px-4 py-3 text-right font-semibold">{{ number_format($d->cantidad) }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format($d->precio_unitario, 2) }}</td>
                        <td class="px-4 py-3 text-right font-bold">{{ number_format($d->valor_total, 2) }}</td>
                        <x-sistema.celdas-campos :tabla="$tablaCampos" :registro="$d->producto_codigo" />
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if($consolidados->isNotEmpty())
        <section class="ui-panel mb-6 space-y-3 p-4">
            <h2 class="font-bold">Distribución por consolidado</h2>
            @if(!$compra->distribucion_confirmada_en)
                <p class="ui-alert-warning">Factura histórica pendiente de conciliación. Sus unidades se incorporan al seguimiento cuando se confirme su distribución.</p>
            @endif
            <ul class="space-y-2 text-sm">
                @foreach($consolidados as $consolidado)
                    <li>
                        @if($puedeVerConsolidado)
                            <a class="font-semibold underline" href="{{ route('flujo.consolidados.show', $consolidado) }}">{{ $consolidado->nombre }}</a>
                        @else
                            <span class="font-semibold">{{ $consolidado->nombre }}</span>
                        @endif
                        @if($compra->distribucion_confirmada_en)
                            <ul class="ml-4 list-disc">
                                @forelse($distribucion->get($consolidado->id, collect()) as $asignada)
                                    <li>{{ $detalles->firstWhere('id', $asignada->compra_detalle_id)->producto_codigo }} · {{ $asignada->cantidad }} unidades</li>
                                @empty
                                    <li>Sin unidades distribuidas.</li>
                                @endforelse
                            </ul>
                        @endif
                    </li>
                @endforeach
            </ul>
            @if($conciliar)
                @rutaDisponible($routePrefix.'.compras.conciliar')
                    <form method="POST" action="{{ route($routePrefix.'.compras.conciliar', $compra->id) }}" class="space-y-4">
                        @csrf
                        <p class="text-sm">Ingresa las cantidades respaldadas por la documentación. Puedes dejar en blanco las unidades que no correspondan a un consolidado.</p>
                        @foreach($consolidados as $consolidado)
                            <fieldset class="space-y-2 rounded-xl border border-slate-200 p-3">
                                <legend class="font-semibold">{{ $consolidado->nombre }}</legend>
                                @forelse($detalles->filter(fn ($d) => ($pendientes->get($consolidado->id.'|'.$d->producto_codigo)['pendiente'] ?? 0) > 0) as $d)
                                    @php($limite = $pendientes->get($consolidado->id.'|'.$d->producto_codigo)['pendiente'])
                                    <label class="flex items-center gap-3 text-sm">
                                        <span>{{ $d->producto_nombre }} · {{ $d->producto_codigo }} · {{ $d->cantidad }} facturadas · {{ $limite }} pendientes</span>
                                        <input type="number" min="0" max="{{ min($limite, $d->cantidad) }}" class="w-24 rounded-xl border-slate-300"
                                            name="distribucion[{{ $consolidado->id }}][{{ $d->id }}]"
                                            value="{{ old('distribucion.'.$consolidado->id.'.'.$d->id) }}"
                                            aria-label="Unidades de {{ $d->producto_codigo }} para {{ $consolidado->nombre }}">
                                    </label>
                                @empty
                                    <p class="text-sm text-slate-600">No hay productos facturados con cantidades pendientes en este consolidado.</p>
                                @endforelse
                            </fieldset>
                        @endforeach
                        <label class="block text-sm"><input type="checkbox" name="confirmar" value="1" required> Confirmo la distribución, incluidas las unidades que quedan sin atribuir.</label>
                        <button class="ui-btn-primary">Confirmar conciliación</button>
                    </form>
                @endrutaDisponible
            @endif
        </section>
    @endif

    @rutaDisponible(\App\Sistema\CatalogoPermisos::prefijo(auth()->user()->rolOperativo()).'.compras.archivo')
    <div class="rounded-xl border border-slate-200 bg-white p-4">
        <h2 class="font-bold text-slate-800 mb-2">Archivos (PDF)</h2>

        @if($archivos->isEmpty())
            <div class="text-slate-500 text-sm">No hay archivos adjuntos.</div>
        @else
            <ul class="list-disc pl-6 text-sm">
                @foreach($archivos as $a)
                    <li class="mb-1">
                        <a class="text-blue-700 font-semibold hover:underline" href="{{ $a->url }}" target="_blank">
                            {{ $a->nombre_original ?? $a->ruta }}
                        </a>
                        <span class="text-slate-500">({{ number_format(($a->tamano ?? 0)/1024, 0) }} KB)</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
    @endrutaDisponible

</div>
@endsection

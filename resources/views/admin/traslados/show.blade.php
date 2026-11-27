@extends('layouts.app')

@section('title', 'Solicitud de traslado')

@section('content')
@php($ruta = \App\Services\AccesoInventarioOperativo::prefijo(auth()->user()).'.operaciones.traslados')

<div class="space-y-4">
    <x-ui.encabezado :titulo="'Solicitud de traslado #'.$operacion->id" :descripcion="$operacion->created_at->format('d/m/Y H:i')">
        <x-ui.accion :href="route($ruta.'.index')">Volver a traslados</x-ui.accion>
        @rutaDisponible($ruta.'.hoja')
            <x-ui.accion :href="route($ruta.'.hoja', $operacion)" target="_blank" rel="noopener" icono="archivo">Hoja de traslado</x-ui.accion>
        @endrutaDisponible
        @if($operacion->archivo_excel_path)
            @rutaDisponible($ruta.'.archivo')
                <x-ui.accion :href="route($ruta.'.archivo', $operacion)" target="_blank" rel="noopener" icono="archivo">Ver Excel adjunto</x-ui.accion>
            @endrutaDisponible
        @endif
    </x-ui.encabezado>

    <x-sistema.panel titulo="Datos de la solicitud">
        <dl class="flujo-dato">
            <div><dt>Estado</dt><dd>@include('admin.traslados.estado', ['estadoTraslado' => $operacion->estado])</dd></div>
            <div><dt>Origen</dt><dd>{{ $operacion->bodegaOrigen?->nombre ?? '—' }}</dd></div>
            <div><dt>Destino</dt><dd>{{ $operacion->bodegaDestino?->nombre ?? '—' }}</dd></div>
            <div><dt>Solicitó</dt><dd>{{ $operacion->creador?->name ?? '—' }}</dd></div>
        </dl>
        @if($operacion->observacion)
            <p class="mt-4 whitespace-pre-wrap break-words text-sm"><strong>Observación</strong><br>{{ $operacion->observacion }}</p>
        @endif
        @if($operacion->estado === 'RECHAZADO' && $operacion->motivo_rechazo)
            <p class="ui-alert-error mb-0 mt-4"><strong>Motivo de rechazo</strong><br>{{ $operacion->motivo_rechazo }}</p>
        @endif
    </x-sistema.panel>

    <x-sistema.panel titulo="Productos solicitados">
        <div class="ui-table-scroll" tabindex="0" role="region" aria-label="Productos solicitados">
            <table class="ui-table">
                <thead><tr><th scope="col">Producto</th><th scope="col" class="text-right">Cantidad</th></tr></thead>
                <tbody>
                    @forelse($operacion->detalles as $detalle)
                        <tr>
                            <td><strong>{{ $detalle->producto_codigo }}</strong><p>{{ $detalle->producto?->descripcion ?? $detalle->producto?->nombre ?? 'Producto sin nombre' }}</p></td>
                            <td class="text-right font-semibold tabular-nums">{{ $detalle->cantidad }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2"><p class="ui-empty-state">Esta solicitud no tiene productos asociados.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-sistema.panel>

    @if($puedeDecidir)
        <x-sistema.panel titulo="Decisión de la bodega receptora">
            <div class="flex flex-wrap items-end gap-3">
                @if($puedeAprobar)
                    <form method="POST" action="{{ route($ruta.'.aprobar', $operacion) }}"
                          data-confirmar="¿Aprobar este traslado y mover las existencias a la bodega de destino?">
                        @csrf
                        <x-ui.accion variante="primaria" type="submit">Aprobar traslado</x-ui.accion>
                    </form>
                @endif
                @if($puedeRechazar)
                    <form method="POST" action="{{ route($ruta.'.rechazar', $operacion) }}"
                          data-confirmar="¿Rechazar esta solicitud de traslado?" class="flex min-w-0 flex-wrap items-end gap-2">
                        @csrf
                        <label class="ui-label mb-0">Motivo de rechazo
                            <input name="motivo_rechazo" required class="ui-input mt-1">
                        </label>
                        <x-ui.accion variante="riesgo" type="submit">Rechazar</x-ui.accion>
                    </form>
                @endif
            </div>
        </x-sistema.panel>
    @endif
</div>
@endsection

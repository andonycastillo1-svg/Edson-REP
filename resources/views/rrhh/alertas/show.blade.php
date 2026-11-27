@extends('layouts.app')
@section('title', 'Incidencia para revisión')
@section('content')
<div class="mx-auto max-w-6xl space-y-4">
    <x-internal-navigation :back-url="route('rrhh.alertas.index')" />
    <section class="ui-panel p-4 sm:p-5">
        <x-ui.estado :variante="$alerta->estado === \App\Models\AlertaReemplazo::ESTADO_FINALIZADO ? 'exito' : 'pendiente'">{{ $alerta->estado_etiqueta }}</x-ui.estado>
        <h1 class="ui-page-title ">{{ $alerta->motivo_etiqueta }} · {{ $alerta->producto?->nombre ?? $alerta->producto_codigo }}</h1>
        <dl class="my-3 grid gap-3 text-sm sm:grid-cols-2 xl:grid-cols-4">
            <div><dt class="font-bold">Colaborador</dt><dd>{{ $alerta->colaborador?->nombre ? $alerta->colaborador->nombre.' — '.$alerta->colaborador_codigo : $alerta->colaborador_codigo }}</dd></div>
            <div><dt class="font-bold">Cantidad</dt><dd>{{ $alerta->cantidad ?? '—' }}</dd></div>
            <div><dt class="font-bold">Fecha del incidente</dt><dd>{{ $alerta->fecha_incidente?->format('d/m/Y H:i') ?? 'Desconocida' }}</dd></div>
            <div><dt class="font-bold">Fecha de asignación</dt><dd>{{ $alerta->fecha_asignacion_anterior?->format('d/m/Y') ?? 'Desconocida' }}</dd></div>
            <div><dt class="font-bold">Vida útil al ocurrir</dt><dd>{{ $alerta->vida_etiqueta }}</dd></div>
            <div><dt class="font-bold">Registrado por</dt><dd>{{ $alerta->incidencia?->registrador?->name ?? $alerta->registrador?->name ?? 'No registrado' }}</dd></div>
            <div><dt class="font-bold">Bodega</dt><dd>{{ $alerta->bodega_nombre ?? 'No registrada' }}</dd></div>
            <div><dt class="font-bold">Observaciones</dt><dd class="whitespace-pre-wrap">{{ $alerta->incidencia?->observaciones ?? $alerta->detalle ?? '—' }}</dd></div>
        </dl>
        @if($alerta->referencia_proporcional !== null)
            <p class="rounded-lg bg-slate-50 p-3 text-sm"><strong>Referencia económica para revisión de RR. HH.</strong> Q {{ number_format((float) $alerta->referencia_proporcional, 2) }}. La decisión se documenta en el seguimiento de RR. HH.</p>
        @elseif($alerta->politica_motivo === 'proporcional')
            <p class="ui-alert-warning">Referencia no calculable. Verifica la fecha de recepción, la vida útil y el costo histórico de la asignación anterior.</p>
        @elseif($alerta->motivo_revision === \App\Models\AlertaReemplazo::REEMPLAZO_SIN_DATOS)
            <p class="ui-alert-warning">Vida útil sin datos suficientes. Si existe evidencia de recepción o de duración, usa la opción de regularización.</p>
        @endif
        @if($alerta->asignacionAnterior && $alerta->asignacionNueva)
            <dl class="my-4 grid gap-3 rounded-xl bg-slate-50 p-3 text-sm sm:grid-cols-2">
                <div><dt class="font-bold">Custodia anterior</dt><dd>{{ $alerta->asignacionAnterior->custodia_etiqueta }}</dd></div>
                <div><dt class="font-bold">Fecha de la nueva salida</dt><dd>{{ ($alerta->asignacionNueva->entrega?->salida_en ?? $alerta->asignacionNueva->fecha)->format('d/m/Y H:i') }} · {{ $alerta->asignacionNueva->bodega?->nombre }}</dd></div>
            </dl>
        @endif
        @if($alerta->incidencia?->archivo)
            <a class="ui-btn-download" href="{{ route('rrhh.alertas.evidencia', $alerta) }}">Respaldo del incidente</a>
            <p class="mt-1 text-sm text-slate-600">{{ $alerta->incidencia->archivoSubidoPor?->name }} · {{ $alerta->incidencia->archivo_subido_en?->format('d/m/Y H:i') }}</p>
        @endif
        @if($documentos->isNotEmpty() || $refacciones->isNotEmpty())
            <h2 class="mt-4 font-bold">Respaldos de la asignación</h2>
            <ul class="mt-2 space-y-1 text-sm">
                @foreach($documentos as $documento)
                    <li><a class="underline" href="{{ route('rrhh.alertas.documento', [$alerta, $documento]) }}">{{ $documento->nombre_original }}</a></li>
                @endforeach
                @foreach($refacciones as $documento)
                    <li><a class="underline" href="{{ route('rrhh.alertas.documento_refaccion', [$alerta, $documento]) }}">{{ $documento->nombre_original }}</a></li>
                @endforeach
            </ul>
        @endif
    </section>
    @if($historial->isNotEmpty())
        <section class="ui-panel p-4 sm:p-5">
            <h2 class="text-lg font-bold">Historial del producto</h2>
            <ul class="divide-y divide-slate-200 text-sm">
                @foreach($historial as $fila)
                    <li class="py-2">
                        <p>{{ ($fila->entrega?->salida_en ?? $fila->fecha)->format('d/m/Y H:i') }} · {{ $fila->bodega?->nombre }} · {{ $fila->custodia_etiqueta }}</p>
                        @foreach($fila->movimientos->whereIn('tipo', ['Devolucion', 'Reemplazo']) as $movimiento)
                            <p>Retorno físico · {{ ($movimiento->ocurrio_en ?? $movimiento->created_at)?->format('d/m/Y H:i') }} · {{ $movimiento->cantidad }} unidad(es)</p>
                        @endforeach
                    </li>
                @endforeach
            </ul>
            {{ $historial->links() }}
        </section>
    @endif
    <section class="ui-panel p-4 sm:p-5">
        <h2 class="text-lg font-bold">Seguimiento</h2>
        <ul class="my-3 divide-y divide-slate-200">
            @forelse($alerta->actuaciones->sortBy('id') as $actuacion)
                <li class="py-3">
                    <div class="font-semibold">{{ $actuacion->accion === 'solicitar_regularizacion' ? 'Regularización solicitada' : \App\Models\AlertaReemplazo::ESTADOS[$actuacion->estado] }} · {{ $actuacion->usuario?->name ?? 'Usuario histórico' }} · {{ $actuacion->created_at->format('d/m/Y H:i') }}</div>
                    <p class="whitespace-pre-wrap">{{ $actuacion->nota }}</p>
                </li>
            @empty
                <li class="py-2 text-slate-600">Sin actuaciones registradas.</li>
            @endforelse
        </ul>
        @if($alerta->estado !== \App\Models\AlertaReemplazo::ESTADO_FINALIZADO)
            @rutaDisponible('rrhh.alertas.actuacion')
                <form method="POST" action="{{ route('rrhh.alertas.actuacion', $alerta) }}" class="mt-4 space-y-3">
                    @csrf
                    <label class="block text-sm font-semibold">Nota de seguimiento
                        <textarea name="nota" required maxlength="4000" rows="3" class="mt-1 block w-full rounded-xl border-slate-300">{{ old('nota') }}</textarea>
                    </label>
                    <button class="ui-btn-primary">Guardar nota</button>
                    @if($alerta->asignacionAnterior?->estado === 'Activa' && $alerta->asignacionAnterior?->sustituida_por_id)
                        <button class="ui-btn-secondary" formaction="{{ route('rrhh.alertas.regularizacion', $alerta) }}">Solicitar regularización</button>
                    @endif
                    <button name="confirmar" value="1" formaction="{{ route('rrhh.alertas.finalizar', $alerta) }}"
                        onclick="return confirm('¿Finalizar esta incidencia con la nota registrada?')" class="ui-btn-secondary">Finalizar incidencia</button>
                </form>
            @endrutaDisponible
        @endif
    </section>
</div>
@endsection

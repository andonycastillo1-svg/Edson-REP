@extends('layouts.admin')
@section('title', 'Incidencia para revisión')
@section('content')
<div class="mx-auto max-w-4xl space-y-4 px-4 py-6">
    <x-internal-navigation :back-url="route('rrhh.alertas.index')" />
    <section class="rounded-xl border border-slate-200 bg-white p-5">
        <p class="text-sm font-semibold text-blue-800">{{ $alerta->estado_etiqueta }}</p>
        <h1 class="text-2xl font-bold">{{ $alerta->motivo_etiqueta }} · {{ $alerta->producto?->nombre ?? $alerta->producto_codigo }}</h1>
        <dl class="my-4 grid gap-3 text-sm sm:grid-cols-2">
            <div><dt class="font-bold">Colaborador</dt><dd>{{ $alerta->colaborador?->nombre ?? $alerta->colaborador_codigo }}</dd></div>
            <div><dt class="font-bold">Cantidad</dt><dd>{{ $alerta->cantidad ?? '—' }}</dd></div>
            <div><dt class="font-bold">Fecha del incidente</dt><dd>{{ $alerta->fecha_incidente?->format('d/m/Y H:i') ?? 'Desconocida' }}</dd></div>
            <div><dt class="font-bold">Fecha de asignación</dt><dd>{{ $alerta->fecha_asignacion_anterior?->format('d/m/Y') ?? 'Desconocida' }}</dd></div>
            <div><dt class="font-bold">Vida útil al ocurrir</dt><dd>{{ $alerta->vida_etiqueta }}</dd></div>
            <div><dt class="font-bold">Registrado por</dt><dd>{{ $alerta->registrador?->name ?? 'No registrado' }}</dd></div>
            <div><dt class="font-bold">Bodega</dt><dd>{{ $alerta->bodega_nombre ?? 'No registrada' }}</dd></div>
            <div><dt class="font-bold">Observaciones</dt><dd class="whitespace-pre-wrap">{{ $alerta->detalle ?? '—' }}</dd></div>
        </dl>
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
    <section class="rounded-xl border border-slate-200 bg-white p-5">
        <h2 class="text-lg font-bold">Seguimiento</h2>
        <ul class="my-3 divide-y divide-slate-200">
            @forelse($alerta->actuaciones->sortBy('id') as $actuacion)
                <li class="py-3">
                    <div class="font-semibold">{{ \App\Models\AlertaReemplazo::ESTADOS[$actuacion->estado] }} · {{ $actuacion->usuario?->name ?? 'Usuario histórico' }} · {{ $actuacion->created_at->format('d/m/Y H:i') }}</div>
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
                    <button name="confirmar" value="1" formaction="{{ route('rrhh.alertas.finalizar', $alerta) }}"
                        onclick="return confirm('¿Finalizar esta incidencia con la nota registrada?')" class="ui-btn-secondary">Finalizar incidencia</button>
                </form>
            @endrutaDisponible
        @endif
    </section>
</div>
@endsection

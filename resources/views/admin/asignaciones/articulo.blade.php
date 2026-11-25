@extends('layouts.app')

@section('title', 'Detalle de artículo')
@section('content')
<div class="space-y-4">
    <x-ui.encabezado :titulo="$asignacion->producto?->nombre ?? $asignacion->producto_codigo"
        :descripcion="$asignacion->producto_codigo.' · '.($asignacion->colaborador?->nombre ?? $asignacion->colaborador_codigo)">
        <x-ui.accion :href="route($routePrefix.'.asignaciones.colaborador', $asignacion->colaborador_codigo)">Volver a productos</x-ui.accion>
    </x-ui.encabezado>

    <x-sistema.panel titulo="Datos de la custodia">
        <dl class="flujo-dato">
            <div><dt>Cantidad bajo custodia</dt><dd>{{ $asignacion->cantidad_asignada }}</dd></div>
            <div><dt>Fecha de entrega</dt><dd>{{ ($asignacion->entrega?->salida_en ?? $asignacion->fecha)?->format('d/m/Y H:i') ?? '—' }}</dd></div>
            <div><dt>Bodega</dt><dd>{{ $asignacion->bodega?->nombre ?? '—' }}</dd></div>
            <div><dt>Estado</dt><dd>{{ $asignacion->custodia_etiqueta }}</dd></div>
            <div><dt>Vida útil</dt><dd>@include('admin.asignaciones.vida-util', ['segundos' => $vida])</dd></div>
            <div><dt>Vencimiento estimado</dt><dd>{{ $asignacion->fecha_vencimiento?->format('d/m/Y') ?? '—' }}</dd></div>
        </dl>
        @if($asignacion->motivo_registrado)
            <p class="mt-3 text-sm"><strong>Motivo de reemplazo</strong> {{ $asignacion->motivo_registrado }}</p>
        @endif
        <x-sistema.detalle-campos :tabla="$tablaCampos" :registro="$asignacion->id" />
    </x-sistema.panel>

    <x-sistema.panel titulo="Documentación" class="space-y-3">
        @if($asignacion->entrega)
            <p class="text-sm">{{ $asignacion->entrega->faltaHojaFirmada() ? 'Hoja firmada pendiente' : 'Hoja firmada registrada' }}. Los respaldos se administran por entrega.</p>
            @if($puedeEntrega)
                <x-ui.accion :href="route($routePrefix.'.asignaciones.entrega', $asignacion->grupo_asignacion)">Ver entrega y documentos</x-ui.accion>
            @endif
        @else
            @if($puedePdf)
                <x-ui.accion :href="route($routePrefix.'.asignaciones.pdf', $asignacion->grupo_asignacion)" target="_blank" rel="noopener">Hoja de asignación</x-ui.accion>
            @endif
            @if($puedeVerArchivos && $asignacion->evidencias->isNotEmpty())
                <ul class="space-y-2">
                    @foreach($asignacion->evidencias as $evidencia)
                        <li><a class="flujo-enlace break-all" href="{{ route($routePrefix.'.asignaciones.ver_pdf_firmado', $evidencia) }}" target="_blank" rel="noopener">{{ $evidencia->nombre_original }}</a></li>
                    @endforeach
                </ul>
            @endif
            @if($puedeAdjuntar)
                <form class="flex flex-wrap items-end gap-2" method="POST" enctype="multipart/form-data"
                      action="{{ route($routePrefix.'.asignaciones.upload_pdf_firmado', $asignacion) }}">
                    @csrf
                    <label class="ui-label mb-0">Respaldos históricos
                        <input class="ui-input mt-1" type="file" name="evidencias[]" required multiple accept=".pdf,.jpg,.jpeg,.png,.webp">
                    </label>
                    <x-ui.accion type="submit">Adjuntar</x-ui.accion>
                </form>
            @endif
        @endif
    </x-sistema.panel>

    <x-sistema.panel titulo="Historial del artículo">
        <ul class="divide-y divide-line">
            @forelse($movimientos as $movimiento)
                <li class="flex flex-wrap items-start justify-between gap-2 py-3 text-sm">
                    <div>
                        <strong>{{ ['Asignacion' => 'Asignación', 'Devolucion' => 'Devolución'][$movimiento->tipo] ?? $movimiento->tipo }}</strong>
                        <p>{{ $movimiento->cantidad }} unidades · {{ $movimiento->user?->name ?? 'Usuario histórico' }}</p>
                        <p class="break-words">{{ $movimiento->detalle }}</p>
                    </div>
                    <span>{{ ($movimiento->ocurrio_en ?? $movimiento->created_at)->format('d/m/Y H:i') }}</span>
                </li>
            @empty
                <li class="ui-empty-state">No hay movimientos registrados para este artículo.</li>
            @endforelse
        </ul>
        {{ $movimientos->links() }}
        @if($asignacion->incidencias->isNotEmpty())
            <h3 class="mt-4 font-semibold">Incidencias</h3>
            <ul class="divide-y divide-line">
                @foreach($asignacion->incidencias as $incidencia)
                    <li class="py-3 text-sm">
                        <a class="flujo-enlace" href="{{ route($routePrefix.'.asignaciones.incidencia', $incidencia) }}">{{ $incidencia->motivo_etiqueta }}</a>
                        · {{ ($incidencia->ocurrio_en ?? $incidencia->created_at)->format('d/m/Y H:i') }}
                        · {{ $incidencia->registrador?->name ?? '—' }}
                    </li>
                @endforeach
            </ul>
        @endif
    </x-sistema.panel>
</div>
@endsection

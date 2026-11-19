@extends('layouts.app')
@section('title', 'Incidencia de inventario')
@section('content')
<div class="mx-auto max-w-4xl space-y-4 px-4 py-8">
    <x-internal-navigation :back-url="route($routePrefix.'.asignaciones.index')" />
    <section class="rounded-2xl border border-slate-200 bg-white p-6">
        <h1 class="text-2xl font-bold">{{ $incidencia->motivo_etiqueta }} · {{ $incidencia->asignacion->producto?->nombre ?? $incidencia->asignacion->producto_codigo }}</h1>
        <dl class="my-4 grid gap-3 sm:grid-cols-2">
            <div><dt class="font-bold">Colaborador</dt><dd>{{ $incidencia->asignacion->colaborador?->nombre }}</dd></div>
            <div><dt class="font-bold">Bodega de salida</dt><dd>{{ $incidencia->asignacion->bodega?->nombre }}</dd></div>
            <div><dt class="font-bold">Cantidad</dt><dd>{{ $incidencia->cantidad }}</dd></div>
            <div><dt class="font-bold">Fecha del hecho</dt><dd>{{ $incidencia->ocurrio_en?->format('d/m/Y H:i') ?? 'Desconocida' }}</dd></div>
            <div><dt class="font-bold">Registro</dt><dd>{{ $incidencia->registrador?->name }} · {{ $incidencia->created_at?->format('d/m/Y H:i') }}</dd></div>
            <div><dt class="font-bold">Vida útil al ocurrir</dt><dd>{{ ucfirst($incidencia->estado_vida) }}</dd></div>
            <div><dt class="font-bold">Efecto</dt><dd>{{ \App\Services\ResolverCustodia::RESULTADOS_OTRA[$incidencia->resultado_stock === 'usado' ? 'buen_estado' : $incidencia->resultado_stock] ?? $incidencia->resultado_stock }}</dd></div>
            <div><dt class="font-bold">Observaciones</dt><dd class="whitespace-pre-wrap">{{ $incidencia->observaciones ?? '—' }}</dd></div>
        </dl>
        @if($incidencia->archivo)
            @rutaDisponible($routePrefix.'.asignaciones.archivo_incidencia')
                <a class="ui-btn-download" href="{{ route($routePrefix.'.asignaciones.archivo_incidencia', $incidencia) }}">Ver respaldo</a>
            @endrutaDisponible
        @else
            @rutaDisponible($routePrefix.'.asignaciones.documento_incidencia')
                <form method="POST" enctype="multipart/form-data" action="{{ route($routePrefix.'.asignaciones.documento_incidencia', $incidencia) }}" class="flex flex-wrap items-end gap-3">
                    @csrf
                    <label class="text-sm font-semibold">Respaldo de la incidencia (opcional)
                        <input type="file" name="evidencia" accept="application/pdf,image/jpeg,image/png,image/webp" class="mt-1 block" required>
                    </label>
                    <button class="ui-btn-primary">Cargar respaldo</button>
                </form>
            @endrutaDisponible
        @endif
    </section>
</div>
@endsection

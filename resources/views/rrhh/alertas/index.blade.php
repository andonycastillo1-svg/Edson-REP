@extends('layouts.app')
@section('title', 'Incidencias para revisión de RR. HH.')
@section('content')
<div class="mx-auto max-w-7xl space-y-3">
    <x-internal-navigation :back-url="route('dashboard')" />
    <section class="ui-panel p-4">
        <h1 class="ui-page-title text-slate-900">Incidencias para revisión de RR. HH.</h1>
        <form method="GET" class="mt-3 grid grid-cols-2 items-end gap-3 md:grid-cols-4 xl:grid-cols-6">
            <label class="ui-label col-span-2 mb-0 md:col-span-1">Colaborador o producto
                <input type="search" name="q" value="{{ request('q') }}" class="ui-input mt-1 min-w-0">
            </label>
            <label class="ui-label mb-0 min-w-0">Estado
                <select name="estado" class="ui-input mt-1 min-w-0">
                    <option value="">Todos</option>
                    @foreach(\App\Models\AlertaReemplazo::ESTADOS as $valor => $etiqueta)
                        <option value="{{ $valor }}" @selected(request('estado') === $valor)>{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </label>
            <label class="ui-label mb-0 min-w-0">Región
                <select name="region" class="ui-input mt-1">
                    <option value="">Todas las autorizadas</option>
                    @foreach($regiones as $region)
                        <option value="{{ $region->id }}" @selected(request('region') == $region->id)>{{ $region->nombre }}</option>
                    @endforeach
                </select>
            </label>
            <label class="ui-label col-span-2 mb-0 min-w-0 md:col-span-1">Motivo
                <select name="motivo" class="ui-input mt-1">
                    <option value="">Todos</option>
                    @foreach($motivos as $clave => $nombre)
                        <option value="{{ $clave }}" @selected(request('motivo') === $clave)>{{ $nombre }}</option>
                    @endforeach
                </select>
            </label>
            <label class="ui-label mb-0 min-w-0">Desde
                <input type="date" name="desde" value="{{ request('desde') }}" class="ui-input mt-1 min-w-0">
            </label>
            <label class="ui-label mb-0 min-w-0">Hasta
                <input type="date" name="hasta" value="{{ request('hasta') }}" class="ui-input mt-1 min-w-0">
            </label>
            <div class="col-span-2 flex flex-wrap gap-2">
                <button class="ui-btn-primary">Filtrar</button>
            @rutaDisponible('rrhh.alertas.export')
                <a class="ui-btn-download" href="{{ route('rrhh.alertas.export', request()->query()) }}">Exportar Excel</a>
            @endrutaDisponible
            </div>
        </form>
    </section>
    <div class="ui-table-scroll ui-panel" tabindex="0" role="region" aria-label="Incidencias de RR. HH.">
        <table class="ui-table min-w-[700px]">
            <thead class="bg-slate-50"><tr>
                <th class="p-3">Colaborador y producto</th><th class="p-3">Motivo</th>
                <th class="p-3">Fecha del incidente</th><th class="p-3">Vida útil al ocurrir</th><th class="p-3">Estado</th><th class="p-3">Acción</th>
            </tr></thead>
            <tbody class="divide-y divide-slate-200">
                @forelse($alertas as $alerta)
                    <tr>
                        <td class="p-3"><strong>{{ $alerta->colaborador?->nombre ?? $alerta->colaborador_codigo }}</strong>
                            <p class="text-xs text-muted">{{ $alerta->colaborador_codigo }}</p>
                            <p>{{ $alerta->producto?->nombre ?? $alerta->producto_codigo }}</p></td>
                        <td class="p-3">{{ $alerta->motivo_etiqueta }}
                            @if($alerta->referencia_proporcional !== null)
                                <p class="text-xs text-muted">Referencia por unidad: Q {{ number_format((float) $alerta->referencia_proporcional, 2) }}</p>
                            @endif
                        </td>
                        <td class="p-3">{{ $alerta->fecha_incidente?->format('d/m/Y H:i') ?? 'Desconocida' }}</td>
                        <td class="p-3">{{ $alerta->vida_etiqueta }}</td>
                        <td class="p-3"><x-ui.estado :variante="$alerta->estado === \App\Models\AlertaReemplazo::ESTADO_FINALIZADO ? 'exito' : 'pendiente'">{{ $alerta->estado_etiqueta }}</x-ui.estado></td>
                        <td class="p-3"><a class="underline" href="{{ route('rrhh.alertas.show', $alerta) }}">Ver incidencia</a></td>
                    </tr>
                @empty
                    <tr><td class="p-5 text-center" colspan="6">No hay incidencias con estos filtros.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $alertas->links() }}
</div>
@endsection

@extends('layouts.admin')
@section('title', 'Incidencias para revisión de RR. HH.')
@section('content')
<div class="mx-auto max-w-6xl space-y-4 px-4 py-6">
    <x-internal-navigation :back-url="route('dashboard')" />
    <section class="rounded-xl border border-slate-200 bg-white p-5">
        <h1 class="ui-page-title text-slate-900">Incidencias para revisión de RR. HH.</h1>
        <form method="GET" class="mt-4 flex flex-wrap items-end gap-3">
            <label class="text-sm font-semibold">Colaborador o producto
                <input type="search" name="q" value="{{ request('q') }}" class="mt-1 block rounded-xl border-slate-300">
            </label>
            <label class="text-sm font-semibold">Estado
                <select name="estado" class="mt-1 block rounded-xl border-slate-300">
                    <option value="">Todos</option>
                    @foreach(\App\Models\AlertaReemplazo::ESTADOS as $valor => $etiqueta)
                        <option value="{{ $valor }}" @selected(request('estado') === $valor)>{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm font-semibold">Desde
                <input type="date" name="desde" value="{{ request('desde') }}" class="mt-1 block rounded-xl border-slate-300">
            </label>
            <label class="text-sm font-semibold">Hasta
                <input type="date" name="hasta" value="{{ request('hasta') }}" class="mt-1 block rounded-xl border-slate-300">
            </label>
            <button class="ui-btn-primary">Filtrar</button>
            @rutaDisponible('rrhh.alertas.export')
                <a class="ui-btn-download" href="{{ route('rrhh.alertas.export', request()->query()) }}">Exportar Excel</a>
            @endrutaDisponible
        </form>
    </section>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="ui-table w-full text-left text-sm">
            <thead class="bg-slate-50"><tr>
                <th class="p-3">Colaborador y producto</th><th class="p-3">Motivo</th>
                <th class="p-3">Fecha del incidente</th><th class="p-3">Vida útil al ocurrir</th><th class="p-3">Estado</th><th class="p-3">Acción</th>
            </tr></thead>
            <tbody class="divide-y divide-slate-200">
                @forelse($alertas as $alerta)
                    <tr>
                        <td class="p-3"><strong>{{ $alerta->colaborador?->nombre ?? $alerta->colaborador_codigo }}</strong>
                            <p>{{ $alerta->producto?->nombre ?? $alerta->producto_codigo }}</p></td>
                        <td class="p-3">{{ $alerta->motivo_etiqueta }}</td>
                        <td class="p-3">{{ $alerta->fecha_incidente?->format('d/m/Y H:i') ?? 'Desconocida' }}</td>
                        <td class="p-3">{{ $alerta->vida_etiqueta }}</td>
                        <td class="p-3">{{ $alerta->estado_etiqueta }}</td>
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

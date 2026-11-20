@extends('layouts.operador')

@section('title', 'Panel de operador')

@section('content')
    @php($compras = app(\App\Services\BodegaAccessService::class)->canViewCompras(auth()->user()))
    <x-panel-operativo titulo="Panel de operador" :accesos="[
        'operador.bodegas.index' => ['Bodegas', 'Inventario por bodega', '🏬'],
        'operador.compras.index' => ['Compras', 'Consulta y registro de compras', '🧾', $compras],
        'operador.asignaciones.create' => ['Nueva asignación', 'Entrega de inventario a colaboradores', '📦', (bool) auth()->user()->bodega_id],
        'operador.asignaciones.index' => ['Asignaciones y devoluciones', 'Custodia, retornos y seguimiento de hojas firmadas', '↩'],
        'operador.operaciones.traslados.index' => ['Traslados', 'Solicitudes entre bodegas', '⇄'],
    ]" />
    @rutaDisponible('operador.asignaciones.index')
        <nav class="mx-auto mt-4 flex max-w-6xl flex-wrap gap-3" aria-label="Seguimiento de entregas">
            <a class="ui-btn-secondary" href="{{ route('operador.asignaciones.index', ['seguimiento' => 'documentacion']) }}">Hojas firmadas faltantes</a>
        </nav>
    @endrutaDisponible
@endsection

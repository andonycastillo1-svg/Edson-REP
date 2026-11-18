@extends('layouts.operador')

@section('title', 'Panel de operador')

@section('content')
    @php($compras = app(\App\Services\BodegaAccessService::class)->canViewCompras(auth()->user()))
    <x-panel-operativo titulo="Panel de operador" :accesos="[
        'operador.bodegas.index' => ['Bodegas', 'Inventario por bodega', '🏬'],
        'operador.compras.index' => ['Compras', 'Consulta y registro de compras', '🧾', $compras],
        'operador.asignaciones.create' => ['Nueva asignación', 'Entrega de inventario a colaboradores', '📦', (bool) auth()->user()->bodega_id],
        'operador.asignaciones.index' => ['Asignaciones y devoluciones', 'Consulta de entregas y retornos', '↩'],
        'operador.operaciones.traslados.index' => ['Traslados', 'Solicitudes entre bodegas', '⇄'],
    ]" />
@endsection

@extends('layouts.admin')

@section('title', 'Panel administrativo')

@section('content')
    <x-panel-operativo titulo="Panel administrativo" :accesos="[
        'admin.usuarios.index' => ['Usuarios', 'Altas y gestión de cuentas', '👤'],
        'admin.bodegas.index' => ['Bodegas', 'Inventario y operaciones por bodega', '🏬'],
        'admin.compras.index' => ['Compras', 'Consulta y registro de compras', '🧾'],
        'admin.colaboradores.index' => ['Colaboradores', 'Personal y detalle de asignaciones', '👥'],
        'admin.asignaciones.create' => ['Nueva asignación', 'Entrega de inventario a colaboradores', '📦'],
        'admin.asignaciones.index' => ['Asignaciones y devoluciones', 'Consulta de entregas y retornos', '↩'],
        'admin.operaciones.traslados.index' => ['Traslados', 'Solicitudes entre bodegas', '⇄'],
        'admin.vehiculos.index' => ['Vehículos', 'Control de flota', '🚚'],
        'admin.vehiculos.productos.index' => ['Productos del vehículo', 'Refacciones por unidad', '🧰'],
    ]" />
    @include('flujo.accesos')
@endsection

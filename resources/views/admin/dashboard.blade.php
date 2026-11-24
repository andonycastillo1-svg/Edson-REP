@extends('layouts.admin')

@section('title', 'Panel administrativo')

@section('content')
    <x-panel-operativo titulo="Panel administrativo" :accesos="[
        'admin.usuarios.index' => ['Usuarios', 'Altas y gestión de cuentas', 'usuario'],
        'admin.bodegas.index' => ['Bodegas', 'Inventario y operaciones por bodega', 'bodega'],
        'admin.compras.index' => ['Compras', 'Consulta y registro de compras', 'compra'],
        'admin.colaboradores.index' => ['Colaboradores', 'Personal y detalle de asignaciones', 'personas'],
        'admin.asignaciones.create' => ['Nueva asignación', 'Entrega de inventario a colaboradores', 'caja'],
        'admin.asignaciones.index' => ['Asignaciones y devoluciones', 'Consulta de entregas y retornos', 'caja'],
        'admin.operaciones.traslados.index' => ['Traslados', 'Solicitudes entre bodegas', 'traslado'],
        'admin.vehiculos.index' => ['Vehículos', 'Control de flota', 'vehiculo'],
        'admin.vehiculos.productos.index' => ['Productos del vehículo', 'Refacciones por unidad', 'herramienta'],
    ]" />
    @include('flujo.accesos')
@endsection

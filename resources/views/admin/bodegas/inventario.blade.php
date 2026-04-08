@extends('layouts.admin')

@section('title', 'Inventario')
@section('subtitle', 'Inventario por bodega')

@php
    $isAdmin = ($role === 'administrador' || $role === 'admin');
    $canOperate = $isAdmin || in_array($role, ['encargado','coordinador']);
    $readOnly = ($role === 'consultas');
@endphp

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h4 class="mb-1">Inventario — {{ $bodega->nombre ?? ('Bodega #'.$bodega->id) }}</h4>
        <div class="text-muted small">
            Tipo: <span class="fw-semibold">{{ $bodega->tipo ?? '—' }}</span>
            @if($readOnly) · Modo consulta @endif
        </div>
    </div>

    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('admin.bodegas.index') }}" class="btn btn-light soft-btn">← Volver</a>

        <button class="btn btn-outline-secondary soft-btn" type="button" disabled>
            Descargar inventario
        </button>

        @if($canOperate)
            <button class="btn btn-primary soft-btn" type="button" disabled>
                + Agregar al inventario
            </button>
        @endif
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-md-4">
        <div class="p-3 rounded-4 border bg-white">
            <div class="text-muted small">Productos en inventario</div>
            <div class="fw-bold" style="font-size: 1.4rem;">
                {{ $inventarios->count() }}
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="p-3 rounded-4 border bg-white">
            <div class="text-muted small">Stock total</div>
            <div class="fw-bold" style="font-size: 1.4rem;">
                {{ number_format($inventarios->sum('cantidad')) }}
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="p-3 rounded-4 border bg-white">
            <div class="text-muted small">Acceso</div>
            <div class="fw-bold" style="font-size: 1.1rem;">
                {{ strtoupper($role) }}
            </div>
        </div>
    </div>
</div>

<div class="table-responsive">
    <table class="table align-middle table-hover">
        <thead>
            <tr>
                <th>Código</th>
                <th>Producto</th>
                <th>Unidad</th>
                <th class="text-end">Cantidad</th>
                <th class="text-end">Acciones</th>
            </tr>
        </thead>
        <tbody>
        @forelse($inventarios as $inv)
            <tr>
                <td class="fw-semibold">{{ $inv->producto_codigo }}</td>
                <td>
                    <div class="fw-semibold">{{ $inv->producto->nombre ?? '—' }}</div>
                    <div class="text-muted small text-truncate" style="max-width: 520px;">
                        {{ $inv->producto->descripcion ?? '' }}
                    </div>
                </td>
                <td>{{ $inv->producto->unidad_medida ?? '—' }}</td>
                <td class="text-end fw-bold">{{ number_format($inv->cantidad) }}</td>
                <td class="text-end">
                    <div class="d-inline-flex gap-2">
                        <button class="btn btn-outline-secondary btn-sm soft-btn" type="button" disabled>
                            Movimientos
                        </button>

                        @if($canOperate)
                            <button class="btn btn-primary btn-sm soft-btn" type="button" disabled>
                                Entrada
                            </button>
                            <button class="btn btn-outline-primary btn-sm soft-btn" type="button" disabled>
                                Salida
                            </button>
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5">
                    <div class="alert alert-warning mb-0">
                        Esta bodega aún no tiene inventario registrado.
                    </div>
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>

@if($readOnly)
    <div class="text-muted small mt-2">
        *En modo consulta no se permiten entradas/salidas; solo ver y descargar.
    </div>
@endif
@endsection

@extends('layouts.admin')

@section('title', 'Inventario')
@section('subtitle', 'Inventario por bodega')

@php
    $isAdmin = ($role === 'administrador' || $role === 'admin');
    $canOperate = $isAdmin || in_array($role, ['encargado','coordinador']);
    $readOnly = ($role === 'consultas');

    $totalProductos = $inventarios->count();
    $stockTotal = $inventarios->sum('cantidad');

    $productosSinCosto = $inventarios->filter(function ($inv) {
        $costo = $inv->producto->costo
            ?? $inv->producto->precio
            ?? $inv->producto->precio_unitario
            ?? 0;

        return (float) $costo <= 0;
    })->count();

    $stockBajo = $inventarios->filter(function ($inv) {
        return (int) $inv->cantidad > 0 && (int) $inv->cantidad <= 5;
    })->count();

    $costoTotal = $inventarios->sum(function ($inv) {
        $costo = $inv->producto->costo
            ?? $inv->producto->precio
            ?? $inv->producto->precio_unitario
            ?? 0;

        return ((float) $costo) * ((int) $inv->cantidad);
    });
@endphp

@section('content')

<style>
    .inv-page {
        width: 100%;
    }

    .inv-card {
        background: #fff;
        border: 1px solid #dbe3ea;
        border-radius: 18px;
        overflow: hidden;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
    }

    .inv-header {
        padding: 20px 22px;
        border-bottom: 1px solid #e5eaf0;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
    }

    .inv-title {
        margin: 0;
        font-size: 24px;
        font-weight: 900;
        color: #0f172a;
        letter-spacing: -0.04em;
    }

    .inv-meta {
        margin-top: 6px;
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        color: #64748b;
        font-size: 13px;
    }

    .inv-meta strong {
        color: #0f172a;
    }

    .inv-mode {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 4px 9px;
        font-size: 11px;
        font-weight: 900;
        background: #eff6ff;
        color: #1d4ed8;
        border: 1px solid #bfdbfe;
        text-transform: uppercase;
        letter-spacing: .03em;
    }

    .inv-actions {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: 8px;
    }

    .inv-btn {
        height: 36px;
        border-radius: 9px;
        padding: 0 13px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid transparent;
        font-size: 13px;
        font-weight: 800;
        text-decoration: none;
        white-space: nowrap;
        transition: .15s ease;
    }

    .inv-btn-secondary {
        background: #fff;
        color: #334155;
        border-color: #cbd5e1;
    }

    .inv-btn-secondary:hover {
        background: #f8fafc;
        color: #0f172a;
    }

    .inv-btn-primary {
        background: #4f46e5;
        color: #fff;
        border-color: #4f46e5;
    }

    .inv-btn-primary:hover {
        background: #4338ca;
        color: #fff;
    }

    .inv-btn-disabled {
        opacity: .55;
        cursor: not-allowed;
    }

    .inv-summary {
        padding: 14px 22px;
        border-bottom: 1px solid #e5eaf0;
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 10px;
        background: #fff;
    }

    .inv-summary-item {
        border: 1px solid #e5eaf0;
        border-radius: 13px;
        padding: 12px 14px;
        background: #fbfdff;
    }

    .inv-summary-label {
        font-size: 11px;
        font-weight: 900;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: .04em;
        margin-bottom: 6px;
    }

    .inv-summary-value {
        font-size: 21px;
        font-weight: 900;
        color: #0f172a;
        line-height: 1;
    }

    .inv-summary-note {
        margin-top: 6px;
        font-size: 11px;
        color: #64748b;
        line-height: 1.3;
    }

    .inv-toolbar {
        padding: 13px 22px;
        border-bottom: 1px solid #e5eaf0;
        background: #f8fafc;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }

    .inv-search-box {
        width: 100%;
        max-width: 500px;
        position: relative;
    }

    .inv-search {
        width: 100%;
        height: 38px;
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        padding: 0 38px 0 12px;
        font-size: 13px;
        color: #334155;
        background: #fff;
    }

    .inv-search:focus {
        outline: 3px solid #dbeafe;
        border-color: #3b82f6;
    }

    .inv-search-icon {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 13px;
    }

    .inv-toolbar-info {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
    }

    .inv-mini {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 6px 10px;
        background: #fff;
        border: 1px solid #dbe3ea;
        color: #334155;
        font-size: 12px;
        font-weight: 800;
        white-space: nowrap;
    }

    .inv-table-wrap {
        overflow-x: auto;
    }

    .inv-table {
        width: 100%;
        margin: 0;
        border-collapse: collapse;
        font-size: 13px;
    }

    .inv-table thead {
        background: #fff;
    }

    .inv-table th {
        padding: 12px 14px;
        color: #64748b;
        font-size: 11px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: .05em;
        border-bottom: 1px solid #e5eaf0;
        white-space: nowrap;
    }

    .inv-table td {
        padding: 13px 14px;
        border-bottom: 1px solid #eef2f7;
        color: #0f172a;
        vertical-align: middle;
    }

    .inv-table tbody tr:hover {
        background: #f8fafc;
    }

    .inv-code {
        display: inline-flex;
        align-items: center;
        border-radius: 8px;
        padding: 5px 8px;
        font-size: 12px;
        font-weight: 900;
        color: #1e293b;
        background: #f1f5f9;
        white-space: nowrap;
    }

    .inv-product-name {
        font-weight: 900;
        color: #0f172a;
        margin-bottom: 2px;
    }

    .inv-product-desc {
        color: #64748b;
        font-size: 12px;
        max-width: 520px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .inv-muted {
        color: #94a3b8;
    }

    .inv-stock {
        display: inline-flex;
        justify-content: center;
        min-width: 46px;
        border-radius: 999px;
        padding: 5px 10px;
        font-weight: 900;
        background: #f1f5f9;
        color: #0f172a;
    }

    .inv-stock-low {
        background: #fff7ed;
        color: #c2410c;
    }

    .inv-stock-zero {
        background: #fee2e2;
        color: #b91c1c;
    }

    .inv-life {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 5px 10px;
        font-size: 12px;
        font-weight: 800;
        background: #eef2ff;
        color: #4338ca;
        white-space: nowrap;
    }

    .inv-money {
        font-weight: 900;
        color: #0f172a;
        white-space: nowrap;
    }

    .inv-money-zero {
        color: #dc2626;
    }

    .inv-status {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 5px 10px;
        font-size: 12px;
        font-weight: 800;
        white-space: nowrap;
    }

    .inv-status-ok {
        background: #ecfdf5;
        color: #047857;
    }

    .inv-status-warning {
        background: #fff7ed;
        color: #c2410c;
    }

    .inv-status-danger {
        background: #fee2e2;
        color: #b91c1c;
    }

    .inv-empty {
        padding: 44px 24px;
        text-align: center;
    }

    .inv-empty-title {
        font-weight: 900;
        color: #0f172a;
        margin-bottom: 5px;
    }

    .inv-empty-text {
        color: #64748b;
        font-size: 13px;
    }

    .inv-footer-actions {
        margin-top: 14px;
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .inv-footer-note {
        margin-top: 10px;
        color: #64748b;
        font-size: 12px;
    }

    @media (max-width: 1100px) {
        .inv-header {
            flex-direction: column;
        }

        .inv-actions {
            justify-content: flex-start;
        }

        .inv-summary {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 720px) {
        .inv-summary {
            grid-template-columns: 1fr;
        }

        .inv-header,
        .inv-summary,
        .inv-toolbar {
            padding-left: 16px;
            padding-right: 16px;
        }

        .inv-actions,
        .inv-toolbar-info,
        .inv-footer-actions {
            width: 100%;
        }

        .inv-btn {
            flex: 1;
        }

        .inv-search-box {
            max-width: 100%;
        }
    }
</style>

<div class="ui-panel w-full p-6 md:p-8">
    <div class="inv-page">

        <div class="inv-card">

            {{-- Encabezado --}}
            <div class="inv-header">
                <div>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <h4 class="inv-title">
                            Inventario — {{ $bodega->nombre ?? ('Bodega #'.$bodega->id) }}
                        </h4>

                        @if($readOnly)
                            <span class="inv-mode">
                                Consulta
                            </span>
                        @endif
                    </div>

                    <div class="inv-meta">
                        <span>Tipo: <strong>{{ $bodega->tipo ?? '—' }}</strong></span>

                        @if(!empty($bodega->ubicacion))
                            <span>· Ubicación: <strong>{{ $bodega->ubicacion }}</strong></span>
                        @endif

                        <span>· Rol: <strong>{{ strtoupper($role) }}</strong></span>
                    </div>
                </div>

                <div class="inv-actions">
                    <button class="inv-btn inv-btn-secondary inv-btn-disabled" type="button" disabled>
                        Descargar Excel
                    </button>

                    @if($canOperate)
                        <button class="inv-btn inv-btn-primary inv-btn-disabled" type="button" disabled>
                            + Agregar al inventario
                        </button>
                    @endif
                </div>
            </div>

            {{-- Resumen --}}
            <div class="inv-summary">
                <div class="inv-summary-item">
                    <div class="inv-summary-label">Productos</div>
                    <div class="inv-summary-value">{{ number_format($totalProductos) }}</div>
                    <div class="inv-summary-note">Registros en esta bodega.</div>
                </div>

                <div class="inv-summary-item">
                    <div class="inv-summary-label">Stock total</div>
                    <div class="inv-summary-value">{{ number_format($stockTotal) }}</div>
                    <div class="inv-summary-note">Unidades disponibles.</div>
                </div>

                <div class="inv-summary-item">
                    <div class="inv-summary-label">Costo estimado</div>
                    <div class="inv-summary-value">Q {{ number_format($costoTotal, 2) }}</div>
                    <div class="inv-summary-note">Según costo del producto.</div>
                </div>

                <div class="inv-summary-item">
                    <div class="inv-summary-label">Revisión</div>
                    <div class="inv-summary-value">{{ number_format($stockBajo) }}</div>
                    <div class="inv-summary-note">
                        Stock bajo · {{ number_format($productosSinCosto) }} sin costo
                    </div>
                </div>
            </div>

            {{-- Buscador --}}
            <div class="inv-toolbar">
                <div class="inv-search-box">
                    <input type="text"
                           id="inventarioSearch"
                           class="inv-search"
                           placeholder="Buscar por código, producto, descripción o unidad...">

                    <span class="inv-search-icon">🔎</span>
                </div>

                <div class="inv-toolbar-info">
                    <span class="inv-mini">{{ number_format($totalProductos) }} productos</span>
                    <span class="inv-mini">{{ number_format($stockTotal) }} unidades</span>
                    <span class="inv-mini">{{ strtoupper($role) }}</span>
                </div>
            </div>

            {{-- Tabla --}}
            <div class="inv-table-wrap">
                <table class="inv-table" id="inventarioTable">
                    <thead>
                        <tr>
                            <th class="text-start">Código</th>
                            <th class="text-start">Producto</th>
                            <th class="text-start">Unidad</th>
                            <th class="text-end">Cantidad</th>
                            <th class="text-end">Vida útil</th>
                            <th class="text-end">Costo unitario</th>
                            <th class="text-end">Costo total</th>
                            <th class="text-end">Estado</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($inventarios as $inv)
                            @php
                                $producto = $inv->producto;

                                $nombreProducto = $producto->nombre ?? '—';
                                $descripcionProducto = $producto->descripcion ?? '';
                                $unidadMedida = $producto->unidad_medida ?? '—';
                                $vidaUtil = $producto->vida_util_meses ?? null;

                                $cantidad = (int) $inv->cantidad;

                                $costoUnitario = $producto->costo
                                    ?? $producto->precio
                                    ?? $producto->precio_unitario
                                    ?? 0;

                                $costoUnitario = (float) $costoUnitario;
                                $costoLinea = $costoUnitario * $cantidad;

                                if ($cantidad <= 0) {
                                    $stockClass = 'inv-stock-zero';
                                    $estadoTexto = 'Sin stock';
                                    $estadoClass = 'inv-status-danger';
                                } elseif ($cantidad <= 5) {
                                    $stockClass = 'inv-stock-low';
                                    $estadoTexto = 'Stock bajo';
                                    $estadoClass = 'inv-status-warning';
                                } else {
                                    $stockClass = '';
                                    $estadoTexto = 'Disponible';
                                    $estadoClass = 'inv-status-ok';
                                }
                            @endphp

                            <tr class="inv-row">
                                <td>
                                    <span class="inv-code">
                                        {{ $inv->producto_codigo }}
                                    </span>
                                </td>

                                <td>
                                    <div class="inv-product-name">
                                        {{ $nombreProducto }}
                                    </div>

                                    @if(!empty($descripcionProducto))
                                        <div class="inv-product-desc" title="{{ $descripcionProducto }}">
                                            {{ $descripcionProducto }}
                                        </div>
                                    @else
                                        <div class="inv-product-desc inv-muted">
                                            Sin descripción registrada
                                        </div>
                                    @endif
                                </td>

                                <td>
                                    {{ $unidadMedida }}
                                </td>

                                <td class="text-end">
                                    <span class="inv-stock {{ $stockClass }}">
                                        {{ number_format($cantidad) }}
                                    </span>
                                </td>

                                <td class="text-end">
                                    @if(!empty($vidaUtil))
                                        <span class="inv-life">
                                            {{ $vidaUtil }} meses
                                        </span>
                                    @else
                                        <span class="inv-muted">—</span>
                                    @endif
                                </td>

                                <td class="text-end">
                                    <span class="inv-money {{ $costoUnitario <= 0 ? 'inv-money-zero' : '' }}">
                                        Q {{ number_format($costoUnitario, 2) }}
                                    </span>
                                </td>

                                <td class="text-end">
                                    <span class="inv-money {{ $costoLinea <= 0 ? 'inv-money-zero' : '' }}">
                                        Q {{ number_format($costoLinea, 2) }}
                                    </span>
                                </td>

                                <td class="text-end">
                                    @if($costoUnitario <= 0)
                                        <span class="inv-status inv-status-warning">
                                            Sin costo
                                        </span>
                                    @else
                                        <span class="inv-status {{ $estadoClass }}">
                                            {{ $estadoTexto }}
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8">
                                    <div class="inv-empty">
                                        <div class="inv-empty-title">
                                            Esta bodega aún no tiene inventario registrado.
                                        </div>
                                        <div class="inv-empty-text">
                                            Cuando se agreguen productos, aparecerán en esta tabla.
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        </div>

        <div class="inv-footer-actions">
            <a href="{{ route('admin.bodegas.index') }}" class="inv-btn inv-btn-secondary">
                ← Volver a bodegas
            </a>
        </div>

        @if($readOnly)
            <div class="inv-footer-note">
                * En modo consulta no se permiten entradas ni salidas; solo visualización.
            </div>
        @endif

    </div>
</div>

<script>
    const inventarioSearch = document.getElementById('inventarioSearch');
    const inventarioTable = document.getElementById('inventarioTable');

    if (inventarioSearch && inventarioTable) {
        inventarioSearch.addEventListener('input', function () {
            const search = this.value.toLowerCase().trim();
            const rows = inventarioTable.querySelectorAll('tbody tr.inv-row');

            rows.forEach((row) => {
                const text = row.innerText.toLowerCase();
                row.style.display = text.includes(search) ? '' : 'none';
            });
        });
    }
</script>
@endsection
@extends('layouts.admin')

@section('title', 'Inventario')
@section('subtitle', 'Inventario por bodega')

@php
    $isAdmin = ($role === 'administrador' || $role === 'admin');
    $canOperate = $isAdmin || in_array($role, ['encargado','coordinador']);
    $readOnly = ($role === 'consultas');

    $totalProductos = $inventarios->count();
    $stockTotal = $inventarios->sum('cantidad');

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

    .inv-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
        margin-bottom: 22px;
    }

    .inv-title {
        margin: 0;
        font-size: 24px;
        font-weight: 800;
        color: #0f172a;
        letter-spacing: -0.03em;
    }

    .inv-subtitle {
        margin-top: 4px;
        color: #64748b;
        font-size: 13px;
    }

    .inv-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .inv-btn {
        height: 38px;
        border-radius: 10px;
        font-weight: 700;
        font-size: 13px;
        padding: 0 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        border: 1px solid transparent;
        white-space: nowrap;
    }

    .inv-btn-primary {
        background: #2563eb;
        color: #fff;
        border-color: #2563eb;
    }

    .inv-btn-primary:hover {
        background: #1d4ed8;
        color: #fff;
    }

    .inv-btn-secondary {
        background: #fff;
        color: #334155;
        border-color: #dbe3ea;
    }

    .inv-btn-secondary:hover {
        background: #f8fafc;
        color: #0f172a;
    }

    .inv-btn-disabled {
        opacity: .55;
        cursor: not-allowed;
    }

    .inv-stats {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 14px;
        margin-bottom: 18px;
    }

    .inv-stat-card {
        background: #fff;
        border: 1px solid #dbe3ea;
        border-radius: 16px;
        padding: 18px;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
    }

    .inv-stat-label {
        color: #64748b;
        font-size: 13px;
        margin-bottom: 6px;
    }

    .inv-stat-value {
        color: #0f172a;
        font-size: 24px;
        font-weight: 800;
        line-height: 1;
    }

    .inv-stat-note {
        margin-top: 8px;
        color: #64748b;
        font-size: 11px;
        line-height: 1.3;
    }

    .inv-toolbar {
        background: #fff;
        border: 1px solid #dbe3ea;
        border-radius: 16px;
        padding: 14px;
        margin-bottom: 14px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
    }

    .inv-search {
        width: 100%;
        max-width: 420px;
        height: 38px;
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        padding: 0 12px;
        font-size: 13px;
        color: #334155;
        background: #fff;
    }

    .inv-search:focus {
        outline: 3px solid #dbeafe;
        border-color: #3b82f6;
    }

    .inv-badge {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 5px 10px;
        font-size: 12px;
        font-weight: 700;
        background: #eff6ff;
        color: #1d4ed8;
        border: 1px solid #bfdbfe;
        white-space: nowrap;
    }

    .inv-table-card {
        background: #fff;
        border: 1px solid #dbe3ea;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
    }

    .inv-table {
        width: 100%;
        margin: 0;
        border-collapse: collapse;
        font-size: 13px;
    }

    .inv-table thead {
        background: #f8fafc;
        color: #475569;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: .03em;
    }

    .inv-table th {
        padding: 12px 14px;
        font-weight: 800;
        border-bottom: 1px solid #e2e8f0;
        white-space: nowrap;
    }

    .inv-table td {
        padding: 13px 14px;
        border-bottom: 1px solid #eef2f7;
        vertical-align: middle;
        color: #0f172a;
    }

    .inv-table tbody tr:hover {
        background: #f8fafc;
    }

    .inv-code {
        font-weight: 800;
        color: #0f172a;
        white-space: nowrap;
    }

    .inv-product-name {
        font-weight: 800;
        color: #0f172a;
        margin-bottom: 2px;
    }

    .inv-product-desc {
        color: #64748b;
        font-size: 12px;
        max-width: 560px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .inv-stock {
        font-weight: 900;
        font-size: 15px;
    }

    .inv-stock-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 44px;
        padding: 4px 10px;
        border-radius: 999px;
        background: #f1f5f9;
        color: #0f172a;
        font-weight: 800;
    }

    .inv-money {
        font-weight: 800;
        color: #0f172a;
        white-space: nowrap;
    }

    .inv-muted {
        color: #94a3b8;
    }

    .inv-row-actions {
        display: inline-flex;
        gap: 6px;
        justify-content: flex-end;
        flex-wrap: wrap;
    }

    .inv-small-btn {
        height: 30px;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 700;
        padding: 0 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #dbe3ea;
        background: #fff;
        color: #334155;
    }

    .inv-small-btn-primary {
        background: #2563eb;
        color: #fff;
        border-color: #2563eb;
    }

    .inv-empty {
        padding: 34px;
        text-align: center;
        color: #64748b;
    }

    .inv-empty-title {
        font-weight: 800;
        color: #0f172a;
        margin-bottom: 4px;
    }

    @media (max-width: 992px) {
        .inv-header,
        .inv-toolbar {
            flex-direction: column;
            align-items: stretch;
        }

        .inv-actions {
            justify-content: flex-start;
        }

        .inv-stats {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="ui-panel w-full p-6 md:p-8">
    <div class="inv-page">

        <div class="inv-header">
            <div>
                <h4 class="inv-title">
                    Inventario — {{ $bodega->nombre ?? ('Bodega #'.$bodega->id) }}
                </h4>

                <div class="inv-subtitle">
                    Tipo:
                    <strong>{{ $bodega->tipo ?? '—' }}</strong>

                    @if(!empty($bodega->ubicacion))
                        · Ubicación:
                        <strong>{{ $bodega->ubicacion }}</strong>
                    @endif

                    @if($readOnly)
                        · Modo consulta
                    @endif
                </div>
            </div>

            <div class="inv-actions">
                <a href="{{ route('admin.bodegas.index') }}" class="inv-btn inv-btn-secondary">
                    ← Volver
                </a>

                <button class="inv-btn inv-btn-secondary inv-btn-disabled" type="button" disabled>
                    Descargar inventario
                </button>

                @if($canOperate)
                    <button class="inv-btn inv-btn-primary inv-btn-disabled" type="button" disabled>
                        + Agregar al inventario
                    </button>
                @endif
            </div>
        </div>

        <div class="inv-stats">
            <div class="inv-stat-card">
                <div class="inv-stat-label">Productos</div>
                <div class="inv-stat-value">{{ number_format($totalProductos) }}</div>
                <div class="inv-stat-note">Total de productos registrados en esta bodega.</div>
            </div>

            <div class="inv-stat-card">
                <div class="inv-stat-label">Stock total</div>
                <div class="inv-stat-value">{{ number_format($stockTotal) }}</div>
                <div class="inv-stat-note">Suma total de unidades disponibles.</div>
            </div>

            <div class="inv-stat-card">
                <div class="inv-stat-label">Costo total estimado</div>
                <div class="inv-stat-value">Q {{ number_format($costoTotal, 2) }}</div>
                <div class="inv-stat-note">Calculado con el costo registrado en el producto, si existe.</div>
            </div>
        </div>

        <div class="inv-toolbar">
            <input type="text"
                   id="inventarioSearch"
                   class="inv-search"
                   placeholder="Buscar por código, producto, descripción o unidad...">

            <span class="inv-badge">
                Acceso: {{ strtoupper($role) }}
            </span>
        </div>

        <div class="inv-table-card">
            <div class="table-responsive">
                <table class="inv-table" id="inventarioTable">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Producto</th>
                            <th>Unidad</th>
                            <th class="text-end">Cantidad</th>
                            <th class="text-end">Vida útil</th>
                            <th class="text-end">Costo unitario</th>
                            <th class="text-end">Costo total</th>
                            <th class="text-end">Acciones</th>
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

                                $costoUnitario = $producto->costo
                                    ?? $producto->precio
                                    ?? $producto->precio_unitario
                                    ?? 0;

                                $costoLinea = ((float) $costoUnitario) * ((int) $inv->cantidad);
                            @endphp

                            <tr class="inv-row">
                                <td>
                                    <span class="inv-code">{{ $inv->producto_codigo }}</span>
                                </td>

                                <td>
                                    <div class="inv-product-name">{{ $nombreProducto }}</div>

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
                                    <span class="inv-stock-pill">
                                        {{ number_format($inv->cantidad) }}
                                    </span>
                                </td>

                                <td class="text-end">
                                    @if(!empty($vidaUtil))
                                        {{ $vidaUtil }} meses
                                    @else
                                        <span class="inv-muted">—</span>
                                    @endif
                                </td>

                                <td class="text-end">
                                    <span class="inv-money">
                                        Q {{ number_format((float) $costoUnitario, 2) }}
                                    </span>
                                </td>

                                <td class="text-end">
                                    <span class="inv-money">
                                        Q {{ number_format($costoLinea, 2) }}
                                    </span>
                                </td>

                                <td class="text-end">
                                    <div class="inv-row-actions">
                                        <button class="inv-small-btn inv-btn-disabled" type="button" disabled>
                                            Movimientos
                                        </button>

                                        @if($canOperate)
                                            <button class="inv-small-btn inv-small-btn-primary inv-btn-disabled" type="button" disabled>
                                                Entrada
                                            </button>

                                            <button class="inv-small-btn inv-btn-disabled" type="button" disabled>
                                                Salida
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8">
                                    <div class="inv-empty">
                                        <div class="inv-empty-title">
                                            Esta bodega aún no tiene inventario registrado.
                                        </div>
                                        <div>
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

        @if($readOnly)
            <div class="text-muted small mt-3">
                * En modo consulta no se permiten entradas/salidas; solo ver y descargar.
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
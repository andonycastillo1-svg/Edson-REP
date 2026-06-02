@extends('layouts.admin')

@section('title', 'Inventario')

@section('content')

<style>
    .inv-page {
        width: 100%;
    }

    .inv-wrapper {
        width: 100%;
        background: linear-gradient(135deg, rgba(255,255,255,.98), rgba(240,249,255,.96));
        border: 1px solid #bfdbfe;
        border-radius: 26px;
        box-shadow: 0 22px 45px rgba(15, 23, 42, .08);
        overflow: hidden;
    }

    .inv-top {
        padding: 26px 28px 22px;
        background:
            radial-gradient(circle at top right, rgba(59,130,246,.14), transparent 35%),
            linear-gradient(135deg, #ffffff 0%, #f0f9ff 100%);
        border-bottom: 1px solid #dbeafe;
    }

    .inv-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 18px;
    }

    .inv-title-group {
        display: flex;
        align-items: flex-start;
        gap: 14px;
        min-width: 0;
    }

    .inv-icon {
        width: 50px;
        height: 50px;
        border-radius: 18px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #2563eb, #0ea5e9);
        color: #fff;
        font-size: 22px;
        box-shadow: 0 12px 24px rgba(37, 99, 235, .22);
        flex-shrink: 0;
    }

    .inv-title {
        margin: 0;
        color: #0f172a;
        font-size: 27px;
        font-weight: 900;
        letter-spacing: -0.035em;
        line-height: 1.15;
    }

    .inv-subtitle {
        margin-top: 7px;
        color: #64748b;
        font-size: 13px;
    }

    .inv-subtitle strong {
        color: #334155;
        font-weight: 900;
    }

    .inv-actions {
        display: flex;
        gap: 9px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .inv-btn {
        min-height: 40px;
        padding: 0 15px;
        border-radius: 14px;
        border: 1px solid transparent;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        font-size: 13px;
        font-weight: 900;
        text-decoration: none;
        white-space: nowrap;
        transition: .15s ease;
        cursor: pointer;
    }

    .inv-btn-blue {
        background: #2563eb;
        color: #fff;
        border-color: #2563eb;
        box-shadow: 0 8px 18px rgba(37, 99, 235, .18);
    }

    .inv-btn-blue:hover {
        background: #1d4ed8;
        color: #fff;
    }

    .inv-btn-sky {
        background: #0ea5e9;
        color: #fff;
        border-color: #0ea5e9;
        box-shadow: 0 8px 18px rgba(14, 165, 233, .16);
    }

    .inv-btn-sky:hover {
        background: #0284c7;
        color: #fff;
    }

    .inv-btn-purple {
        background: #4f46e5;
        color: #fff;
        border-color: #4f46e5;
        box-shadow: 0 8px 18px rgba(79, 70, 229, .18);
    }

    .inv-btn-purple:hover {
        background: #4338ca;
        color: #fff;
    }

    .inv-btn-light {
        background: #fff;
        color: #334155;
        border-color: #cbd5e1;
    }

    .inv-btn-light:hover {
        background: #f8fafc;
        color: #0f172a;
    }

    .inv-body {
        padding: 24px 28px 28px;
    }

    .inv-stats {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 14px;
        margin-bottom: 18px;
    }

    .inv-stat {
        position: relative;
        overflow: hidden;
        background: #fff;
        border: 1px solid #dbeafe;
        border-radius: 20px;
        padding: 18px;
        min-height: 108px;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
    }

    .inv-stat::after {
        content: "";
        position: absolute;
        right: -32px;
        top: -32px;
        width: 95px;
        height: 95px;
        border-radius: 999px;
        background: rgba(59, 130, 246, .08);
    }

    .inv-stat-label {
        color: #64748b;
        font-size: 13px;
        font-weight: 700;
        margin-bottom: 7px;
    }

    .inv-stat-value {
        color: #0f172a;
        font-size: 27px;
        font-weight: 950;
        line-height: 1;
        letter-spacing: -0.03em;
    }

    .inv-stat-note {
        margin-top: 10px;
        color: #64748b;
        font-size: 11px;
        line-height: 1.35;
    }

    .inv-toolbar {
        display: grid;
        grid-template-columns: 1fr auto auto auto;
        align-items: center;
        gap: 12px;
        background: rgba(255,255,255,.92);
        border: 1px solid #dbeafe;
        border-radius: 20px;
        padding: 14px;
        margin-bottom: 14px;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
    }

    .inv-search {
        width: 100%;
        height: 42px;
        border: 1px solid #bfdbfe;
        border-radius: 14px;
        padding: 0 14px;
        font-size: 13px;
        color: #334155;
        background: #fff;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .03);
    }

    .inv-search:focus {
        outline: 4px solid #dbeafe;
        border-color: #3b82f6;
    }

    .inv-counter {
        height: 38px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        padding: 0 14px;
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        color: #1d4ed8;
        font-size: 12px;
        font-weight: 900;
        white-space: nowrap;
    }

    .inv-table-card {
        background: #fff;
        border: 1px solid #dbeafe;
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(15, 23, 42, .04);
    }

    .inv-table-scroll {
        overflow-x: auto;
    }

    .inv-table {
        width: 100%;
        min-width: 1060px;
        border-collapse: collapse;
        font-size: 13px;
    }

    .inv-table thead {
        background: linear-gradient(90deg, #f8fafc, #eff6ff);
        color: #475569;
        text-transform: uppercase;
        letter-spacing: .04em;
        font-size: 11px;
    }

    .inv-table th {
        padding: 13px 14px;
        font-weight: 950;
        border-bottom: 1px solid #dbeafe;
        white-space: nowrap;
        text-align: left;
    }

    .inv-table td {
        padding: 14px;
        border-bottom: 1px solid #eef2f7;
        color: #0f172a;
        vertical-align: middle;
    }

    .inv-table tbody tr:hover {
        background: #f0f9ff;
    }

    .inv-code {
        font-weight: 950;
        color: #0f172a;
        white-space: nowrap;
    }

    .inv-product {
        font-weight: 950;
        color: #0f172a;
        margin-bottom: 4px;
    }

    .inv-description {
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

    .inv-stock-pill {
        display: inline-flex;
        min-width: 50px;
        justify-content: center;
        align-items: center;
        padding: 5px 11px;
        border-radius: 999px;
        background: #f1f5f9;
        color: #0f172a;
        font-weight: 950;
    }

    .inv-money {
        font-weight: 950;
        color: #0f172a;
        white-space: nowrap;
    }

    .inv-life {
        white-space: nowrap;
        color: #334155;
        font-weight: 800;
    }

    .inv-empty {
        padding: 38px;
        text-align: center;
        color: #64748b;
    }

    .inv-empty-title {
        color: #0f172a;
        font-weight: 950;
        margin-bottom: 4px;
    }

    .inv-pagination {
        margin-top: 18px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        flex-wrap: wrap;
    }

    .inv-pagination-text {
        color: #64748b;
        font-size: 13px;
        margin: 0;
    }

    .inv-pagination-text strong {
        color: #0f172a;
    }

    .inv-bottom-actions {
        margin-top: 18px;
        display: flex;
        justify-content: flex-end;
    }

    .text-right {
        text-align: right !important;
    }

    @media (max-width: 1000px) {
        .inv-header {
            flex-direction: column;
        }

        .inv-actions {
            justify-content: flex-start;
        }

        .inv-stats {
            grid-template-columns: 1fr;
        }

        .inv-toolbar {
            grid-template-columns: 1fr;
        }

        .inv-counter {
            width: fit-content;
        }
    }

    @media (max-width: 640px) {
        .inv-top,
        .inv-body {
            padding-left: 18px;
            padding-right: 18px;
        }

        .inv-wrapper {
            border-radius: 20px;
        }

        .inv-title {
            font-size: 22px;
        }

        .inv-actions {
            width: 100%;
        }

        .inv-btn {
            width: 100%;
        }

        .inv-bottom-actions {
            justify-content: stretch;
        }

        .inv-bottom-actions .inv-btn {
            width: 100%;
        }
    }
</style>

<div class="inv-page">
    <div class="inv-wrapper">

        <div class="inv-top">
            <div class="inv-header">
                <div class="inv-title-group">
                    <div class="inv-icon">📦</div>

                    <div>
                        <h1 class="inv-title">
                            Inventario — {{ $bodega->nombre ?? ('Bodega #'.$bodega->id) }}
                        </h1>

                        <p class="inv-subtitle">
                            Tipo:
                            <strong>{{ $bodega->tipo ?? '—' }}</strong>
                            · Ubicación:
                            <strong>{{ $bodega->ubicacion ?? '—' }}</strong>
                        </p>
                    </div>
                </div>

                <div class="inv-actions">
                    <a class="inv-btn inv-btn-light"
                       href="{{ route((auth()->user()->role_id == 2 ? 'operador' : 'admin') . '.bodegas.inventario.export', [$bodega->id] + request()->query()) }}">
                        Descargar Excel
                    </a>

                    <a href="{{ route('admin.bodegas.entrada', $bodega->id) }}" class="inv-btn inv-btn-purple">
                        + Agregar al inventario
                    </a>
                </div>
            </div>
        </div>

        <div class="inv-body">

            <div class="inv-stats">
                <div class="inv-stat">
                    <div class="inv-stat-label">Productos</div>
                    <div class="inv-stat-value">{{ number_format($productosTotal ?? 0) }}</div>
                    <div class="inv-stat-note">Total de productos registrados en esta bodega.</div>
                </div>

                <div class="inv-stat">
                    <div class="inv-stat-label">Stock total</div>
                    <div class="inv-stat-value">{{ number_format($stockTotal ?? 0) }}</div>
                    <div class="inv-stat-note">Suma total de unidades disponibles.</div>
                </div>

                <div class="inv-stat">
                    <div class="inv-stat-label">Costo total inventario</div>
                    <div class="inv-stat-value">
                        Q {{ number_format((float)($costoTotalInventario ?? 0), 2) }}
                    </div>
                    <div class="inv-stat-note">
                        Basado en el último costo de compra registrado por producto.
                    </div>
                </div>
            </div>

            <form method="GET" class="inv-toolbar">
                <input type="text"
                       id="inventarioSearch"
                       class="inv-search"
                       placeholder="Buscar por código, producto, descripción o unidad...">

                <span class="inv-counter" id="inventarioCounter">
                    {{ number_format($inventarios->count()) }} productos en pantalla
                </span>

                <button class="inv-btn inv-btn-blue" type="submit">
                    Filtrar
                </button>

                <a class="inv-btn inv-btn-light"
                   href="{{ route((auth()->user()->role_id == 2 ? 'operador' : 'admin') . '.bodegas.show', $bodega->id) }}">
                    Limpiar
                </a>
            </form>

            <div class="inv-table-card">
                <div class="inv-table-scroll">
                    <table class="inv-table" id="inventarioTable">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Producto</th>
                                <th>Unidad</th>
                                <th class="text-right">Cantidad</th>
                                <th class="text-right">Vida útil</th>
                                <th class="text-right">Costo unitario</th>
                                <th class="text-right">Costo total</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse($inventarios as $inv)
                                <tr class="inv-row">
                                    <td>
                                        <span class="inv-code">{{ $inv->producto_codigo }}</span>
                                    </td>

                                    <td>
                                        <div class="inv-product">
                                            {{ $inv->nombre ?? '—' }}
                                        </div>

                                        @if(!empty($inv->descripcion))
                                            <div class="inv-description" title="{{ $inv->descripcion }}">
                                                {{ $inv->descripcion }}
                                            </div>
                                        @else
                                            <div class="inv-description inv-muted">
                                                Sin descripción
                                            </div>
                                        @endif
                                    </td>

                                    <td>
                                        {{ $inv->unidad_medida ?? '—' }}
                                    </td>

                                    <td class="text-right">
                                        <span class="inv-stock-pill">
                                            {{ number_format($inv->cantidad ?? 0) }}
                                        </span>
                                    </td>

                                    <td class="text-right">
                                        @if(is_null($inv->vida_util_meses))
                                            <span class="inv-muted">—</span>
                                        @else
                                            <span class="inv-life">
                                                {{ number_format($inv->vida_util_meses) }} meses
                                            </span>
                                        @endif
                                    </td>

                                    <td class="text-right">
                                        <span class="inv-money">
                                            Q {{ number_format((float)($inv->costo_unitario ?? 0), 2) }}
                                        </span>
                                    </td>

                                    <td class="text-right">
                                        <span class="inv-money">
                                            Q {{ number_format((float)($inv->costo_total ?? 0), 2) }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7">
                                        <div class="inv-empty">
                                            <div class="inv-empty-title">
                                                Esta bodega aún no tiene inventario.
                                            </div>
                                            <div>
                                                Cuando agregues productos, aparecerán en esta tabla.
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse

                            <tr id="noSearchResults" style="display:none;">
                                <td colspan="7">
                                    <div class="inv-empty">
                                        <div class="inv-empty-title">
                                            No se encontraron resultados.
                                        </div>
                                        <div>
                                            Prueba buscando por otro código, producto o descripción.
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            @if(method_exists($inventarios, 'links'))
                <div class="inv-pagination">
                    <p class="inv-pagination-text">
                        Mostrando
                        <strong>{{ $inventarios->firstItem() ?? 0 }}</strong>
                        a
                        <strong>{{ $inventarios->lastItem() ?? 0 }}</strong>
                        de
                        <strong>{{ $inventarios->total() }}</strong>
                        resultados
                    </p>

                    <div>
                        {{ $inventarios->links() }}
                    </div>
                </div>
            @endif

            <div class="inv-bottom-actions">
                <a href="{{ route('dashboard') }}" class="inv-btn inv-btn-sky">
                    ← Volver
                </a>
            </div>

        </div>
    </div>
</div>

<script>
    const inventarioSearch = document.getElementById('inventarioSearch');
    const inventarioTable = document.getElementById('inventarioTable');
    const inventarioCounter = document.getElementById('inventarioCounter');
    const noSearchResults = document.getElementById('noSearchResults');

    if (inventarioSearch && inventarioTable) {
        inventarioSearch.addEventListener('input', function () {
            const search = this.value.toLowerCase().trim();
            const rows = inventarioTable.querySelectorAll('tbody tr.inv-row');
            let visible = 0;

            rows.forEach((row) => {
                const text = row.innerText.toLowerCase();
                const match = text.includes(search);

                row.style.display = match ? '' : 'none';

                if (match) {
                    visible++;
                }
            });

            if (inventarioCounter) {
                inventarioCounter.textContent = visible + ' productos en pantalla';
            }

            if (noSearchResults) {
                noSearchResults.style.display = visible === 0 && rows.length > 0 ? '' : 'none';
            }
        });
    }
</script>

@endsection
@extends('layouts.admin')

@section('title', 'Inventario')

@section('content')

<style>
    .inv-wrapper {
        width: 100%;
        max-width: 1280px;
        margin: 0 auto;
        background: rgba(255, 255, 255, .95);
        border: 1px solid #e2e8f0;
        border-radius: 24px;
        box-shadow: 0 20px 45px rgba(15, 23, 42, .08);
        padding: 28px;
    }

    .inv-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 18px;
        margin-bottom: 22px;
    }

    .inv-title {
        margin: 0;
        color: #0f172a;
        font-size: 26px;
        font-weight: 800;
        letter-spacing: -0.03em;
    }

    .inv-subtitle {
        margin-top: 5px;
        color: #64748b;
        font-size: 13px;
    }

    .inv-subtitle strong {
        color: #334155;
    }

    .inv-actions {
        display: flex;
        gap: 9px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .inv-btn {
        height: 38px;
        padding: 0 14px;
        border-radius: 12px;
        border: 1px solid transparent;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 13px;
        font-weight: 800;
        text-decoration: none;
        white-space: nowrap;
        transition: .15s ease;
    }

    .inv-btn-back {
        background: #0ea5e9;
        color: #fff;
        border-color: #0ea5e9;
    }

    .inv-btn-back:hover {
        background: #0284c7;
        color: #fff;
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
        opacity: .6;
        cursor: not-allowed;
    }

    .inv-stats {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 14px;
        margin-bottom: 18px;
    }

    .inv-stat {
        background: #fff;
        border: 1px solid #dbe3ea;
        border-radius: 18px;
        padding: 18px;
        min-height: 104px;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
    }

    .inv-stat-label {
        color: #64748b;
        font-size: 13px;
        margin-bottom: 7px;
    }

    .inv-stat-value {
        color: #0f172a;
        font-size: 25px;
        font-weight: 900;
        line-height: 1;
    }

    .inv-stat-note {
        margin-top: 9px;
        color: #64748b;
        font-size: 11px;
        line-height: 1.35;
    }

    .inv-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        background: #fff;
        border: 1px solid #dbe3ea;
        border-radius: 18px;
        padding: 14px;
        margin-bottom: 14px;
    }

    .inv-search {
        width: 100%;
        max-width: 460px;
        height: 38px;
        border: 1px solid #cbd5e1;
        border-radius: 12px;
        padding: 0 12px;
        font-size: 13px;
        color: #334155;
        background: #fff;
    }

    .inv-search:focus {
        outline: 3px solid #dbeafe;
        border-color: #3b82f6;
    }

    .inv-counter {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 6px 11px;
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        color: #1d4ed8;
        font-size: 12px;
        font-weight: 800;
        white-space: nowrap;
    }

    .inv-table-card {
        background: #fff;
        border: 1px solid #dbe3ea;
        border-radius: 18px;
        overflow: hidden;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
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
        background: #f8fafc;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: .035em;
        font-size: 11px;
    }

    .inv-table th {
        padding: 12px 14px;
        font-weight: 900;
        border-bottom: 1px solid #e2e8f0;
        white-space: nowrap;
        text-align: left;
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
        font-weight: 900;
        color: #0f172a;
        white-space: nowrap;
    }

    .inv-product {
        font-weight: 900;
        color: #0f172a;
        margin-bottom: 3px;
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
        min-width: 48px;
        justify-content: center;
        align-items: center;
        padding: 4px 10px;
        border-radius: 999px;
        background: #f1f5f9;
        color: #0f172a;
        font-weight: 900;
    }

    .inv-money {
        font-weight: 900;
        color: #0f172a;
        white-space: nowrap;
    }

    .inv-life {
        white-space: nowrap;
        color: #334155;
        font-weight: 700;
    }

    .inv-empty {
        padding: 34px;
        text-align: center;
        color: #64748b;
    }

    .inv-empty-title {
        color: #0f172a;
        font-weight: 900;
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

    .text-right {
        text-align: right !important;
    }

    @media (max-width: 900px) {
        .inv-wrapper {
            padding: 18px;
            border-radius: 18px;
        }

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

        .inv-search {
            max-width: none;
        }
    }
</style>

<div class="inv-wrapper">

    <div class="inv-header">
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

        <div class="inv-actions">
            <a href="{{ route('dashboard') }}" class="inv-btn inv-btn-back">
                ← Volver
            </a>

            <button class="inv-btn inv-btn-secondary inv-btn-disabled" type="button" disabled>
                Descargar inventario
            </button>

            <a href="{{ route('admin.bodegas.entrada', $bodega->id) }}" class="inv-btn inv-btn-primary">
                + Agregar al inventario
            </a>
        </div>
    </div>

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

    <div class="inv-toolbar">
        <input type="text"
               id="inventarioSearch"
               class="inv-search"
               placeholder="Buscar por código, producto, descripción o unidad...">

        <span class="inv-counter" id="inventarioCounter">
            {{ number_format($inventarios->count()) }} productos en pantalla
        </span>
    </div>

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
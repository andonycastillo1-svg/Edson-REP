@extends(auth()->user()->role_id == 1 ? 'layouts.admin' : 'layouts.operador')

@section('title', 'Bodegas')

@section('content')
@php
    $user = auth()->user();

    $esAdmin = (int) $user->role_id === 1;
    $esOperador = (int) $user->role_id === 2;
    $bodegaOperadorId = $user->bodega_id;

    $totalBodegas = $bodegas->count();
    $totalPrincipales = $bodegas->where('tipo', 'Principal')->count();
    $totalRegionales = $bodegas->where('tipo', 'Regional')->count();
@endphp

<style>
    .bodega-page {
        width: 100%;
        min-height: calc(100vh - 64px);
        background: #f8fafc;
        padding: 18px 14px;
    }

    .bodega-container {
        width: 100%;
        max-width: 1080px;
        margin: 0 auto;
    }

    .bodega-header {
        background: #ffffff;
        border: 1px solid #dbe3ea;
        border-radius: 14px;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        padding: 14px 16px;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
    }

    .bodega-title {
        margin: 0;
        font-size: 22px;
        line-height: 1.1;
        font-weight: 800;
        color: #0f172a;
    }

    .bodega-subtitle {
        margin-top: 5px;
        font-size: 13px;
        color: #64748b;
    }

    .bodega-header-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .metric {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 30px;
        padding: 0 11px;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 700;
        border: 1px solid #dbe3ea;
        background: #f8fafc;
        color: #334155;
    }

    .metric-green {
        border-color: #bbf7d0;
        background: #ecfdf5;
        color: #047857;
    }

    .metric-blue {
        border-color: #bfdbfe;
        background: #eff6ff;
        color: #1d4ed8;
    }

    .btn-new {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 30px;
        padding: 0 12px;
        border-radius: 8px;
        background: #2563eb !important;
        color: #ffffff !important;
        font-size: 12px;
        font-weight: 800;
        text-decoration: none !important;
        border: 1px solid #2563eb;
    }

    .btn-new:hover {
        background: #1d4ed8 !important;
    }

    .bodega-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
    }

    .bodega-card {
        background: #ffffff;
        border: 1px solid #dbe3ea;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    }

    .bodega-card-body {
        padding: 10px 14px;
        display: flex;
        justify-content: space-between;
        gap: 10px;
    }

    .bodega-info {
        display: flex;
        gap: 10px;
        min-width: 0;
    }

    .bodega-letter {
        width: 28px;
        height: 28px;
        border-radius: 7px;
        background: #f1f5f9;
        color: #0f172a;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: 800;
        flex-shrink: 0;
    }

    .bodega-name {
        margin: 0;
        font-size: 13px;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.15;
    }

    .bodega-location {
        margin-top: 2px;
        font-size: 11px;
        color: #64748b;
    }

    .bodega-id {
        margin-top: 3px;
        font-size: 12px;
        color: #0f172a;
    }

    .bodega-badge {
        height: fit-content;
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 4px 10px;
        font-size: 12px;
        font-weight: 800;
        white-space: nowrap;
    }

    .badge-principal {
        background: #ecfdf5;
        color: #047857;
    }

    .badge-regional {
        background: #eff6ff;
        color: #1d4ed8;
    }

    .access-label {
        margin-top: 6px;
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 3px 9px;
        font-size: 11px;
        font-weight: 700;
    }

    .access-own {
        background: #ecfdf5;
        color: #047857;
    }

    .access-read {
        background: #f1f5f9;
        color: #64748b;
    }

    .bodega-card-actions {
        border-top: 1px solid #e2e8f0;
        background: #f8fafc;
        padding: 8px 14px;
        display: flex;
        align-items: center;
        gap: 7px;
        flex-wrap: wrap;
    }

    .action-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 28px;
        padding: 0 11px;
        border-radius: 7px;
        font-size: 11px;
        font-weight: 800;
        text-decoration: none !important;
        border: 1px solid transparent;
        cursor: pointer;
        line-height: 1;
    }

    .btn-inventario {
        background: #0f172a !important;
        color: #ffffff !important;
        border-color: #0f172a !important;
    }

    .btn-trasladar {
        background: #4f46e5 !important;
        color: #ffffff !important;
        border-color: #4f46e5 !important;
    }

    .btn-aprobacion {
        background: #0891b2 !important;
        color: #ffffff !important;
        border-color: #0891b2 !important;
    }

    .btn-editar {
        background: #f59e0b !important;
        color: #ffffff !important;
        border-color: #f59e0b !important;
    }

    .btn-eliminar {
        background: #e11d48 !important;
        color: #ffffff !important;
        border-color: #e11d48 !important;
    }

    .btn-volver {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 34px;
        padding: 0 16px;
        border-radius: 8px;
        border: 1px solid #cbd5e1;
        background: #ffffff !important;
        color: #334155 !important;
        font-size: 12px;
        font-weight: 800;
        text-decoration: none !important;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
    }

    .volver-wrap {
        margin-top: 14px;
        display: flex;
        justify-content: flex-end;
    }

    .empty-box {
        grid-column: 1 / -1;
        border: 1px solid #fde68a;
        background: #fffbeb;
        color: #92400e;
        border-radius: 12px;
        padding: 18px;
    }

    @media (max-width: 900px) {
        .bodega-grid {
            grid-template-columns: 1fr;
        }

        .bodega-header {
            align-items: stretch;
        }

        .bodega-header-actions {
            justify-content: flex-start;
        }
    }
</style>

<div class="bodega-page">
    <div class="bodega-container">

        <div class="bodega-header">
            <div>
                <h1 class="bodega-title">Bodegas</h1>
                <div class="bodega-subtitle">
                    Consulta inventario, traslados y administración de bodegas.
                </div>
            </div>

            <div class="bodega-header-actions">
                <span class="metric">Total: {{ $totalBodegas }}</span>
                <span class="metric metric-green">Principales: {{ $totalPrincipales }}</span>
                <span class="metric metric-blue">Regionales: {{ $totalRegionales }}</span>

                @if($esAdmin)
                    <a href="{{ route('admin.bodegas.create') }}" class="btn-new">
                        + Nueva bodega
                    </a>
                @endif
            </div>
        </div>

        <div class="bodega-grid">
            @forelse($bodegas as $bodega)
                @php
                    $esMiBodega = (int) $bodega->id === (int) $bodegaOperadorId;
                    $isPrincipal = ($bodega->tipo === 'Principal');

                    $rutaInventario = $esOperador
                        ? route('operador.bodegas.show', $bodega->id)
                        : route('admin.bodegas.show', $bodega->id);
                @endphp

                <article class="bodega-card">
                    <div class="bodega-card-body">
                        <div>
                            <div class="bodega-info">
                                <div class="bodega-letter">
                                    {{ strtoupper(mb_substr($bodega->nombre ?? 'B', 0, 1)) }}
                                </div>

                                <div>
                                    <h2 class="bodega-name">
                                        {{ $bodega->nombre ?? ('Bodega #'.$bodega->id) }}
                                    </h2>

                                    <div class="bodega-location">
                                        {{ $bodega->ubicacion ?? 'Sin ubicación registrada' }}
                                    </div>

                                    <div class="bodega-id">
                                        ID: {{ $bodega->id }}
                                    </div>
                                </div>
                            </div>

                            @if($esOperador)
                                @if($esMiBodega)
                                    <span class="access-label access-own">Tu bodega asignada</span>
                                @else
                                    <span class="access-label access-read">Solo consulta</span>
                                @endif
                            @endif
                        </div>

                        <span class="bodega-badge {{ $isPrincipal ? 'badge-principal' : 'badge-regional' }}">
                            {{ $bodega->tipo ?? '—' }}
                        </span>
                    </div>

                    <div class="bodega-card-actions">
                        <a href="{{ $rutaInventario }}" class="action-btn btn-inventario">
                            Inventario
                        </a>

                        @if($esAdmin)
                            <a href="{{ route('admin.operaciones.traslados.create', ['origen' => $bodega->id]) }}"
                               class="action-btn btn-trasladar">
                                Trasladar
                            </a>

                            <a href="{{ route('admin.operaciones.traslados.index') }}"
                               class="action-btn btn-aprobacion">
                                Aprobación
                            </a>

                            <a href="{{ route('admin.bodegas.edit', $bodega->id) }}"
                               class="action-btn btn-editar">
                                Editar
                            </a>

                            <form method="POST"
                                  action="{{ route('admin.bodegas.destroy', $bodega->id) }}"
                                  onsubmit="return confirm('¿Eliminar esta bodega?')">
                                @csrf
                                @method('DELETE')

                                <button type="submit" class="action-btn btn-eliminar">
                                    Eliminar
                                </button>
                            </form>
                        @endif

                        @if($esOperador && $esMiBodega)
                            <a href="{{ route('operador.operaciones.traslados.create', ['origen' => $bodega->id]) }}"
                               class="action-btn btn-trasladar">
                                Trasladar
                            </a>
                        @endif
                    </div>
                </article>
            @empty
                <div class="empty-box">
                    <div style="font-weight: 800;">No hay bodegas registradas aún.</div>
                    <div style="margin-top: 4px; font-size: 13px;">Crea una nueva bodega para empezar.</div>
                </div>
            @endforelse
        </div>

        <div class="volver-wrap">
            <a href="{{ route('dashboard') }}" class="btn-volver">
                ← Volver
            </a>
        </div>

    </div>
</div>
@endsection
@extends(auth()->user()->role_id == 1 ? 'layouts.admin' : 'layouts.operador')

@section('title', 'Solicitudes de traslado')

@section('content')
@php
  $routePrefix = auth()->user()->role_id == 2 ? 'operador' : 'admin';
@endphp

<style>
  .tras-page {
    width: 100%;
    min-height: calc(100vh - 64px);
    background: #f8fafc;
    padding: 18px 14px;
  }

  .tras-container {
    width: 100%;
    max-width: 1080px;
    margin: 0 auto;
  }

  .tras-header {
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

  .tras-title {
    margin: 0;
    font-size: 22px;
    line-height: 1.1;
    font-weight: 800;
    color: #0f172a;
  }

  .tras-subtitle {
    margin-top: 5px;
    font-size: 13px;
    color: #64748b;
  }

  .tras-header-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
  }

  .btn-primary,
  .btn-secondary,
  .btn-filter,
  .btn-view,
  .btn-clear {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 30px;
    padding: 0 12px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 800;
    text-decoration: none !important;
    border: 1px solid transparent;
    cursor: pointer;
    line-height: 1;
  }

  .btn-primary {
    background: #2563eb !important;
    color: #ffffff !important;
    border-color: #2563eb !important;
  }

  .btn-primary:hover {
    background: #1d4ed8 !important;
  }

  .btn-secondary {
    background: #ffffff !important;
    color: #334155 !important;
    border-color: #cbd5e1 !important;
  }

  .btn-secondary:hover {
    background: #f8fafc !important;
  }

  .btn-filter {
    background: #0f172a !important;
    color: #ffffff !important;
    border-color: #0f172a !important;
  }

  .btn-view {
    background: #2563eb !important;
    color: #ffffff !important;
    border-color: #2563eb !important;
  }

  .btn-clear {
    background: #eff6ff !important;
    color: #1d4ed8 !important;
    border-color: #bfdbfe !important;
  }

  .tras-alert {
    margin-bottom: 12px;
    border-radius: 12px;
    padding: 10px 14px;
    font-size: 13px;
    font-weight: 600;
  }

  .tras-alert-ok {
    border: 1px solid #bbf7d0;
    background: #ecfdf5;
    color: #047857;
  }

  .tras-alert-error {
    border: 1px solid #fecaca;
    background: #fef2f2;
    color: #b91c1c;
  }

  .filter-card {
    background: #ffffff;
    border: 1px solid #dbe3ea;
    border-radius: 14px;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    padding: 12px 14px;
    margin-bottom: 12px;
  }

  .filter-grid {
    display: grid;
    grid-template-columns: 1fr 1.3fr 1.3fr auto auto;
    gap: 10px;
    align-items: end;
  }

  .field-label {
    display: block;
    font-size: 11px;
    font-weight: 800;
    color: #475569;
    margin-bottom: 4px;
  }

  .field-control {
    width: 100%;
    height: 32px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 0 10px;
    font-size: 12px;
    color: #334155;
    background: #ffffff;
  }

  .field-control:focus {
    outline: 2px solid #dbeafe;
    border-color: #3b82f6;
  }

  .tras-card {
    background: #ffffff;
    border: 1px solid #dbe3ea;
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
  }

  .tras-card-head {
    padding: 10px 14px;
    border-bottom: 1px solid #e2e8f0;
    background: #f8fafc;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
  }

  .tras-card-title {
    font-size: 13px;
    font-weight: 800;
    color: #0f172a;
  }

  .tras-table-wrap {
    overflow-x: auto;
  }

  .tras-table {
    width: 100%;
    min-width: 850px;
    border-collapse: collapse;
    font-size: 12px;
  }

  .tras-table thead {
    background: #ffffff;
    border-bottom: 1px solid #e2e8f0;
    color: #64748b;
    text-transform: uppercase;
    font-size: 11px;
    letter-spacing: .03em;
  }

  .tras-table th {
    text-align: left;
    font-weight: 800;
    padding: 9px 12px;
  }

  .tras-table td {
    padding: 10px 12px;
    border-bottom: 1px solid #eef2f7;
    vertical-align: middle;
    color: #334155;
  }

  .tras-table tbody tr:hover {
    background: #f8fafc;
  }

  .tras-id {
    font-weight: 800;
    color: #0f172a;
  }

  .badge {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 4px 10px;
    font-size: 11px;
    font-weight: 800;
    white-space: nowrap;
  }

  .badge-pendiente {
    background: #fffbeb;
    color: #b45309;
  }

  .badge-aprobado {
    background: #ecfdf5;
    color: #047857;
  }

  .badge-rechazado {
    background: #fef2f2;
    color: #b91c1c;
  }

  .badge-default {
    background: #f1f5f9;
    color: #475569;
  }

  .route-text {
    font-weight: 800;
    color: #0f172a;
  }

  .route-arrow {
    color: #94a3b8;
    margin: 0 5px;
  }

  .date-main {
    font-weight: 800;
    color: #0f172a;
  }

  .date-sub {
    margin-top: 2px;
    font-size: 11px;
    color: #64748b;
  }

  .pagination-wrap {
    margin-top: 14px;
  }

  .empty-row {
    padding: 36px 12px !important;
    text-align: center;
    color: #64748b !important;
  }

  @media (max-width: 900px) {
    .filter-grid {
      grid-template-columns: 1fr;
    }

    .tras-header {
      align-items: stretch;
    }

    .tras-header-actions {
      justify-content: flex-start;
    }
  }
</style>

<div class="tras-page">
  <div class="tras-container">
    <x-internal-navigation :back-url="route('dashboard')" />

    <div class="tras-header">
      <div>
        <h1 class="tras-title">Solicitudes de traslado</h1>
        <div class="tras-subtitle">
          @if($user->isEncargado())
            Bandeja de solicitudes para tu bodega de destino.
          @else
            Solicitudes creadas y gestionadas por tu usuario.
          @endif
        </div>
      </div>

      <div class="tras-header-actions">
        @if(!$user->isEncargado())
          <a href="{{ route($routePrefix . '.operaciones.traslados.create') }}" class="btn-primary">
            + Nueva solicitud
          </a>
        @endif
      </div>
    </div>

    @if(session('ok'))
      <div class="tras-alert tras-alert-ok">
        {{ session('ok') }}
      </div>
    @endif

    @if(session('error'))
      <div class="tras-alert tras-alert-error">
        {{ session('error') }}
      </div>
    @endif

    <div class="filter-card">
      <form method="GET" class="filter-grid">
        <div>
          <label class="field-label">Estado</label>
          <select name="estado" class="field-control">
            <option value="">Todos</option>
            <option value="PENDIENTE" {{ $estado === 'PENDIENTE' ? 'selected' : '' }}>PENDIENTE</option>
            <option value="APROBADO" {{ $estado === 'APROBADO' ? 'selected' : '' }}>APROBADO</option>
            <option value="RECHAZADO" {{ $estado === 'RECHAZADO' ? 'selected' : '' }}>RECHAZADO</option>
          </select>
        </div>

        <div>
          <label class="field-label">Origen</label>
          <select name="origen" class="field-control">
            <option value="">Todas</option>
            @foreach($bodegas as $b)
              <option value="{{ $b->id }}" {{ (string) $origen === (string) $b->id ? 'selected' : '' }}>
                {{ $b->nombre }}
              </option>
            @endforeach
          </select>
        </div>

        <div>
          <label class="field-label">Destino</label>
          <select name="destino" class="field-control">
            <option value="">Todas</option>
            @foreach($bodegas as $b)
              <option value="{{ $b->id }}" {{ (string) $destino === (string) $b->id ? 'selected' : '' }}>
                {{ $b->nombre }}
              </option>
            @endforeach
          </select>
        </div>

        <div>
          <button class="btn-filter" type="submit">
            Filtrar
          </button>
        </div>

        <div>
          <a href="{{ route($routePrefix . '.operaciones.traslados.index') }}" class="btn-clear">
            Limpiar
          </a>
        </div>
      </form>
    </div>

    <div class="tras-card">
      <div class="tras-card-head">
        <div class="tras-card-title">Listado de solicitudes</div>
        <div style="font-size: 12px; color: #64748b; font-weight: 700;">
          Total visible: {{ $operaciones->count() }}
        </div>
      </div>

      <div class="tras-table-wrap">
        <table class="tras-table">
          <thead>
            <tr>
              <th style="width: 80px;">#</th>
              <th style="width: 130px;">Estado</th>
              <th>Origen → Destino</th>
              <th style="width: 210px;">Creado</th>
              <th style="width: 110px; text-align: right;">Acción</th>
            </tr>
          </thead>

          <tbody>
            @forelse($operaciones as $op)
              @php
                $estadoItem = $op->estado;

                if ($estadoItem === 'PENDIENTE') {
                    $badge = 'badge-pendiente';
                } elseif ($estadoItem === 'APROBADO') {
                    $badge = 'badge-aprobado';
                } elseif ($estadoItem === 'RECHAZADO') {
                    $badge = 'badge-rechazado';
                } else {
                    $badge = 'badge-default';
                }
              @endphp

              <tr>
                <td>
                  <span class="tras-id">#{{ $op->id }}</span>
                </td>

                <td>
                  <span class="badge {{ $badge }}">
                    {{ $op->estado }}
                  </span>
                </td>

                <td>
                  <span class="route-text">{{ optional($op->bodegaOrigen)->nombre ?? '—' }}</span>
                  <span class="route-arrow">→</span>
                  <span class="route-text">{{ optional($op->bodegaDestino)->nombre ?? '—' }}</span>
                </td>

                <td>
                  <div class="date-main">{{ $op->created_at->format('d/m/Y H:i') }}</div>
                  <div class="date-sub">Por: {{ optional($op->creador)->name ?? '—' }}</div>
                </td>

                <td style="text-align: right;">
                  <a href="{{ route($routePrefix . '.operaciones.traslados.show', $op) }}" class="btn-view">
                    Ver
                  </a>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="5" class="empty-row">
                  No hay solicitudes registradas.
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    <div class="pagination-wrap">
      {{ $operaciones->links() }}
    </div>

  </div>
</div>
@endsection
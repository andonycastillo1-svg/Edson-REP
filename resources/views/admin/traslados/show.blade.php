@extends(auth()->user()->role_id == 1 ? 'layouts.admin' : 'layouts.operador')

@section('title', 'Solicitud #' . $operacion->id)

@section('content')
@php
  $routePrefix = auth()->user()->role_id == 2 ? 'operador' : 'admin';

  if ($operacion->estado === 'PENDIENTE') {
      $badgeClass = 'badge-pendiente';
  } elseif ($operacion->estado === 'APROBADO') {
      $badgeClass = 'badge-aprobado';
  } elseif ($operacion->estado === 'RECHAZADO') {
      $badgeClass = 'badge-rechazado';
  } else {
      $badgeClass = 'badge-default';
  }
@endphp

<style>
  .show-page {
    width: 100%;
    min-height: calc(100vh - 64px);
    background: #f8fafc;
    padding: 18px 14px;
  }

  .show-container {
    width: 100%;
    max-width: 920px;
    margin: 0 auto;
  }

  .show-header {
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

  .show-title {
    margin: 0;
    font-size: 22px;
    line-height: 1.1;
    font-weight: 800;
    color: #0f172a;
  }

  .show-subtitle {
    margin-top: 5px;
    font-size: 13px;
    color: #64748b;
  }

  .show-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
  }

  .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 32px;
    padding: 0 13px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 800;
    text-decoration: none !important;
    border: 1px solid transparent;
    cursor: pointer;
    line-height: 1;
  }

  .btn-dark {
    background: #0f172a !important;
    color: #ffffff !important;
    border-color: #0f172a !important;
  }

  .btn-blue {
    background: #2563eb !important;
    color: #ffffff !important;
    border-color: #2563eb !important;
  }

  .btn-white {
    background: #ffffff !important;
    color: #334155 !important;
    border-color: #cbd5e1 !important;
  }

  .btn-green {
    background: #059669 !important;
    color: #ffffff !important;
    border-color: #059669 !important;
  }

  .btn-red {
    background: #dc2626 !important;
    color: #ffffff !important;
    border-color: #dc2626 !important;
  }

  .alert {
    margin-bottom: 12px;
    border-radius: 12px;
    padding: 10px 14px;
    font-size: 13px;
    font-weight: 600;
  }

  .alert-ok {
    border: 1px solid #bbf7d0;
    background: #ecfdf5;
    color: #047857;
  }

  .alert-error {
    border: 1px solid #fecaca;
    background: #fef2f2;
    color: #b91c1c;
  }

  .main-card {
    background: #ffffff;
    border: 1px solid #dbe3ea;
    border-radius: 14px;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    overflow: hidden;
  }

  .summary-box {
    padding: 16px;
    border-bottom: 1px solid #e2e8f0;
  }

  .summary-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    flex-wrap: wrap;
  }

  .route-line {
    font-size: 14px;
    color: #334155;
  }

  .route-line strong {
    color: #0f172a;
  }

  .route-arrow {
    color: #94a3b8;
    margin: 0 6px;
  }

  .meta-line {
    margin-top: 8px;
    font-size: 12px;
    color: #64748b;
  }

  .meta-line strong {
    color: #334155;
  }

  .badge {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 5px 11px;
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

  .note-box {
    margin-top: 12px;
    border: 1px solid #dbe3ea;
    background: #f8fafc;
    border-radius: 10px;
    padding: 10px 12px;
    font-size: 13px;
    color: #334155;
  }

  .reject-box {
    margin-top: 12px;
    border: 1px solid #fecaca;
    background: #fef2f2;
    border-radius: 10px;
    padding: 10px 12px;
    font-size: 13px;
    color: #991b1b;
  }

  .section-title {
    padding: 12px 16px;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    font-size: 13px;
    font-weight: 800;
    color: #0f172a;
    display: flex;
    justify-content: space-between;
    gap: 10px;
  }

  .table-wrap {
    overflow-x: auto;
  }

  .items-table {
    width: 100%;
    min-width: 620px;
    border-collapse: collapse;
    font-size: 12px;
  }

  .items-table thead {
    background: #ffffff;
    border-bottom: 1px solid #e2e8f0;
    color: #64748b;
    text-transform: uppercase;
    font-size: 11px;
    letter-spacing: .03em;
  }

  .items-table th {
    text-align: left;
    font-weight: 800;
    padding: 9px 12px;
  }

  .items-table td {
    padding: 10px 12px;
    border-bottom: 1px solid #eef2f7;
    vertical-align: middle;
    color: #334155;
  }

  .product-code {
    font-weight: 800;
    color: #0f172a;
  }

  .product-name {
    margin-top: 2px;
    font-size: 11px;
    color: #64748b;
  }

  .qty {
    text-align: right;
    font-size: 14px;
    font-weight: 800;
    color: #0f172a;
  }

  .empty-products {
    padding: 24px 12px !important;
    text-align: center;
    color: #64748b !important;
  }

  .decision-box {
    padding: 14px 16px;
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
  }

  .decision-grid {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    flex-wrap: wrap;
  }

  .reject-form {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
  }

  .input {
    height: 32px;
    min-width: 260px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 0 10px;
    font-size: 12px;
    color: #334155;
    background: #ffffff;
  }

  .input:focus {
    outline: 2px solid #dbeafe;
    border-color: #3b82f6;
  }

  .bottom-actions {
    margin-top: 14px;
    display: flex;
    justify-content: flex-end;
  }

  @media (max-width: 700px) {
    .show-header {
      align-items: stretch;
    }

    .show-actions {
      justify-content: flex-start;
    }

    .decision-grid {
      justify-content: stretch;
    }

    .reject-form,
    .reject-form .input,
    .reject-form button,
    .decision-grid form {
      width: 100%;
    }
  }
</style>

<div class="show-page">
  <div class="show-container">

    <div class="show-header">
      <div>
        <h1 class="show-title">Solicitud #{{ $operacion->id }}</h1>
        <div class="show-subtitle">
          Tipo: {{ $operacion->tipo }} · Creada el {{ $operacion->created_at->format('d/m/Y H:i') }}
        </div>
      </div>

      <div class="show-actions">
        <a href="{{ route($routePrefix . '.operaciones.traslados.hoja', $operacion) }}" class="btn btn-blue">
          🧾 Hoja
        </a>


        @if($operacion->archivo_excel_path)
          <a href="{{ route($routePrefix . '.operaciones.traslados.archivo', $operacion) }}" target="_blank" rel="noopener" class="btn btn-white">
            📎 Ver Excel adjunto
          </a>
        @endif

        <a href="{{ route($routePrefix . '.operaciones.traslados.index') }}" class="btn btn-white">
          ← Volver
        </a>
      </div>
    </div>

    @if(session('ok'))
      <div class="alert alert-ok">
        {{ session('ok') }}
      </div>
    @endif

    @if(session('error'))
      <div class="alert alert-error">
        {{ session('error') }}
      </div>
    @endif

    <div class="main-card">

      <div class="summary-box">
        <div class="summary-top">
          <div>
            <div class="route-line">
              <strong>Origen:</strong> {{ optional($operacion->bodegaOrigen)->nombre ?? '—' }}
              <span class="route-arrow">→</span>
              <strong>Destino:</strong> {{ optional($operacion->bodegaDestino)->nombre ?? '—' }}
            </div>

            <div class="meta-line">
              Creado por:
              <strong>{{ optional($operacion->creador)->name ?? '—' }}</strong>
              · {{ $operacion->created_at->format('d/m/Y H:i') }}
            </div>
          </div>

          <span class="badge {{ $badgeClass }}">
            {{ $operacion->estado }}
          </span>
        </div>

        @if($operacion->observacion)
          <div class="note-box">
            <strong>Observación:</strong> {{ $operacion->observacion }}
          </div>
        @endif

        @if($operacion->estado === 'RECHAZADO' && $operacion->motivo_rechazo)
          <div class="reject-box">
            <strong>Motivo de rechazo:</strong> {{ $operacion->motivo_rechazo }}
          </div>
        @endif
      </div>

      <div class="section-title">
        <span>Productos solicitados</span>
        <span>{{ $operacion->detalles->count() }} producto(s)</span>
      </div>

      <div class="table-wrap">
        <table class="items-table">
          <thead>
            <tr>
              <th>Producto</th>
              <th style="width: 150px; text-align: right;">Cantidad</th>
            </tr>
          </thead>

          <tbody>
            @forelse($operacion->detalles as $d)
              <tr>
                <td>
                  <div class="product-code">{{ $d->producto_codigo }}</div>
                  <div class="product-name">
                    {{ optional($d->producto)->descripcion ?? optional($d->producto)->nombre ?? 'Producto sin nombre' }}
                  </div>
                </td>

                <td class="qty">
                  {{ $d->cantidad }}
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="2" class="empty-products">
                  Esta solicitud no tiene productos asociados.
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      @if($puedeDecidir)
        <div class="decision-box">
          <div class="decision-grid">

            <form method="POST" action="{{ route($routePrefix . '.operaciones.traslados.aprobar', $operacion) }}">
              @csrf
              <button class="btn btn-green">
                ✅ Aprobar
              </button>
            </form>

            <form method="POST"
                  action="{{ route($routePrefix . '.operaciones.traslados.rechazar', $operacion) }}"
                  class="reject-form">
              @csrf

              <input name="motivo_rechazo"
                     required
                     class="input"
                     placeholder="Motivo de rechazo...">

              <button class="btn btn-red">
                ❌ Rechazar
              </button>
            </form>

          </div>
        </div>
      @endif

    </div>
  </div>
</div>
@endsection
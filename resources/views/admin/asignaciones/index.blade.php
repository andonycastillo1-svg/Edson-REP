@extends('layouts.app')

@section('no_nav')
@endsection

@section('content')
@php($puedeGestionarPdfsFirmados = \Illuminate\Support\Facades\Schema::hasTable('asignacion_inventario_archivos'))

<style>
  .asg-page {
    min-height: 100vh;
    background: #f8fafc;
    padding: 28px 16px;
  }

  .asg-container {
    max-width: 1280px;
    margin: 0 auto;
  }

  .asg-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    margin-bottom: 18px;
  }

  .asg-title {
    margin: 0;
    font-size: 22px;
    font-weight: 700;
    color: #0f172a;
  }

  .asg-subtitle {
    margin: 4px 0 0;
    font-size: 13px;
    color: #64748b;
  }

  .asg-actions {
    display: flex;
    gap: 8px;
  }

  .asg-btn-primary,
  .asg-btn-secondary {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 34px;
    padding: 0 14px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    border: 1px solid transparent;
  }

  .asg-btn-primary {
    background: #2563eb;
    color: #fff;
  }

  .asg-btn-primary:hover {
    background: #1d4ed8;
  }

  .asg-btn-secondary {
    background: #fff;
    color: #334155;
    border-color: #dbe3ea;
  }

  .asg-btn-secondary:hover {
    background: #f8fafc;
  }

  .asg-alert {
    margin-bottom: 14px;
    border-radius: 10px;
    padding: 10px 14px;
    font-size: 13px;
  }

  .asg-alert-success {
    border: 1px solid #bbf7d0;
    background: #f0fdf4;
    color: #166534;
  }

  .asg-alert-error {
    border: 1px solid #fecaca;
    background: #fef2f2;
    color: #991b1b;
  }

  .asg-panel {
    background: #fff;
    border: 1px solid #dbe3ea;
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
  }

  .asg-panel-title {
    padding: 12px 16px;
    background: #f8fafc;
    border-bottom: 1px solid #dbe3ea;
    font-size: 14px;
    font-weight: 700;
    color: #0f172a;
  }

  .asg-collab {
    border-bottom: 1px solid #eef2f7;
  }

  .asg-collab:last-child {
    border-bottom: none;
  }

  .asg-collab summary {
    list-style: none;
  }

  .asg-collab summary::-webkit-details-marker {
    display: none;
  }

  .asg-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    min-height: 58px;
    padding: 10px 16px;
    cursor: pointer;
    background: #fff;
  }

  .asg-row:hover {
    background: #f8fafc;
  }

  .asg-left {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 280px;
    flex: 1;
  }

  .asg-arrow {
    font-size: 12px;
    color: #94a3b8;
    transition: transform .15s ease;
  }

  .asg-collab[open] .asg-arrow {
    transform: rotate(90deg);
  }

  .asg-avatar {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: #eff6ff;
    color: #1d4ed8;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: 700;
    flex: 0 0 auto;
  }

  .asg-name {
    margin: 0;
    font-size: 14px;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.2;
  }

  .asg-code {
    margin: 3px 0 0;
    font-size: 12px;
    color: #64748b;
  }

  .asg-metrics {
    display: flex;
    align-items: center;
    gap: 22px;
    flex: 0 0 auto;
  }

  .asg-metric-label {
    display: block;
    font-size: 10px;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: .04em;
    line-height: 1;
  }

  .asg-metric-value {
    display: block;
    margin-top: 4px;
    font-size: 13px;
    font-weight: 700;
    color: #0f172a;
  }

  .asg-pill {
    display: inline-flex;
    margin-top: 4px;
    padding: 2px 9px;
    border-radius: 999px;
    background: #eff6ff;
    color: #1d4ed8;
    font-size: 12px;
    font-weight: 700;
  }

  .asg-detail-btn {
    border: 1px solid #dbe3ea;
    background: #fff;
    color: #334155;
    border-radius: 7px;
    padding: 6px 10px;
    font-size: 12px;
    font-weight: 700;
  }

  .asg-collab[open] .asg-detail-btn {
    background: #0f172a;
    color: #fff;
    border-color: #0f172a;
  }

  .asg-detail {
    background: #f8fafc;
    border-top: 1px solid #dbe3ea;
    padding: 14px;
  }

  .asg-mini-card {
    background: #fff;
    border: 1px solid #dbe3ea;
    border-radius: 10px;
    padding: 12px;
    margin-bottom: 12px;
  }

  .asg-mini-card form {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
  }

  .asg-mini-title {
    margin: 0;
    font-size: 13px;
    font-weight: 700;
    color: #0f172a;
  }

  .asg-mini-text {
    margin: 2px 0 0;
    font-size: 11px;
    color: #64748b;
  }

  .asg-input {
    height: 32px;
    border: 1px solid #cbd5e1;
    border-radius: 7px;
    padding: 0 10px;
    font-size: 12px;
    color: #334155;
    background: #fff;
  }

  .asg-input:focus {
    outline: 2px solid #dbeafe;
    border-color: #3b82f6;
  }

  .asg-btn-warning {
    height: 32px;
    border: 1px solid #fcd34d;
    background: #fffbeb;
    color: #92400e;
    border-radius: 7px;
    padding: 0 12px;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
  }

  .asg-btn-warning:hover {
    background: #fef3c7;
  }

  .asg-table-wrap {
    background: #fff;
    border: 1px solid #dbe3ea;
    border-radius: 10px;
    overflow: hidden;
  }

  .asg-table-scroll {
    overflow-x: auto;
  }

  .asg-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
    min-width: 1080px;
  }

  .asg-table thead {
    background: #f8fafc;
    color: #64748b;
    text-transform: uppercase;
    font-size: 11px;
  }

  .asg-table th,
  .asg-table td {
    padding: 8px 10px;
    border-bottom: 1px solid #eef2f7;
    vertical-align: middle;
  }

  .asg-table th {
    text-align: left;
    font-weight: 700;
  }

  .asg-product {
    font-weight: 700;
    color: #0f172a;
  }

  .asg-product-code {
    margin-top: 2px;
    color: #64748b;
    font-size: 11px;
  }

  .asg-status {
    display: inline-flex;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
  }

  .asg-status-green {
    background: #ecfdf5;
    color: #047857;
  }

  .asg-status-amber {
    background: #fffbeb;
    color: #b45309;
  }

  .asg-status-red {
    background: #fef2f2;
    color: #b91c1c;
  }

  .asg-status-gray {
    background: #f1f5f9;
    color: #475569;
  }

  .asg-small-input {
    width: 56px;
    height: 28px;
    border: 1px solid #cbd5e1;
    border-radius: 7px;
    text-align: center;
    font-size: 12px;
  }

  .asg-pdf-btn {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    height: 28px;
    padding: 0 10px;
    border-radius: 7px;
    background: #2563eb;
    color: #fff;
    text-decoration: none;
    font-size: 11px;
    font-weight: 700;
  }

  .asg-pdf-btn:hover {
    background: #1d4ed8;
  }

  .asg-upload-form {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }

  .asg-upload-row {
    display: flex;
    gap: 6px;
    align-items: center;
  }

  .asg-file {
    width: 160px;
    font-size: 11px;
  }

  .asg-upload-btn {
    height: 28px;
    border: 1px solid #cbd5e1;
    background: #fff;
    border-radius: 7px;
    padding: 0 10px;
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
  }

  .asg-footer-actions {
    padding: 10px 12px;
    background: #f8fafc;
    border-top: 1px solid #dbe3ea;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
  }

  .asg-footer-title {
    margin: 0;
    font-size: 12px;
    font-weight: 700;
    color: #0f172a;
  }

  .asg-footer-text {
    margin: 2px 0 0;
    font-size: 11px;
    color: #64748b;
  }

  .asg-history {
    margin-top: 18px;
  }

  @media (max-width: 768px) {
    .asg-header,
    .asg-row,
    .asg-mini-card form,
    .asg-footer-actions {
      flex-direction: column;
      align-items: stretch;
    }

    .asg-actions,
    .asg-metrics {
      width: 100%;
      justify-content: space-between;
    }

    .asg-left {
      min-width: 0;
    }

    .asg-upload-row {
      flex-direction: column;
      align-items: stretch;
    }

    .asg-file {
      width: 100%;
      max-width: 100%;
    }
  }
</style>

<div class="asg-page">
  <div class="asg-container">

    <div class="asg-header">
      <div>
        <h1 class="asg-title">Mis asignaciones</h1>
        <p class="asg-subtitle">Control de equipos asignados, documentos firmados y devoluciones.</p>
      </div>

      <div class="asg-actions">
        <a href="{{ route($routePrefix . '.asignaciones.create') }}" class="asg-btn-primary">
          + Nueva asignación
        </a>
      </div>
    </div>

    @if(session('success'))
      <div class="asg-alert asg-alert-success">
        {{ session('success') }}

        @if(session('grupo_devolucion'))
          <div style="margin-top:6px;">
            <a href="{{ route($routePrefix . '.asignaciones.hoja_devolucion', session('grupo_devolucion')) }}"
               target="_blank"
               rel="noopener noreferrer"
               style="font-weight:700; text-decoration:underline;">
              Ver hoja de devolución
            </a>
          </div>
        @endif
      </div>
    @endif

    @if(session('error'))
      <div class="asg-alert asg-alert-error">
        {{ session('error') }}
      </div>
    @endif

    <div class="asg-panel">
      <div class="asg-panel-title">
        Colaboradores con asignaciones
      </div>

      @forelse($asignacionesPorColaborador as $grupo)

        @php
          $bulkFormId = 'bulk-return-' . $grupo['colaborador_codigo'];
        @endphp

        <form id="{{ $bulkFormId }}" method="POST" action="{{ route($routePrefix . '.asignaciones.devolver_lote') }}">
          @csrf
        </form>

        <details class="asg-collab">
          <summary>
            <div class="asg-row">
              <div class="asg-left">
                <span class="asg-arrow">▶</span>

                <div class="asg-avatar">
                  {{ strtoupper(mb_substr($grupo['colaborador_nombre'] ?? 'C', 0, 1)) }}
                </div>

                <div>
                  <p class="asg-name">{{ $grupo['colaborador_nombre'] }}</p>
                  <p class="asg-code">Código: <strong>{{ $grupo['colaborador_codigo'] }}</strong></p>
                </div>
              </div>

              <div class="asg-metrics">
                <div>
                  <span class="asg-metric-label">Asignaciones</span>
                  <span class="asg-metric-value">{{ $grupo['asignaciones']->count() }}</span>
                </div>

                <div>
                  <span class="asg-metric-label">Activas</span>
                  <span class="asg-pill">{{ $grupo['total_activo'] }}</span>
                </div>

                <span class="asg-detail-btn">Ver detalle</span>
              </div>
            </div>
          </summary>

          <div class="asg-detail">

            <div class="asg-mini-card">
              <form method="POST"
                    action="{{ route($routePrefix . '.asignaciones.devolver_todo_colaborador', $grupo['colaborador_codigo']) }}">
                @csrf

                <div>
                  <p class="asg-mini-title">Acciones del colaborador</p>
                  <p class="asg-mini-text">Puedes devolver todo lo activo o seleccionar productos específicos.</p>
                </div>

                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                  <input type="text"
                         name="detalle_devolucion"
                         placeholder="Motivo devolución total"
                         class="asg-input"
                         style="width:260px;">

                  <button type="submit"
                          class="asg-btn-warning"
                          onclick="return confirm('¿Devolver todo lo activo de este colaborador?')">
                    Devolver todo
                  </button>
                </div>
              </form>
            </div>

            <div class="asg-table-wrap">
              <div class="asg-table-scroll">
                <table class="asg-table">
                  <thead>
                    <tr>
                      <th>Sel.</th>
                      <th>Producto</th>
                      <th>Fecha</th>
                      <th>Bodega</th>
                      <th>Estado</th>
                      <th>Vida útil</th>
                      <th style="text-align:center;">Activa</th>
                      <th style="text-align:center;">Dev.</th>
                      <th style="text-align:center;">PDF</th>
                      <th>Firmado</th>
                    </tr>
                  </thead>

                  <tbody>
                    @foreach($grupo['asignaciones'] as $a)
                      @php
                        $estado = $a->estado ?? 'Activa';
                        $activa = $estado === 'Activa' && (int) $a->cantidad_asignada > 0;
                        $productoNombre = optional($a->producto)->descripcion ?? optional($a->producto)->nombre ?? $a->producto_codigo;

                        $fechaVenc = !empty($a->fecha_vencimiento)
                          ? \Carbon\Carbon::parse($a->fecha_vencimiento)
                          : null;
                      @endphp

                      <tr>
                        <td>
                          @if($activa)
                            <input type="checkbox"
                                   name="seleccionadas[]"
                                   value="{{ $a->id }}"
                                   form="{{ $bulkFormId }}"
                                   class="selector"
                                   data-target="devolucion_{{ $a->id }}">
                          @else
                            —
                          @endif
                        </td>

                        <td>
                          <div class="asg-product">{{ $productoNombre }}</div>
                          <div class="asg-product-code">COD: {{ $a->producto_codigo }}</div>
                        </td>

                        <td>
                          {{ $a->fecha ? date('d/m/Y', strtotime($a->fecha)) : '—' }}
                        </td>

                        <td>
                          {{ optional($a->bodega)->nombre ?? '—' }}
                        </td>

                        <td>
                          @if($estado === 'Activa')
                            <span class="asg-status asg-status-green">Activa</span>
                          @elseif($estado === 'Dañada')
                            <span class="asg-status asg-status-red">Dañada</span>
                          @else
                            <span class="asg-status asg-status-gray">{{ $estado }}</span>
                          @endif
                        </td>

                        <td>
                          @if($fechaVenc && $fechaVenc->isFuture())
                            <span class="asg-status asg-status-green">Dentro de vida útil</span>
                          @elseif($fechaVenc)
                            <span class="asg-status asg-status-amber">Depreciado</span>
                          @else
                            <span style="color:#94a3b8;">Sin dato</span>
                          @endif
                        </td>

                        <td style="text-align:center; font-weight:700;">
                          {{ $a->cantidad_asignada }}
                        </td>

                        <td style="text-align:center;">
                          @if($activa)
                            <input type="number"
                                   id="devolucion_{{ $a->id }}"
                                   name="devoluciones[{{ $a->id }}]"
                                   form="{{ $bulkFormId }}"
                                   min="1"
                                   max="{{ $a->cantidad_asignada }}"
                                   value="1"
                                   disabled
                                   class="asg-small-input">
                          @else
                            <span style="color:#94a3b8;">No aplica</span>
                          @endif
                        </td>

                        <td style="text-align:center;">
                          @if(!empty($a->grupo_asignacion))
                            <a href="{{ route($routePrefix . '.asignaciones.pdf', $a->grupo_asignacion) }}"
                               target="_blank"
                               rel="noopener noreferrer"
                               class="asg-pdf-btn">
                              PDF
                            </a>
                          @else
                            <span style="color:#94a3b8;">Sin grupo</span>
                          @endif
                        </td>

                        <td>
                          @php($pdfAsignacionFirmado = $puedeGestionarPdfsFirmados ? ($a->pdfAsignacionFirmado ?? null) : null)
                          <form method="POST"
                                action="{{ route($routePrefix . '.asignaciones.upload_pdf_firmado', $a) }}"
                                enctype="multipart/form-data"
                                class="asg-upload-form">
                            @csrf

                            <div class="asg-upload-row">
                              <input type="file"
                                     name="pdf_firmado"
                                     accept="application/pdf,.pdf"
                                     required
                                     class="asg-file">

                              <button type="submit" class="asg-upload-btn">
                                {{ $pdfAsignacionFirmado ? 'Reemplazar' : 'Subir' }}
                              </button>
                            </div>

                            @if($pdfAsignacionFirmado)
                              <a href="{{ route($routePrefix . '.asignaciones.ver_pdf_firmado', $pdfAsignacionFirmado) }}"
                                 target="_blank"
                                 style="font-size:11px; font-weight:700; color:#047857;">
                                Ver firmado: {{ $pdfAsignacionFirmado->nombre_original ?? 'PDF firmado' }}
                              </a>
                            @endif
                          </form>
                        </td>
                      </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>

              <div class="asg-footer-actions">
                <div>
                  <p class="asg-footer-title">Devolución de seleccionados</p>
                  <p class="asg-footer-text">Marca asignaciones y coloca la cantidad a devolver.</p>
                </div>

                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                  <input type="text"
                         name="detalle_devolucion"
                         form="{{ $bulkFormId }}"
                         placeholder="Detalle devolución múltiple"
                         class="asg-input"
                         style="width:260px;">

                  <button type="submit"
                          form="{{ $bulkFormId }}"
                          class="asg-btn-warning">
                    Devolver seleccionados
                  </button>
                </div>
              </div>
            </div>

          </div>
        </details>

      @empty
        <div style="padding:32px; text-align:center; color:#64748b;">
          Aún no tienes asignaciones registradas.
        </div>
      @endforelse
    </div>

    <div class="asg-panel asg-history">
      <div class="asg-panel-title">
        Historial de movimientos
      </div>

      <div class="asg-table-scroll">
        <table class="asg-table">
          <thead>
            <tr>
              <th>Fecha</th>
              <th>Tipo</th>
              <th>Colaborador</th>
              <th style="text-align:center;">Cantidad</th>
              <th>Detalle</th>
              <th>Usuario</th>
              <th style="text-align:center;">Documento</th>
            </tr>
          </thead>

          <tbody>
            @forelse($movimientos as $m)
              <tr>
                <td>{{ $m->created_at?->format('d/m/Y H:i') }}</td>

                <td>
                  <span class="asg-status asg-status-gray">
                    {{ $m->tipo }}
                  </span>
                </td>

                <td>
                  {{ optional(optional($m->asignacion)->colaborador)->nombre ?? '—' }}
                </td>

                <td style="text-align:center; font-weight:700;">
                  {{ $m->cantidad }}
                </td>

                <td>
                  {{ $m->detalle ?? '—' }}
                </td>

                <td>
                  {{ optional($m->user)->name ?? '—' }}
                </td>

                <td style="text-align:center;">
                  @if($m->tipo === 'Devolucion' && !empty($m->grupo_devolucion))
                    @php($pdfDevolucionFirmado = $puedeGestionarPdfsFirmados ? $pdfsDevolucionFirmados->get($m->grupo_devolucion) : null)
                    <div class="asg-upload-form">
                      <a href="{{ route($routePrefix . '.asignaciones.hoja_devolucion', $m->grupo_devolucion) }}"
                         target="_blank"
                         rel="noopener noreferrer"
                         class="asg-pdf-btn">
                        Ver hoja
                      </a>

                      <form method="POST"
                            action="{{ route($routePrefix . '.asignaciones.upload_pdf_devolucion_firmado', $m->grupo_devolucion) }}"
                            enctype="multipart/form-data"
                            class="asg-upload-form">
                        @csrf
                        <div class="asg-upload-row">
                          <input type="file" name="pdf_firmado" accept="application/pdf,.pdf" required class="asg-file">
                          <button type="submit" class="asg-upload-btn">
                            {{ $pdfDevolucionFirmado ? 'Reemplazar firmado' : 'Subir firmado' }}
                          </button>
                        </div>
                      </form>

                      @if($pdfDevolucionFirmado)
                        <a href="{{ route($routePrefix . '.asignaciones.ver_pdf_firmado', $pdfDevolucionFirmado) }}"
                           target="_blank"
                           style="font-size:11px; font-weight:700; color:#047857;">
                          Ver firmado: {{ $pdfDevolucionFirmado->nombre_original ?? 'PDF firmado' }}
                        </a>
                      @endif
                    </div>
                  @else
                    <span style="color:#94a3b8;">—</span>
                  @endif
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="7" style="padding:28px; text-align:center; color:#64748b;">
                  Sin movimientos registrados.
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<script>
  document.querySelectorAll('.selector').forEach((checkbox) => {
    checkbox.addEventListener('change', function () {
      const targetId = this.dataset.target;
      const input = document.getElementById(targetId);

      if (!input) return;

      input.disabled = !this.checked;

      if (!this.checked) {
        input.value = 1;
      }
    });
  });

  document.querySelectorAll('form[id^="bulk-return-"]').forEach((form) => {
    form.addEventListener('submit', function (event) {
      const selected = document.querySelectorAll('[form="' + form.id + '"].selector:checked');

      if (!selected.length) {
        event.preventDefault();
        alert('Selecciona al menos una asignación para devolver.');
      }
    });
  });
</script>
@endsection
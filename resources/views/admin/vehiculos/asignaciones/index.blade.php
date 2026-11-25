@extends('layouts.admin')

@section('title', 'Asignaciones de vehículos')

@section('content')
<style>
    .veh-page {
        max-width: 1180px;
        margin: 0 auto;
    }

    .veh-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
        margin-bottom: 22px;
    }

    .veh-kicker {
        font-size: 13px;
        font-weight: 800;
        color: var(--ui-primary);
        text-transform: uppercase;
        letter-spacing: .08em;
        margin-bottom: 4px;
    }

    .veh-title {
        font-size: 28px;
        font-weight: 800;
        color: var(--ui-text);
        margin: 0;
    }

    .veh-subtitle {
        color: var(--ui-muted);
        font-size: 14px;
        margin-top: 4px;
    }

    .veh-top-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .veh-btn {
        border-radius: 10px;
        padding: 10px 14px;
        font-size: 14px;
        font-weight: 700;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid transparent;
        cursor: pointer;
        white-space: nowrap;
    }

    .veh-btn-light {
        background: var(--ui-surface);
        color: var(--ui-text);
        border-color: var(--ui-border);
    }

    .veh-btn-light:hover {
        background: var(--ui-subtle);
    }

    .veh-btn-primary {
        background: var(--ui-primary);
        color: var(--ui-surface);
        border-color: var(--ui-primary);
    }

    .veh-btn-primary:hover {
        background: var(--ui-primary);
    }

    .veh-alert-success {
        border: 1px solid var(--ui-success-border);
        background: var(--ui-success-bg);
        color: var(--ui-success-dark);
        padding: 12px 14px;
        border-radius: 12px;
        margin-bottom: 14px;
        font-weight: 600;
    }

    .veh-alert-error {
        border: 1px solid var(--ui-danger-border);
        background: var(--ui-danger-bg);
        color: var(--ui-danger-dark);
        padding: 12px 14px;
        border-radius: 12px;
        margin-bottom: 14px;
        font-weight: 600;
    }

    .filters-card {
        display: grid;
        grid-template-columns: 1fr 220px;
        gap: 12px;
        background: var(--ui-surface);
        border: 1px solid var(--ui-border);
        border-radius: 16px;
        padding: 14px;
        margin-bottom: 14px;
        box-shadow: var(--ui-shadow-sm);
    }

    .filters-card input,
    .filters-card select {
        border: 1px solid var(--ui-border);
        border-radius: 10px;
        padding: 10px 12px;
        min-height: 42px;
        width: 100%;
    }

    .compact-list {
        background: var(--ui-surface);
        border: 1px solid var(--ui-border);
        border-radius: 18px;
        box-shadow: var(--ui-shadow-sm);
        overflow: hidden;
    }

    .compact-row {
        border-bottom: 1px solid var(--ui-subtle);
    }

    .compact-row:last-child {
        border-bottom: 0;
    }

    .compact-summary {
        display: grid;
        grid-template-columns: 1.45fr 1.05fr .8fr auto;
        gap: 14px;
        align-items: center;
        padding: 14px 16px;
    }

    .compact-summary:hover {
        background: var(--ui-bg);
    }

    .info-main {
        font-size: 15px;
        font-weight: 800;
        color: var(--ui-text);
    }

    .info-sub {
        font-size: 12px;
        color: var(--ui-muted);
        margin-top: 2px;
    }

    .badge {
        display: inline-flex;
        border-radius: 999px;
        padding: 6px 12px;
        font-size: 12px;
        font-weight: 800;
        white-space: nowrap;
    }

    .badge-active {
        background: var(--ui-success-bg);
        color: var(--ui-success-dark);
    }

    .badge-closed {
        background: var(--ui-border);
        color: var(--ui-text);
    }

    .row-actions,
    .action-row {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .row-actions {
        justify-content: flex-end;
    }

    .small-btn {
        border: 1px solid var(--ui-border);
        background: var(--ui-surface);
        color: var(--ui-text);
        border-radius: 10px;
        padding: 8px 11px;
        font-weight: 700;
        font-size: 13px;
        text-decoration: none;
        cursor: pointer;
        white-space: nowrap;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .small-btn:hover {
        background: var(--ui-subtle);
    }

    .blue-btn {
        border-color: var(--ui-info-border);
        background: var(--ui-info-bg);
        color: var(--ui-primary);
    }

    .blue-btn:hover {
        background: var(--ui-tint);
    }

    .green-btn {
        border-color: var(--ui-success-border);
        background: var(--ui-success-bg);
        color: var(--ui-success-dark);
    }

    .green-btn:hover {
        background: var(--ui-success-bg);
    }

    .danger-outline-btn {
        border-color: var(--ui-danger-border);
        background: var(--ui-surface);
        color: var(--ui-danger);
    }

    .danger-outline-btn:hover {
        background: var(--ui-danger-bg);
    }

    .detail-area {
        display: none;
        padding: 0 16px 16px;
    }

    .detail-area.open {
        display: block;
    }

    .detail-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px;
        background: var(--ui-bg);
        border: 1px solid var(--ui-border);
        border-radius: 14px;
        padding: 14px;
    }

    .panel {
        background: var(--ui-surface);
        border: 1px solid var(--ui-border);
        border-radius: 14px;
        padding: 14px;
    }

    .panel-full {
        grid-column: 1 / -1;
    }

    .panel-title {
        font-size: 14px;
        font-weight: 800;
        color: var(--ui-text);
        margin-bottom: 10px;
    }

    .panel-description {
        font-size: 12px;
        color: var(--ui-muted);
        margin-bottom: 12px;
    }

    .document-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }

    .document-box {
        border: 1px solid var(--ui-border);
        border-radius: 12px;
        padding: 12px;
        background: var(--ui-surface);
    }

    .document-title {
        font-size: 13px;
        font-weight: 800;
        color: var(--ui-text);
        margin-bottom: 8px;
    }

    .upload-form {
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 8px;
        align-items: center;
        margin-top: 10px;
    }

    .upload-form input[type="file"] {
        border: 1px solid var(--ui-border);
        border-radius: 10px;
        padding: 8px;
        background: var(--ui-surface);
        width: 100%;
        font-size: 13px;
    }

    .upload-btn {
        border: 1px solid var(--ui-border);
        background: var(--ui-surface);
        color: var(--ui-text);
        border-radius: 10px;
        padding: 9px 11px;
        font-weight: 800;
        cursor: pointer;
        white-space: nowrap;
        font-size: 13px;
    }

    .upload-btn:hover {
        background: var(--ui-subtle);
    }

    .close-form {
        display: grid;
        grid-template-columns: 150px 1fr 1fr auto;
        gap: 10px;
        align-items: center;
    }

    .close-form input {
        border: 1px solid var(--ui-border);
        border-radius: 10px;
        padding: 9px 11px;
        min-height: 40px;
        width: 100%;
        background: var(--ui-surface);
    }

    .danger-btn {
        border: 1px solid var(--ui-danger);
        background: var(--ui-surface);
        color: var(--ui-danger);
        border-radius: 10px;
        padding: 9px 12px;
        font-weight: 800;
        cursor: pointer;
        white-space: nowrap;
    }

    .danger-btn:hover {
        background: var(--ui-danger-bg);
    }

    .empty-box {
        text-align: center;
        color: var(--ui-muted);
        padding: 38px;
        background: var(--ui-surface);
        border: 1px solid var(--ui-border);
        border-radius: 18px;
    }

    .muted-line {
        font-size: 12px;
        color: var(--ui-muted);
        margin-top: 8px;
        line-height: 1.4;
    }

    .quick-info {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px;
    }

    .quick-info-item {
        background: var(--ui-bg);
        border: 1px solid var(--ui-border);
        border-radius: 12px;
        padding: 10px;
    }

    .quick-info-label {
        font-size: 11px;
        color: var(--ui-muted);
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .04em;
        margin-bottom: 3px;
    }

    .quick-info-value {
        font-size: 13px;
        color: var(--ui-text);
        font-weight: 800;
    }

    @media (max-width: 1000px) {
        .compact-summary {
            grid-template-columns: 1fr 1fr;
        }

        .detail-grid,
        .document-grid {
            grid-template-columns: 1fr;
        }

        .panel-full {
            grid-column: auto;
        }

        .close-form {
            grid-template-columns: 1fr;
        }

        .upload-form {
            grid-template-columns: 1fr;
        }

        .quick-info {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 700px) {
        .veh-header {
            flex-direction: column;
        }

        .filters-card {
            grid-template-columns: 1fr;
        }

        .compact-summary {
            grid-template-columns: 1fr;
        }

        .row-actions {
            justify-content: flex-start;
        }
    }
</style>

<div class="ui-panel p-6 md:p-8">
    <x-internal-navigation :back-url="route('admin.vehiculos.index')" />
    <div class="veh-page">
        <div class="veh-header">
            <div>
                <div class="veh-kicker">Flota</div>
                <h1 class="ui-page-title veh-title">Asignaciones de vehículos</h1>
                <p class="veh-subtitle">
                    Busca por vehículo, placa, VIN o colaborador y gestiona documentos firmados.
                </p>
            </div>

            <div class="veh-top-actions">
                @rutaDisponible('admin.vehiculos.asignaciones.create')
                <a href="{{ route('admin.vehiculos.asignaciones.create') }}" class="veh-btn veh-btn-primary">+ Nueva asignación</a>
                @endrutaDisponible
            </div>
        </div>

        <div class="filters-card">
            <input
                type="text"
                id="buscarAsignacion" maxlength="190"
                placeholder="Buscar por vehículo, placa, VIN o colaborador..."
                onkeyup="filtrarAsignaciones()"
            >

            <select id="filtroEstado" onchange="filtrarAsignaciones()">
                <option value="todos">Todos los estados</option>
                <option value="activa">Activas</option>
                <option value="cerrada">Cerradas</option>
            </select>
            <x-ui.exportar-excel modulo="vehiculos" :campos="['buscar' => 'buscarAsignacion', 'estado' => 'filtroEstado']" />
        </div>

        <div class="compact-list">
            @forelse($asignaciones as $a)
                @php
                    $estadoTexto = $a->activa ? 'activa' : 'cerrada';

                    $textoBusqueda = strtolower(
                        (optional($a->vehiculo)->marca ?? '') . ' ' .
                        (optional($a->vehiculo)->modelo ?? '') . ' ' .
                        (optional($a->vehiculo)->placa ?? '') . ' ' .
                        ($a->vehiculo_vin ?? '') . ' ' .
                        (optional($a->colaborador)->nombre ?? '') . ' ' .
                        ($a->colaborador_codigo ?? '')
                    );

                    $pdfAsignacionFirmado = $a->pdfAsignacionFirmado ?? null;
                    $pdfDevolucionFirmado = $a->pdfDevolucionFirmado ?? null;

                    $vehiculoNombre = trim(
                        (optional($a->vehiculo)->marca ?? 'Sin marca') .
                        (optional($a->vehiculo)->modelo ? ' - '.optional($a->vehiculo)->modelo : '')
                    );
                @endphp

                <div
                    class="compact-row asignacion-item"
                    data-estado="{{ $estadoTexto }}"
                    data-search="{{ $textoBusqueda }}"
                >
                    <div class="compact-summary">
                        <div>
                            <div class="info-main">{{ $vehiculoNombre }}</div>
                            <div class="info-sub">
                                Placa: {{ optional($a->vehiculo)->placa ?? 'Sin placa' }} · VIN: {{ $a->vehiculo_vin ?? '—' }}
                            </div>
                        </div>

                        <div>
                            <div class="info-main">{{ optional($a->colaborador)->nombre ?? 'Sin colaborador' }}</div>
                            <div class="info-sub">Código: {{ $a->colaborador_codigo ?? '—' }}</div>
                        </div>

                        <div>
                            <div class="info-main">{{ optional($a->fecha_inicio)?->format('d/m/Y') ?? '—' }}</div>
                            <div class="info-sub">
                                @if($a->activa)
                                    <span class="badge badge-active">Activa</span>
                                @else
                                    <span class="badge badge-closed">Cerrada</span>
                                @endif
                            </div>
                        </div>

                        <div class="row-actions">
                            @rutaDisponible('admin.vehiculos.productos.index')
                            <a class="small-btn blue-btn"
                               href="{{ route('admin.vehiculos.productos.index', ['vehiculo_vin' => $a->vehiculo_vin]) }}">
                                Productos
                            </a>
                            @endrutaDisponible

                            <button type="button" class="small-btn" onclick="toggleDetalle('detalle-{{ $a->id }}')">
                                Gestionar
                            </button>
                        </div>
                    </div>

                    <div id="detalle-{{ $a->id }}" class="detail-area">
                        <div class="detail-grid">
                            <div class="panel panel-full">
                                <div class="panel-title">Resumen de la asignación</div>

                                <div class="quick-info">
                                    <div class="quick-info-item">
                                        <div class="quick-info-label">Fecha asignación</div>
                                        <div class="quick-info-value">{{ optional($a->fecha_inicio)?->format('d/m/Y') ?? '—' }}</div>
                                    </div>

                                    <div class="quick-info-item">
                                        <div class="quick-info-label">Fecha desasignación</div>
                                        <div class="quick-info-value">{{ optional($a->fecha_fin)?->format('d/m/Y') ?? '—' }}</div>
                                    </div>

                                    <div class="quick-info-item">
                                        <div class="quick-info-label">VIN</div>
                                        <div class="quick-info-value">{{ $a->vehiculo_vin ?? '—' }}</div>
                                    </div>
                                </div>
                            </div>

                            <div class="panel panel-full">
                                <div class="panel-title">Documentos PDF</div>
                                <div class="panel-description">
                                    Aquí puedes abrir los PDFs generados y cargar los documentos ya firmados.
                                </div>

                                <div class="document-grid">
                                    <div class="document-box">
                                        <div class="document-title">Asignación</div>

                                        <div class="action-row">
                                            @rutaDisponible('admin.vehiculos.asignaciones.pdf_asignacion')
                                            <a class="small-btn" target="_blank" rel="noopener noreferrer"
                                               href="{{ route('admin.vehiculos.asignaciones.pdf_asignacion', $a) }}">
                                                PDF generado
                                            </a>
                                            @endrutaDisponible

                                            @if($pdfAsignacionFirmado)
                                                @rutaDisponible('admin.vehiculos.asignaciones.ver_pdf_firmado')
                                                <a class="small-btn green-btn" target="_blank" rel="noopener noreferrer"
                                                   href="{{ route('admin.vehiculos.asignaciones.ver_pdf_firmado', $pdfAsignacionFirmado) }}">
                                                    Ver firmado
                                                </a>
                                                @endrutaDisponible
                                            @endif
                                        </div>

                                        @rutaDisponible('admin.vehiculos.asignaciones.subir_pdf_firmado')
                                        <form method="POST"
                                              action="{{ route('admin.vehiculos.asignaciones.subir_pdf_firmado', $a) }}"
                                              enctype="multipart/form-data"
                                              class="upload-form">
                                            @csrf

                                            <input type="hidden" name="tipo_documento" value="asignacion_firmada">
                                            <input type="file" name="archivo" accept="application/pdf" required>

                                            <button class="upload-btn" type="submit">
                                                Subir firmado
                                            </button>
                                        </form>
                                        @endrutaDisponible

                                        <div class="muted-line">
                                            @if($pdfAsignacionFirmado)
                                                Archivo actual: {{ $pdfAsignacionFirmado->nombre_original ?? 'PDF firmado' }}
                                            @else
                                                Aún no se ha subido PDF firmado de asignación.
                                            @endif
                                        </div>
                                    </div>

                                    <div class="document-box">
                                        <div class="document-title">Devolución / desasignación</div>

                                        @if(!$a->activa)
                                            <div class="action-row">
                                                @rutaDisponible('admin.vehiculos.asignaciones.pdf_desasignacion')
                                                <a class="small-btn" target="_blank" rel="noopener noreferrer"
                                                   href="{{ route('admin.vehiculos.asignaciones.pdf_desasignacion', $a) }}">
                                                    PDF generado
                                                </a>
                                                @endrutaDisponible

                                                @if($pdfDevolucionFirmado)
                                                    @rutaDisponible('admin.vehiculos.asignaciones.ver_pdf_firmado')
                                                    <a class="small-btn green-btn" target="_blank" rel="noopener noreferrer"
                                                       href="{{ route('admin.vehiculos.asignaciones.ver_pdf_firmado', $pdfDevolucionFirmado) }}">
                                                        Ver firmado
                                                    </a>
                                                    @endrutaDisponible
                                                @endif
                                            </div>

                                            @rutaDisponible('admin.vehiculos.asignaciones.subir_pdf_firmado')
                                            <form method="POST"
                                                  action="{{ route('admin.vehiculos.asignaciones.subir_pdf_firmado', $a) }}"
                                                  enctype="multipart/form-data"
                                                  class="upload-form">
                                                @csrf

                                                <input type="hidden" name="tipo_documento" value="devolucion_firmada">
                                                <input type="file" name="archivo" accept="application/pdf" required>

                                                <button class="upload-btn" type="submit">
                                                    Subir firmado
                                                </button>
                                            </form>
                                            @endrutaDisponible

                                            <div class="muted-line">
                                                @if($pdfDevolucionFirmado)
                                                    Archivo actual: {{ $pdfDevolucionFirmado->nombre_original ?? 'PDF firmado' }}
                                                @else
                                                    Aún no se ha subido PDF firmado de devolución.
                                                @endif
                                            </div>
                                        @else
                                            <div class="muted-line">
                                                El PDF de devolución estará disponible cuando el vehículo sea desasignado.
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="panel panel-full">
                                <div class="panel-title">Desasignación del vehículo</div>

                                @if($a->activa)
                                    @rutaDisponible('admin.vehiculos.asignaciones.desasignar')
                                    <form method="POST"
                                          action="{{ route('admin.vehiculos.asignaciones.desasignar', $a) }}"
                                          class="close-form"
                                          onsubmit="return confirm('La desasignación no cerrará productos/refacciones activos del vehículo. ¿Deseas continuar?');">
                                        @csrf

                                        <input type="date" name="fecha_fin" required>
                                        <input type="text" name="estado_final_vehiculo" placeholder="Estado final" required>
                                        <input type="text" name="observaciones_desasignacion" placeholder="Observaciones">

                                        <button class="danger-btn" type="submit">Desasignar</button>
                                    </form>
                                    @endrutaDisponible
                                @else
                                    <div class="muted-line">
                                        Esta asignación ya fue cerrada. Puedes consultar el PDF generado de devolución o subir el PDF firmado.
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="empty-box">No hay asignaciones registradas.</div>
            @endforelse
        </div>

        <div class="mt-6">
            {{ $asignaciones->links() }}
        </div>
    </div>
</div>

<script>
@if(session('pdf_url'))
  window.addEventListener('load', function () {
    window.open(@json(session('pdf_url')), '_blank', 'noopener,noreferrer');
  });
@endif
function toggleDetalle(id) {
    const detalleSeleccionado = document.getElementById(id);

    if (!detalleSeleccionado) {
        return;
    }

    detalleSeleccionado.classList.toggle('open');
}

function filtrarAsignaciones() {
    const texto = document.getElementById('buscarAsignacion').value.toLowerCase().trim();
    const estado = document.getElementById('filtroEstado').value;
    const items = document.querySelectorAll('.asignacion-item');

    items.forEach(item => {
        const search = item.dataset.search || '';
        const itemEstado = item.dataset.estado || '';

        const coincideTexto = search.includes(texto);
        const coincideEstado = estado === 'todos' || estado === itemEstado;

        item.style.display = (coincideTexto && coincideEstado) ? '' : 'none';
    });
}
</script>
@endsection

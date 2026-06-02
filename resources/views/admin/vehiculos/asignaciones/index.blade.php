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
        color: #2563eb;
        text-transform: uppercase;
        letter-spacing: .08em;
        margin-bottom: 4px;
    }

    .veh-title {
        font-size: 28px;
        font-weight: 800;
        color: #111827;
        margin: 0;
    }

    .veh-subtitle {
        color: #64748b;
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
        background: #fff;
        color: #111827;
        border-color: #cbd5e1;
    }

    .veh-btn-light:hover {
        background: #f1f5f9;
    }

    .veh-btn-primary {
        background: #2563eb;
        color: #fff;
        border-color: #2563eb;
    }

    .veh-btn-primary:hover {
        background: #1d4ed8;
    }

    .veh-alert-success {
        border: 1px solid #bbf7d0;
        background: #f0fdf4;
        color: #166534;
        padding: 12px 14px;
        border-radius: 12px;
        margin-bottom: 14px;
        font-weight: 600;
    }

    .veh-alert-error {
        border: 1px solid #fecaca;
        background: #fef2f2;
        color: #991b1b;
        padding: 12px 14px;
        border-radius: 12px;
        margin-bottom: 14px;
        font-weight: 600;
    }

    .filters-card {
        display: grid;
        grid-template-columns: 1fr 220px;
        gap: 12px;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 16px;
        padding: 14px;
        margin-bottom: 14px;
        box-shadow: 0 8px 22px rgba(15, 23, 42, .04);
    }

    .filters-card input,
    .filters-card select {
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        padding: 10px 12px;
        min-height: 42px;
        width: 100%;
    }

    .compact-list {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 18px;
        box-shadow: 0 10px 30px rgba(15, 23, 42, .05);
        overflow: hidden;
    }

    .compact-row {
        border-bottom: 1px solid #f1f5f9;
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
        background: #f8fafc;
    }

    .info-main {
        font-size: 15px;
        font-weight: 800;
        color: #111827;
    }

    .info-sub {
        font-size: 12px;
        color: #64748b;
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
        background: #dcfce7;
        color: #166534;
    }

    .badge-closed {
        background: #e5e7eb;
        color: #374151;
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
        border: 1px solid #cbd5e1;
        background: #fff;
        color: #111827;
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
        background: #f1f5f9;
    }

    .blue-btn {
        border-color: #bfdbfe;
        background: #eff6ff;
        color: #1d4ed8;
    }

    .blue-btn:hover {
        background: #dbeafe;
    }

    .green-btn {
        border-color: #bbf7d0;
        background: #f0fdf4;
        color: #166534;
    }

    .green-btn:hover {
        background: #dcfce7;
    }

    .danger-outline-btn {
        border-color: #fecaca;
        background: #fff;
        color: #dc2626;
    }

    .danger-outline-btn:hover {
        background: #fef2f2;
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
        background: #f8fafc;
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        padding: 14px;
    }

    .panel {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        padding: 14px;
    }

    .panel-full {
        grid-column: 1 / -1;
    }

    .panel-title {
        font-size: 14px;
        font-weight: 800;
        color: #111827;
        margin-bottom: 10px;
    }

    .panel-description {
        font-size: 12px;
        color: #64748b;
        margin-bottom: 12px;
    }

    .document-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }

    .document-box {
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 12px;
        background: #fff;
    }

    .document-title {
        font-size: 13px;
        font-weight: 800;
        color: #111827;
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
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        padding: 8px;
        background: #fff;
        width: 100%;
        font-size: 13px;
    }

    .upload-btn {
        border: 1px solid #cbd5e1;
        background: #fff;
        color: #111827;
        border-radius: 10px;
        padding: 9px 11px;
        font-weight: 800;
        cursor: pointer;
        white-space: nowrap;
        font-size: 13px;
    }

    .upload-btn:hover {
        background: #f1f5f9;
    }

    .close-form {
        display: grid;
        grid-template-columns: 150px 1fr 1fr auto;
        gap: 10px;
        align-items: center;
    }

    .close-form input {
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        padding: 9px 11px;
        min-height: 40px;
        width: 100%;
        background: #fff;
    }

    .danger-btn {
        border: 1px solid #ef4444;
        background: #fff;
        color: #dc2626;
        border-radius: 10px;
        padding: 9px 12px;
        font-weight: 800;
        cursor: pointer;
        white-space: nowrap;
    }

    .danger-btn:hover {
        background: #fef2f2;
    }

    .empty-box {
        text-align: center;
        color: #64748b;
        padding: 38px;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 18px;
    }

    .muted-line {
        font-size: 12px;
        color: #64748b;
        margin-top: 8px;
        line-height: 1.4;
    }

    .quick-info {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px;
    }

    .quick-info-item {
        background: #f8fafc;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 10px;
    }

    .quick-info-label {
        font-size: 11px;
        color: #64748b;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .04em;
        margin-bottom: 3px;
    }

    .quick-info-value {
        font-size: 13px;
        color: #111827;
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
    <div class="veh-page">
        <div class="veh-header">
            <div>
                <div class="veh-kicker">Flota</div>
                <h1 class="veh-title">Asignaciones de vehículos</h1>
                <p class="veh-subtitle">
                    Busca por vehículo, placa, VIN o colaborador y gestiona documentos firmados.
                </p>
            </div>

            <div class="veh-top-actions">
                <a href="{{ route('dashboard') }}" class="veh-btn veh-btn-light">← Volver al menú</a>
                <a href="{{ route('admin.vehiculos.asignaciones.create') }}" class="veh-btn veh-btn-primary">+ Nueva asignación</a>
            </div>
        </div>

        @if(session('success'))
            <div class="veh-alert-success">{{ session('success') }}</div>
        @endif

        @if(session('error'))
            <div class="veh-alert-error">{{ session('error') }}</div>
        @endif

        @if($errors->any())
            <div class="veh-alert-error">
                {{ implode(' | ', $errors->all()) }}
            </div>
        @endif

        <div class="filters-card">
            <input
                type="text"
                id="buscarAsignacion"
                placeholder="Buscar por vehículo, placa, VIN o colaborador..."
                onkeyup="filtrarAsignaciones()"
            >

            <select id="filtroEstado" onchange="filtrarAsignaciones()">
                <option value="todos">Todos los estados</option>
                <option value="activa">Activas</option>
                <option value="cerrada">Cerradas</option>
            </select>
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
                            <a class="small-btn blue-btn"
                               href="{{ route('admin.vehiculos.productos.index', ['vehiculo_vin' => $a->vehiculo_vin]) }}">
                                Productos
                            </a>

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
                                            <a class="small-btn" target="_blank"
                                               href="{{ route('admin.vehiculos.asignaciones.pdf_asignacion', $a) }}">
                                                PDF generado
                                            </a>

                                            @if($pdfAsignacionFirmado)
                                                <a class="small-btn green-btn" target="_blank"
                                                   href="{{ route('admin.vehiculos.asignaciones.ver_pdf_firmado', $pdfAsignacionFirmado) }}">
                                                    Ver firmado
                                                </a>
                                            @endif
                                        </div>

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
                                                <a class="small-btn" target="_blank"
                                                   href="{{ route('admin.vehiculos.asignaciones.pdf_desasignacion', $a) }}">
                                                    PDF generado
                                                </a>

                                                @if($pdfDevolucionFirmado)
                                                    <a class="small-btn green-btn" target="_blank"
                                                       href="{{ route('admin.vehiculos.asignaciones.ver_pdf_firmado', $pdfDevolucionFirmado) }}">
                                                        Ver firmado
                                                    </a>
                                                @endif
                                            </div>

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
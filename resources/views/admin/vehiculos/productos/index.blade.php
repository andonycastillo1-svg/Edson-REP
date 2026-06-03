@extends('layouts.admin')

@section('title', 'Productos del vehículo')

@section('content')
<style>
    .vp-wrap {
        max-width: 1180px;
        margin: 0 auto;
    }

    .vp-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
        margin-bottom: 22px;
    }

    .vp-kicker {
        font-size: 13px;
        font-weight: 800;
        color: #2563eb;
        text-transform: uppercase;
        letter-spacing: .08em;
        margin-bottom: 4px;
    }

    .vp-title {
        font-size: 28px;
        font-weight: 800;
        color: #111827;
        margin: 0;
    }

    .vp-subtitle {
        color: #64748b;
        font-size: 14px;
        margin-top: 5px;
    }

    .vp-top-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        align-items: center;
    }

    .vp-btn {
        border-radius: 10px;
        padding: 10px 14px;
        font-size: 14px;
        font-weight: 800;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid transparent;
        cursor: pointer;
        white-space: nowrap;
        min-height: 42px;
    }

    .vp-btn-light {
        background: #fff;
        color: #111827;
        border-color: #cbd5e1;
    }

    .vp-btn-light:hover {
        background: #f1f5f9;
    }

    .vp-btn-primary {
        background: #2563eb;
        color: #fff;
        border-color: #2563eb;
    }

    .vp-btn-primary:hover {
        background: #1d4ed8;
    }

    .vp-btn-danger {
        background: #fff;
        color: #dc2626;
        border-color: #fecaca;
    }

    .vp-btn-danger:hover {
        background: #fef2f2;
    }

    .vp-alert-success {
        border: 1px solid #bbf7d0;
        background: #f0fdf4;
        color: #166534;
        padding: 12px 14px;
        border-radius: 12px;
        margin-bottom: 14px;
        font-weight: 700;
    }

    .vp-alert-error {
        border: 1px solid #fecaca;
        background: #fef2f2;
        color: #991b1b;
        padding: 12px 14px;
        border-radius: 12px;
        margin-bottom: 14px;
        font-weight: 700;
    }

    .vp-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 18px;
        padding: 18px;
        margin-bottom: 18px;
        box-shadow: 0 10px 30px rgba(15, 23, 42, .05);
    }

    .vp-card-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 14px;
        margin-bottom: 14px;
    }

    .vp-card-title {
        font-size: 18px;
        font-weight: 800;
        color: #111827;
        margin: 0;
    }

    .vp-card-subtitle {
        color: #64748b;
        font-size: 13px;
        margin-top: 4px;
    }

    .vp-note {
        border: 1px solid #fde68a;
        background: #fffbeb;
        color: #92400e;
        padding: 10px 12px;
        border-radius: 12px;
        font-size: 13px;
        margin-bottom: 14px;
        line-height: 1.45;
    }

    .vp-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 14px;
    }

    .vp-field {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .vp-field-full {
        grid-column: 1 / -1;
    }

    .vp-field label {
        font-weight: 800;
        font-size: 13px;
        color: #111827;
    }

    .vp-field input,
    .vp-field select,
    .vp-field textarea,
    .vp-filter-form select {
        width: 100%;
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        padding: 10px 12px;
        min-height: 42px;
        background: #fff;
        color: #111827;
        font-size: 14px;
    }

    .vp-field textarea {
        min-height: 76px;
        resize: vertical;
    }

    .vp-form-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 16px;
    }

    .vp-filter-row {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        gap: 14px;
        margin-bottom: 14px;
    }

    .vp-filter-form {
        display: grid;
        grid-template-columns: 300px auto;
        gap: 10px;
        align-items: center;
    }

    .vp-list {
        display: grid;
        gap: 12px;
    }

    .vp-item {
        border: 1px solid #e5e7eb;
        border-radius: 16px;
        background: #fff;
        overflow: hidden;
    }

    .vp-item-summary {
        display: grid;
        grid-template-columns: 1.25fr 1fr .55fr .65fr auto;
        gap: 14px;
        align-items: center;
        padding: 14px;
        background: #fff;
    }

    .vp-item-summary:hover {
        background: #f8fafc;
    }

    .vp-main-text {
        font-size: 14px;
        font-weight: 800;
        color: #111827;
    }

    .vp-muted {
        color: #64748b;
        font-size: 12px;
        margin-top: 3px;
        line-height: 1.4;
    }

    .vp-badge {
        display: inline-flex;
        border-radius: 999px;
        padding: 5px 10px;
        font-weight: 800;
        font-size: 12px;
        white-space: nowrap;
    }

    .vp-badge-activo {
        background: #dcfce7;
        color: #166534;
    }

    .vp-badge-cerrado {
        background: #e2e8f0;
        color: #334155;
    }

    .vp-item-actions {
        display: flex;
        justify-content: flex-end;
        gap: 8px;
        flex-wrap: wrap;
    }

    .vp-small-btn {
        border: 1px solid #cbd5e1;
        background: #fff;
        color: #111827;
        border-radius: 10px;
        padding: 8px 11px;
        font-weight: 800;
        font-size: 13px;
        text-decoration: none;
        cursor: pointer;
        white-space: nowrap;
        min-height: 38px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .vp-small-btn:hover {
        background: #f1f5f9;
    }

    .vp-small-btn-danger {
        border-color: #fecaca;
        color: #dc2626;
    }

    .vp-small-btn-danger:hover {
        background: #fef2f2;
    }

    .vp-details {
        display: none;
        border-top: 1px solid #f1f5f9;
        padding: 14px;
        background: #f8fafc;
    }

    .vp-details.open {
        display: block;
    }

    .vp-detail-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
        margin-bottom: 12px;
    }

    .vp-detail-box {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 11px;
    }

    .vp-detail-label {
        font-size: 11px;
        color: #64748b;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .04em;
        margin-bottom: 4px;
    }

    .vp-detail-value {
        font-size: 13px;
        color: #111827;
        font-weight: 800;
        line-height: 1.4;
    }

    .vp-close-panel {
        display: none;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        padding: 14px;
        margin-top: 12px;
    }

    .vp-close-panel.open {
        display: block;
    }

    .vp-close-title {
        font-size: 14px;
        font-weight: 800;
        color: #111827;
        margin-bottom: 4px;
    }

    .vp-close-subtitle {
        font-size: 12px;
        color: #64748b;
        margin-bottom: 12px;
    }

    .vp-close-form {
        display: grid;
        grid-template-columns: 140px 220px 1fr auto;
        gap: 10px;
        align-items: start;
    }

    .vp-close-form input,
    .vp-close-form select,
    .vp-close-form textarea {
        width: 100%;
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        padding: 10px 12px;
        min-height: 42px;
        background: #fff;
        color: #111827;
        font-size: 14px;
    }

    .vp-close-form textarea {
        min-height: 42px;
        resize: vertical;
    }

    .vp-check {
        display: flex;
        align-items: center;
        gap: 7px;
        font-size: 13px;
        color: #334155;
        margin-top: 10px;
    }

    .vp-check input {
        width: auto;
        min-height: auto;
    }

    .vp-warning {
        margin-top: 10px;
        border: 1px solid #fecaca;
        background: #fef2f2;
        color: #991b1b;
        border-radius: 10px;
        padding: 9px 11px;
        font-size: 12px;
        line-height: 1.4;
    }

    .vp-empty {
        text-align: center;
        color: #64748b;
        padding: 34px;
        background: #fff;
        border: 1px dashed #cbd5e1;
        border-radius: 16px;
    }

    @media (max-width: 1050px) {
        .vp-item-summary {
            grid-template-columns: 1fr 1fr;
        }

        .vp-detail-grid {
            grid-template-columns: 1fr 1fr;
        }

        .vp-close-form {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 800px) {
        .vp-header,
        .vp-card-header,
        .vp-filter-row {
            flex-direction: column;
        }

        .vp-grid,
        .vp-detail-grid,
        .vp-item-summary,
        .vp-filter-form {
            grid-template-columns: 1fr;
        }

        .vp-form-actions,
        .vp-item-actions {
            justify-content: flex-start;
        }
    }
</style>

<div class="ui-panel p-6 md:p-8">
    <div class="vp-wrap">
        <div class="vp-header">
            <div>
                <div class="vp-kicker">Flota</div>
                <h1 class="vp-title">Productos/refacciones del vehículo</h1>

                @if($vehiculo)
                    <p class="vp-subtitle">
                        Vehículo: {{ $vehiculo->marca ?? 'Sin marca' }} - {{ $vehiculo->placa ?? 'Sin placa' }} / VIN: {{ $vehiculo->vin }}
                    </p>
                @else
                    <p class="vp-subtitle">
                        Administra asignaciones de refacciones separadas de la asignación vehículo-colaborador.
                    </p>
                @endif
            </div>

            <div class="vp-top-actions">
                <a class="vp-btn vp-btn-light" href="{{ route('admin.vehiculos.asignaciones.index') }}">
                    ← Asignaciones de vehículos
                </a>

                <a class="vp-btn vp-btn-light" href="{{ route('admin.vehiculos.index') }}">
                    Vehículos
                </a>
            </div>
        </div>

        @if(session('success'))
            <div class="vp-alert-success">{{ session('success') }}</div>
        @endif

        @if(session('error'))
            <div class="vp-alert-error">{{ session('error') }}</div>
        @endif

        @if($errors->any())
            <div class="vp-alert-error">{{ implode(' | ', $errors->all()) }}</div>
        @endif

        <div class="vp-card">
            <div class="vp-card-header">
                <div>
                    <h2 class="vp-card-title">Asignar nuevo producto/refacción</h2>
                    <div class="vp-card-subtitle">
                        Selecciona el vehículo, producto, bodega, cantidad y motivo de uso.
                    </div>
                </div>
            </div>

            <div class="vp-note">
                Solo se listan productos de categoría <strong>Refacciones</strong> con stock disponible en bodegas autorizadas.
            </div>

            <form method="POST" action="{{ route('admin.vehiculos.productos.store') }}">
                @csrf

                <div class="vp-grid">
                    <div class="vp-field">
                        <label>Vehículo</label>
                        <select name="vehiculo_vin" required>
                            <option value="">Seleccione vehículo...</option>
                            @foreach($vehiculos as $v)
                                <option value="{{ $v->vin }}" @selected(old('vehiculo_vin', $vehiculoVin) == $v->vin)>
                                    {{ $v->marca ?? 'Sin marca' }} - {{ $v->placa ?? 'Sin placa' }} / VIN: {{ $v->vin }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="vp-field">
                        <label>Producto/refacción</label>
                        <select name="producto_codigo" id="producto_codigo" required>
                            <option value="">Seleccione producto...</option>
                            @foreach($inventarios->groupBy('producto_codigo') as $codigo => $items)
                                @php
                                    $producto = optional($items->first()->producto);
                                @endphp

                                <option value="{{ $codigo }}" @selected(old('producto_codigo') == $codigo)>
                                    {{ $producto->nombre ?? $codigo }} — Stock total: {{ $items->sum('cantidad') }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="vp-field">
                        <label>Bodega de origen</label>
                        <select name="bodega_id" id="bodega_id" required>
                            <option value="">Seleccione bodega...</option>
                            @foreach($inventarios as $inv)
                                <option
                                    value="{{ $inv->bodega_id }}"
                                    data-producto="{{ $inv->producto_codigo }}"
                                    @selected(old('bodega_id') == $inv->bodega_id)
                                >
                                    {{ optional($inv->bodega)->nombre ?? ('Bodega #' . $inv->bodega_id) }} — Stock: {{ $inv->cantidad }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="vp-field">
                        <label>Cantidad</label>
                        <input type="number" name="cantidad" min="1" value="{{ old('cantidad', 1) }}" required>
                    </div>

                    <div class="vp-field">
                        <label>Fecha</label>
                        <input type="date" name="fecha" value="{{ old('fecha', date('Y-m-d')) }}" required>
                    </div>

                    <div class="vp-field">
                        <label>Motivo</label>
                        <input
                            type="text"
                            name="motivo"
                            value="{{ old('motivo') }}"
                            placeholder="Instalación, reparación, mantenimiento..."
                            required
                        >
                    </div>

                    <div class="vp-field vp-field-full">
                        <label>Observaciones</label>
                        <textarea name="observaciones" placeholder="Observaciones de la asignación al vehículo">{{ old('observaciones') }}</textarea>
                    </div>
                </div>

                <div class="vp-form-actions">
                    <button class="vp-btn vp-btn-primary" type="submit">
                        Asignar producto/refacción
                    </button>
                </div>
            </form>
        </div>

        <div class="vp-card">
            <div class="vp-filter-row">
                <div>
                    <h2 class="vp-card-title">Productos activos e históricos</h2>
                    <div class="vp-card-subtitle">
                        Consulta productos instalados, consumidos, devueltos o cerrados por vehículo.
                    </div>
                </div>

                <form method="GET" action="{{ route('admin.vehiculos.productos.index') }}" class="vp-filter-form">
                    <select name="vehiculo_vin">
                        <option value="">Todos los vehículos</option>
                        @foreach($vehiculos as $v)
                            <option value="{{ $v->vin }}" @selected($vehiculoVin == $v->vin)>
                                {{ $v->marca ?? 'Sin marca' }} - {{ $v->placa ?? 'Sin placa' }} / {{ $v->vin }}
                            </option>
                        @endforeach
                    </select>

                    <button class="vp-btn vp-btn-light" type="submit">
                        Filtrar
                    </button>
                </form>
            </div>

            <div class="vp-list">
                @forelse($asignaciones as $a)
                    <div class="vp-item">
                        <div class="vp-item-summary">
                            <div>
                                <div class="vp-main-text">
                                    {{ optional($a->producto)->nombre ?? $a->producto_codigo }}
                                </div>
                                <div class="vp-muted">
                                    Código: {{ $a->producto_codigo }}
                                </div>
                            </div>

                            <div>
                                <div class="vp-main-text">
                                    {{ optional($a->vehiculo)->marca ?? 'Sin marca' }} - {{ optional($a->vehiculo)->placa ?? 'Sin placa' }}
                                </div>
                                <div class="vp-muted">
                                    VIN: {{ $a->vehiculo_vin }}
                                </div>
                            </div>

                            <div>
                                <div class="vp-main-text">
                                    Cantidad: {{ $a->cantidad }}
                                </div>
                                <div class="vp-muted">
                                    {{ optional($a->fecha)?->format('d/m/Y') ?? 'Sin fecha' }}
                                </div>
                            </div>

                            <div>
                                @if($a->activa)
                                    <span class="vp-badge vp-badge-activo">Activo</span>
                                @else
                                    <span class="vp-badge vp-badge-cerrado">{{ $a->estado ?? 'Cerrado' }}</span>
                                @endif

                                @if($a->mal_uso_colaborador)
                                    <div class="vp-muted">
                                        Mal uso registrado
                                    </div>
                                @endif
                            </div>

                            <div class="vp-item-actions">
                                <button type="button" class="vp-small-btn" onclick="toggleDetalleProducto('detalle-producto-{{ $a->id }}')">
                                    Ver detalle
                                </button>

                                <a class="vp-small-btn" href="{{ route('admin.vehiculos.productos.pdf_asignacion', $a) }}" target="_blank" rel="noopener">
                                    PDF asignación
                                </a>

                                @unless($a->activa)
                                    <a class="vp-small-btn" href="{{ route('admin.vehiculos.productos.pdf_devolucion', $a) }}" target="_blank" rel="noopener">
                                        PDF devolución
                                    </a>
                                @endunless

                                @if($a->activa)
                                    <button type="button" class="vp-small-btn vp-small-btn-danger" onclick="toggleDetalleProducto('cerrar-producto-{{ $a->id }}')">
                                        Retirar / cerrar
                                    </button>
                                @endif
                            </div>
                        </div>

                        <div id="detalle-producto-{{ $a->id }}" class="vp-details">
                            <div class="vp-detail-grid">
                                <div class="vp-detail-box">
                                    <div class="vp-detail-label">Bodega origen</div>
                                    <div class="vp-detail-value">
                                        {{ optional($a->bodega)->nombre ?? ('Bodega #' . $a->bodega_id) }}
                                    </div>
                                </div>

                                <div class="vp-detail-box">
                                    <div class="vp-detail-label">Motivo</div>
                                    <div class="vp-detail-value">
                                        {{ $a->motivo ?? '—' }}
                                    </div>
                                </div>

                                <div class="vp-detail-box">
                                    <div class="vp-detail-label">Usuario que asignó</div>
                                    <div class="vp-detail-value">
                                        {{ optional($a->asignadoPor)->name ?? '—' }}
                                    </div>
                                </div>

                                <div class="vp-detail-box">
                                    <div class="vp-detail-label">Observaciones</div>
                                    <div class="vp-detail-value">
                                        {{ $a->observaciones ?? 'Sin observaciones' }}
                                    </div>
                                </div>

                                <div class="vp-detail-box">
                                    <div class="vp-detail-label">Cierre</div>
                                    <div class="vp-detail-value">
                                        @if($a->activa)
                                            Producto/refacción activo.
                                        @else
                                            Cerrado el {{ optional($a->fecha_cierre)?->format('d/m/Y H:i') ?? '—' }}
                                        @endif
                                    </div>
                                </div>

                                <div class="vp-detail-box">
                                    <div class="vp-detail-label">Responsable por mal uso</div>
                                    <div class="vp-detail-value">
                                        @if($a->mal_uso_colaborador)
                                            {{ optional($a->colaboradorResponsable)->nombre ?? $a->colaborador_responsable_codigo ?? 'Sin responsable activo' }}
                                        @else
                                            No aplica
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        @if($a->activa)
                            <div id="cerrar-producto-{{ $a->id }}" class="vp-close-panel">
                                <div class="vp-close-title">Retirar producto/refacción del vehículo</div>
                                <div class="vp-close-subtitle">
                                    Puedes retirar una cantidad parcial. Si el vehículo tiene 4 llantas y retiras 1, quedarán 3 activas.
                                </div>

                                <form
                                    method="POST"
                                    action="{{ route('admin.vehiculos.productos.cerrar', $a) }}"
                                    onsubmit="return confirmarCierreProducto(this, {{ (int) $a->cantidad }});"
                                >
                                    @csrf

                                    <div class="vp-close-form">
                                        <div>
                                            <input
                                                type="number"
                                                name="cantidad_cierre"
                                                min="1"
                                                max="{{ (int) $a->cantidad }}"
                                                value="1"
                                                required
                                                placeholder="Cantidad"
                                            >

                                            <div class="vp-muted">
                                                Disponible: {{ (int) $a->cantidad }}
                                            </div>
                                        </div>

                                        <div>
                                            <select name="accion_cierre" required onchange="toggleMalUsoWarning(this)">
                                                <option value="">Acción de cierre...</option>
                                                <option value="regresar">Regresa a inventario</option>
                                                <option value="consumido">Consumido</option>
                                                <option value="danado">Dañado</option>
                                                <option value="baja">Baja</option>
                                            </select>

                                            <label class="vp-check">
                                                <input type="checkbox" name="mal_uso_colaborador" value="1">
                                                Dañado por mal uso del colaborador
                                            </label>
                                        </div>

                                        <textarea name="observaciones_cierre" placeholder="Observación de cierre"></textarea>

                                        <button class="vp-btn vp-btn-danger" type="submit">
                                            Confirmar retiro
                                        </button>
                                    </div>

                                    <div class="vp-warning">
                                        Si marcas <strong>mal uso del colaborador</strong>, el sistema generará el cobro al colaborador que tenga asignado este vehículo actualmente. El cobro se calculará proporcionalmente según la vida útil restante del producto/refacción.
                                    </div>
                                </form>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="vp-empty">
                        No hay productos/refacciones registrados para el filtro seleccionado.
                    </div>
                @endforelse
            </div>

            <div class="mt-4">
                {{ $asignaciones->links() }}
            </div>
        </div>
    </div>
</div>

<script>
const productoSelect = document.getElementById('producto_codigo');
const bodegaSelect = document.getElementById('bodega_id');
const allBodegas = bodegaSelect ? Array.from(bodegaSelect.options).map(option => option.cloneNode(true)) : [];

function filtrarBodegas() {
    if (!productoSelect || !bodegaSelect) {
        return;
    }

    const producto = productoSelect.value;

    bodegaSelect.innerHTML = '';

    allBodegas.forEach(option => {
        if (!option.dataset.producto || !producto || option.dataset.producto === producto) {
            bodegaSelect.appendChild(option.cloneNode(true));
        }
    });
}

function toggleDetalleProducto(id) {
    const elemento = document.getElementById(id);

    if (!elemento) {
        return;
    }

    elemento.classList.toggle('open');
}

function confirmarCierreProducto(form, cantidadDisponible) {
    const cantidadInput = form.querySelector('input[name="cantidad_cierre"]');
    const accionInput = form.querySelector('select[name="accion_cierre"]');
    const malUsoInput = form.querySelector('input[name="mal_uso_colaborador"]');

    const cantidad = parseInt(cantidadInput.value || '0', 10);
    const accion = accionInput.value;
    const malUso = malUsoInput.checked;

    if (!cantidad || cantidad < 1) {
        alert('Debes indicar una cantidad válida a retirar.');
        return false;
    }

    if (cantidad > cantidadDisponible) {
        alert('No puedes retirar más cantidad de la que está activa en el vehículo.');
        return false;
    }

    if (!accion) {
        alert('Debes seleccionar una acción de cierre.');
        return false;
    }

    if (malUso && accion !== 'danado') {
        alert('Solo puedes marcar mal uso del colaborador cuando la acción sea Dañado.');
        return false;
    }

    let mensaje = '¿Confirmas retirar ' + cantidad + ' unidad(es) de este producto/refacción?';

    if (cantidad < cantidadDisponible) {
        mensaje += '\n\nQuedarán ' + (cantidadDisponible - cantidad) + ' unidad(es) activas en el vehículo.';
    } else {
        mensaje += '\n\nLa asignación completa quedará cerrada.';
    }

    if (malUso) {
        mensaje += '\n\nSe generará cobro al colaborador que tiene asignado el vehículo actualmente.';
    }

    return confirm(mensaje);
}

function toggleMalUsoWarning(select) {
    const form = select.closest('form');
    const check = form ? form.querySelector('input[name="mal_uso_colaborador"]') : null;

    if (!check) {
        return;
    }

    if (select.value !== 'danado') {
        check.checked = false;
    }
}

if (productoSelect && bodegaSelect) {
    productoSelect.addEventListener('change', filtrarBodegas);
    filtrarBodegas();
}
</script>
@endsection
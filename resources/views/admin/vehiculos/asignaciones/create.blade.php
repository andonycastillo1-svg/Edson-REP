@extends('layouts.admin')

@section('title', 'Nueva asignación vehículo')

@section('content')
<style>
    .veh-form-wrap {
        max-width: 1050px;
        margin: 0 auto;
    }

    .veh-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        margin-bottom: 20px;
    }

    .veh-title {
        font-size: 24px;
        font-weight: 700;
        color: #111827;
        margin: 0;
    }

    .veh-card {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        padding: 22px;
        margin-bottom: 18px;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.04);
    }

    .veh-card h3 {
        font-size: 18px;
        font-weight: 700;
        margin-bottom: 14px;
        color: #111827;
    }

    .veh-grid-2 {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
    }

    .veh-grid-3 {
        display: grid;
        grid-template-columns: 2fr 1.4fr 0.8fr;
        gap: 16px;
    }

    .veh-field {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .veh-field label {
        font-weight: 600;
        color: #111827;
        font-size: 14px;
    }

    .veh-field input,
    .veh-field select,
    .veh-field textarea {
        width: 100%;
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        padding: 10px 12px;
        min-height: 42px;
        background: #ffffff;
        color: #111827;
        outline: none;
    }

    .veh-field input:focus,
    .veh-field select:focus,
    .veh-field textarea:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
    }

    .veh-field textarea {
        min-height: 90px;
        resize: vertical;
    }

    .veh-help {
        font-size: 12px;
        color: #6b7280;
    }

    .veh-item {
        border: 1px solid #dbe4ef;
        border-radius: 14px;
        padding: 18px;
        margin-top: 14px;
        background: #f8fafc;
    }

    .veh-actions {
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
        margin-top: 18px;
    }

    .veh-btn-secondary {
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        padding: 10px 14px;
        background: #ffffff;
        cursor: pointer;
        color: #111827;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .veh-btn-secondary:hover {
        background: #f1f5f9;
    }

    .veh-btn-danger {
        border: 1px solid #ef4444;
        color: #dc2626;
        border-radius: 10px;
        padding: 9px 12px;
        background: #ffffff;
        cursor: pointer;
        font-weight: 600;
    }

    .veh-btn-danger:hover {
        background: #fef2f2;
    }

    .veh-error {
        border: 1px solid #fecaca;
        background: #fef2f2;
        color: #991b1b;
        padding: 12px 14px;
        border-radius: 10px;
        margin-bottom: 16px;
    }

    .veh-info-note {
        border: 1px solid #bfdbfe;
        background: #eff6ff;
        color: #1e40af;
        padding: 12px 14px;
        border-radius: 10px;
        margin-bottom: 14px;
        font-size: 14px;
    }

    @media (max-width: 768px) {
        .veh-header {
            flex-direction: column;
            align-items: flex-start;
        }

        .veh-grid-2,
        .veh-grid-3 {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="ui-panel p-6">
    <div class="veh-form-wrap">
        <div class="veh-header">
            <h1 class="veh-title">Nueva asignación de vehículo</h1>

            <a href="{{ route('admin.vehiculos.asignaciones.index') }}" class="veh-btn-secondary">
                ← Volver
            </a>
        </div>

        @if(session('error'))
            <div class="veh-error">
                {{ session('error') }}
            </div>
        @endif

        @if($errors->any())
            <div class="veh-error">
                {{ implode(' | ', $errors->all()) }}
            </div>
        @endif

        <form method="POST" action="{{ route('admin.vehiculos.asignaciones.store') }}">
            @csrf

            <div class="veh-card">
                <h3>Datos de asignación</h3>

                <div class="veh-grid-2">
                    <div class="veh-field">
                        <label>Vehículo</label>

                        <select name="vehiculo_vin" id="vehiculo_vin" required>
                            <option value="">Seleccione vehículo...</option>
                            @foreach($vehiculos as $v)
                                <option value="{{ $v->vin }}" @selected(old('vehiculo_vin') == $v->vin)>
                                    {{ $v->marca ?? 'Sin marca' }} - {{ $v->placa ?? 'Sin placa' }} / VIN: {{ $v->vin }}
                                </option>
                            @endforeach
                        </select>

                        <span class="veh-help">Formato: Marca - Placa / VIN</span>
                    </div>

                    <div class="veh-field">
                        <label>Colaborador</label>

                        <select name="colaborador_codigo" id="colaborador_codigo" required>
                            <option value="">Seleccione colaborador...</option>
                            @foreach($colaboradores as $c)
                                <option value="{{ $c->codigo }}" @selected(old('colaborador_codigo') == $c->codigo)>
                                    {{ $c->nombre }} - {{ $c->codigo }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="veh-field">
                        <label>Fecha de asignación</label>
                        <input
                            type="date"
                            name="fecha_inicio"
                            value="{{ old('fecha_inicio', date('Y-m-d')) }}"
                            required
                        >
                    </div>

                    <div class="veh-field">
                        <label>Estado inicial del vehículo</label>
                        <input
                            type="text"
                            name="estado_inicial_vehiculo"
                            value="{{ old('estado_inicial_vehiculo') }}"
                            placeholder="Ejemplo: Bueno, regular, con rayones..."
                            required
                        >
                    </div>
                </div>

                <div class="veh-field" style="margin-top: 16px;">
                    <label>Observaciones</label>
                    <textarea
                        name="observaciones_asignacion"
                        placeholder="Observaciones generales de la asignación"
                    >{{ old('observaciones_asignacion') }}</textarea>
                </div>
            </div>

            <div class="veh-card">
                <h3>Productos / refacciones</h3>

                <div class="veh-info-note">
                    Solo se mostrarán productos cuya categoría/tipo sea <strong>refacciones</strong> y tengan stock disponible.
                </div>

                <div id="items"></div>

                <div class="veh-actions">
                    <button type="button" class="veh-btn-secondary" onclick="addItem()">
                        + Agregar producto
                    </button>

                    <button class="ui-btn-primary" type="submit">
                        Guardar asignación
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

@php
    $inventariosJson = $inventarios->map(function ($i) {
        $productoNombre = optional($i->producto)->nombre
            ?? optional($i->producto)->descripcion
            ?? $i->producto_codigo;

        $bodegaNombre = optional($i->bodega)->nombre ?? ('Bodega #' . $i->bodega_id);

        return [
            'producto_codigo' => $i->producto_codigo,
            'producto_nombre' => $productoNombre,
            'bodega_id' => $i->bodega_id,
            'bodega_nombre' => $bodegaNombre,
            'cantidad' => (int) ($i->cantidad ?? 0),
        ];
    })->values();

    $bodegasJson = $bodegas->map(function ($b) {
        return [
            'id' => $b->id,
            'nombre' => $b->nombre ?? ('Bodega #' . $b->id),
        ];
    })->values();
@endphp

<script>
const inv = @json($inventariosJson);
const bodegas = @json($bodegasJson);

function productosUnicos() {
    const mapa = {};

    inv.forEach(item => {
        if (!mapa[item.producto_codigo]) {
            mapa[item.producto_codigo] = {
                producto_codigo: item.producto_codigo,
                producto_nombre: item.producto_nombre,
                cantidad_total: 0
            };
        }

        mapa[item.producto_codigo].cantidad_total += Number(item.cantidad || 0);
    });

    return Object.values(mapa);
}

function addItem() {
    const idx = document.querySelectorAll('.veh-product-item').length;
    const contenedor = document.createElement('div');
    contenedor.className = 'veh-product-item';

    const productos = productosUnicos();

    contenedor.innerHTML = `
        <div class="veh-item">
            <div class="veh-grid-3">
                <div class="veh-field">
                    <label>Producto</label>

                    <select
                        name="productos[${idx}][producto_codigo]"
                        id="producto_${idx}"
                        required
                        onchange="cargarBodegasPorProducto(this, ${idx})"
                    >
                        <option value="">Seleccione producto...</option>
                        ${productos.map(item => `
                            <option value="${item.producto_codigo}">
                                ${item.producto_nombre} - Stock total: ${item.cantidad_total}
                            </option>
                        `).join('')}
                    </select>

                    <span class="veh-help">Solo aparecen productos tipo/categoría refacciones.</span>
                </div>

                <div class="veh-field">
                    <label>Bodega</label>

                    <select
                        name="productos[${idx}][bodega_id]"
                        id="bodega_${idx}"
                        required
                        onchange="mostrarStock(${idx})"
                    >
                        <option value="">Seleccione una bodega...</option>
                        ${bodegas.map(bodega => `
                            <option value="${bodega.id}">
                                ${bodega.nombre}
                            </option>
                        `).join('')}
                    </select>

                    <span class="veh-help" id="stock_${idx}">
                        Seleccione un producto para ver stock por bodega.
                    </span>
                </div>

                <div class="veh-field">
                    <label>Cantidad</label>
                    <input
                        type="number"
                        min="1"
                        name="productos[${idx}][cantidad]"
                        value="1"
                        required
                    >
                </div>
            </div>

            <input type="hidden" name="productos[${idx}][tipo_control]" value="cantidad">

            <div class="veh-field" style="margin-top: 14px;">
                <label>Observaciones del producto</label>
                <input
                    name="productos[${idx}][observaciones]"
                    placeholder="Observaciones del producto"
                >
            </div>

            <div class="veh-actions">
                <button type="button" class="veh-btn-danger" onclick="this.closest('.veh-product-item').remove()">
                    Quitar producto
                </button>
            </div>
        </div>
    `;

    document.getElementById('items').appendChild(contenedor);
}

function cargarBodegasPorProducto(select, idx) {
    const productoCodigo = select.value;
    const bodegaSelect = document.getElementById(`bodega_${idx}`);
    const stockText = document.getElementById(`stock_${idx}`);

    bodegaSelect.innerHTML = '<option value="">Seleccione una bodega...</option>';
    stockText.textContent = 'Seleccione una bodega para ver stock disponible.';

    if (!productoCodigo) {
        bodegas.forEach(bodega => {
            const option = document.createElement('option');
            option.value = bodega.id;
            option.textContent = bodega.nombre;
            bodegaSelect.appendChild(option);
        });

        return;
    }

    const disponibles = inv.filter(item => item.producto_codigo === productoCodigo);

    disponibles.forEach(item => {
        const option = document.createElement('option');
        option.value = item.bodega_id;
        option.textContent = `${item.bodega_nombre} - Stock: ${item.cantidad}`;
        option.dataset.stock = item.cantidad;
        bodegaSelect.appendChild(option);
    });

    if (disponibles.length === 1) {
        bodegaSelect.value = disponibles[0].bodega_id;
        stockText.textContent = `Stock disponible: ${disponibles[0].cantidad}`;
    }
}

function mostrarStock(idx) {
    const bodegaSelect = document.getElementById(`bodega_${idx}`);
    const stockText = document.getElementById(`stock_${idx}`);
    const selected = bodegaSelect.options[bodegaSelect.selectedIndex];

    if (!selected || !selected.dataset.stock) {
        stockText.textContent = 'Seleccione un producto para ver stock por bodega.';
        return;
    }

    stockText.textContent = `Stock disponible: ${selected.dataset.stock}`;
}
</script>
@endsection
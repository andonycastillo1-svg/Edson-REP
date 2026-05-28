@extends('layouts.admin')

@section('title', 'Productos del vehículo')

@section('content')
<style>
    .vp-wrap { max-width: 1180px; margin: 0 auto; }
    .vp-header { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; margin-bottom:20px; }
    .vp-title { font-size:24px; font-weight:700; color:#111827; margin:0; }
    .vp-card { background:#fff; border:1px solid #e5e7eb; border-radius:14px; padding:20px; margin-bottom:18px; box-shadow:0 8px 24px rgba(15,23,42,.04); }
    .vp-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:14px; }
    .vp-field { display:flex; flex-direction:column; gap:6px; }
    .vp-field label { font-weight:600; font-size:13px; color:#111827; }
    .vp-field input, .vp-field select, .vp-field textarea { width:100%; border:1px solid #cbd5e1; border-radius:10px; padding:9px 11px; min-height:40px; background:#fff; color:#111827; }
    .vp-field textarea { min-height:76px; resize:vertical; }
    .vp-actions { display:flex; gap:8px; flex-wrap:wrap; align-items:center; margin-top:14px; }
    .vp-btn-light { border:1px solid #cbd5e1; border-radius:10px; padding:9px 12px; background:#fff; color:#111827; font-weight:600; text-decoration:none; display:inline-flex; }
    .vp-alert-success { border:1px solid #bbf7d0; background:#f0fdf4; color:#166534; padding:12px 14px; border-radius:10px; margin-bottom:14px; }
    .vp-alert-error { border:1px solid #fecaca; background:#fef2f2; color:#991b1b; padding:12px 14px; border-radius:10px; margin-bottom:14px; }
    .vp-note { border:1px solid #fde68a; background:#fffbeb; color:#92400e; padding:10px 12px; border-radius:10px; font-size:13px; margin-bottom:14px; }
    .vp-table-wrap { overflow-x:auto; }
    .vp-table { width:100%; border-collapse:collapse; font-size:13px; }
    .vp-table th { text-align:left; background:#f8fafc; color:#334155; padding:10px; border-bottom:1px solid #e5e7eb; }
    .vp-table td { padding:10px; border-bottom:1px solid #e5e7eb; vertical-align:top; color:#111827; }
    .vp-sub { color:#64748b; font-size:12px; margin-top:2px; }
    .vp-badge { display:inline-flex; border-radius:999px; padding:3px 9px; font-weight:700; font-size:12px; }
    .vp-badge-activo { background:#dcfce7; color:#166534; }
    .vp-badge-cerrado { background:#e2e8f0; color:#334155; }
    .vp-close { display:grid; grid-template-columns:1fr; gap:7px; min-width:230px; }
    .vp-check { display:flex; align-items:center; gap:7px; font-size:12px; color:#334155; }
    @media (max-width: 900px) { .vp-header { flex-direction:column; } .vp-grid { grid-template-columns:1fr; } }
</style>

<div class="ui-panel p-6">
    <div class="vp-wrap">
        <div class="vp-header">
            <div>
                <h1 class="vp-title">Productos/refacciones del vehículo</h1>
                @if($vehiculo)
                    <p class="text-sm text-slate-500 mt-1">
                        Vehículo: {{ $vehiculo->marca ?? 'Sin marca' }} - {{ $vehiculo->placa ?? 'Sin placa' }} / VIN: {{ $vehiculo->vin }}
                    </p>
                @else
                    <p class="text-sm text-slate-500 mt-1">Administra asignaciones de refacciones separadas de la asignación vehículo-colaborador.</p>
                @endif
            </div>
            <div class="vp-actions" style="margin-top:0;">
                <a class="vp-btn-light" href="{{ route('admin.vehiculos.asignaciones.index') }}">← Asignaciones de vehículos</a>
                <a class="vp-btn-light" href="{{ route('admin.vehiculos.index') }}">Vehículos</a>
            </div>
        </div>

        @if(session('success')) <div class="vp-alert-success">{{ session('success') }}</div> @endif
        @if(session('error')) <div class="vp-alert-error">{{ session('error') }}</div> @endif
        @if($errors->any()) <div class="vp-alert-error">{{ implode(' | ', $errors->all()) }}</div> @endif

        <div class="vp-card">
            <h2 class="text-lg font-bold text-slate-900 mb-3">Asignar nuevo producto/refacción</h2>
            <div class="vp-note">Solo se listan productos con <strong>productos.tipo = Refacciones</strong> (sin importar mayúsculas/minúsculas) y stock disponible en bodegas autorizadas.</div>

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
                                @php $producto = optional($items->first()->producto); @endphp
                                <option value="{{ $codigo }}" @selected(old('producto_codigo') == $codigo)>
                                    {{ $producto->nombre ?? $codigo }} (stock total: {{ $items->sum('cantidad') }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="vp-field">
                        <label>Bodega de origen</label>
                        <select name="bodega_id" id="bodega_id" required>
                            <option value="">Seleccione bodega...</option>
                            @foreach($inventarios as $inv)
                                <option value="{{ $inv->bodega_id }}" data-producto="{{ $inv->producto_codigo }}" @selected(old('bodega_id') == $inv->bodega_id)>
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
                        <input type="text" name="motivo" value="{{ old('motivo') }}" placeholder="Instalación, reparación, mantenimiento..." required>
                    </div>
                </div>

                <div class="vp-field" style="margin-top:14px;">
                    <label>Observaciones</label>
                    <textarea name="observaciones" placeholder="Observaciones de la asignación al vehículo">{{ old('observaciones') }}</textarea>
                </div>

                <div class="vp-actions">
                    <button class="ui-btn-primary" type="submit">Asignar producto/refacción</button>
                </div>
            </form>
        </div>

        <div class="vp-card">
            <div class="vp-actions" style="justify-content:space-between; margin-top:0; margin-bottom:12px;">
                <h2 class="text-lg font-bold text-slate-900">Productos activos e históricos</h2>
                <form method="GET" action="{{ route('admin.vehiculos.productos.index') }}" class="vp-actions" style="margin:0;">
                    <select name="vehiculo_vin">
                        <option value="">Todos los vehículos</option>
                        @foreach($vehiculos as $v)
                            <option value="{{ $v->vin }}" @selected($vehiculoVin == $v->vin)>{{ $v->marca }} - {{ $v->placa }} / {{ $v->vin }}</option>
                        @endforeach
                    </select>
                    <button class="vp-btn-light" type="submit">Filtrar / Ver historial</button>
                </form>
            </div>

            <div class="vp-table-wrap">
                <table class="vp-table">
                    <thead>
                        <tr>
                            <th>Vehículo</th>
                            <th>Producto/refacción</th>
                            <th>Bodega origen</th>
                            <th>Cantidad</th>
                            <th>Fecha</th>
                            <th>Estado</th>
                            <th>Motivo / observaciones</th>
                            <th>Usuario que asignó</th>
                            <th>Acciones disponibles</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($asignaciones as $a)
                            <tr>
                                <td>
                                    {{ optional($a->vehiculo)->marca ?? 'Sin marca' }} - {{ optional($a->vehiculo)->placa ?? 'Sin placa' }}
                                    <div class="vp-sub">VIN: {{ $a->vehiculo_vin }}</div>
                                </td>
                                <td>{{ optional($a->producto)->nombre ?? $a->producto_codigo }}<div class="vp-sub">{{ $a->producto_codigo }}</div></td>
                                <td>{{ optional($a->bodega)->nombre ?? ('Bodega #' . $a->bodega_id) }}</td>
                                <td>{{ $a->cantidad }}</td>
                                <td>{{ optional($a->fecha)?->format('d/m/Y') ?? '—' }}</td>
                                <td>
                                    @if($a->activa)
                                        <span class="vp-badge vp-badge-activo">activo</span>
                                    @else
                                        <span class="vp-badge vp-badge-cerrado">{{ $a->estado }}</span>
                                    @endif
                                    @if($a->mal_uso_colaborador)
                                        <div class="vp-sub">Mal uso: {{ optional($a->colaboradorResponsable)->nombre ?? $a->colaborador_responsable_codigo ?? 'Sin responsable activo' }}</div>
                                    @endif
                                </td>
                                <td>
                                    {{ $a->motivo ?? '—' }}
                                    <div class="vp-sub">{{ $a->observaciones ?? 'Sin observaciones' }}</div>
                                </td>
                                <td>{{ optional($a->asignadoPor)->name ?? '—' }}</td>
                                <td>
                                    @if($a->activa)
                                        <form method="POST" action="{{ route('admin.vehiculos.productos.cerrar', $a) }}" class="vp-close" onsubmit="return confirm('¿Cerrar este producto/refacción del vehículo?');">
                                            @csrf
                                            <select name="accion_cierre" required>
                                                <option value="">Acción...</option>
                                                <option value="regresar">Regresa a inventario</option>
                                                <option value="consumido">Consumido</option>
                                                <option value="danado">Dañado</option>
                                                <option value="baja">Baja</option>
                                            </select>
                                            <label class="vp-check">
                                                <input type="checkbox" name="mal_uso_colaborador" value="1">
                                                Dañado por mal uso del colaborador
                                            </label>
                                            <textarea name="observaciones_cierre" placeholder="Observación de cierre"></textarea>
                                            <button class="vp-btn-light" type="submit">Cerrar</button>
                                        </form>
                                    @else
                                        Cerrado el {{ optional($a->fecha_cierre)?->format('d/m/Y H:i') ?? '—' }}
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="text-center text-slate-500 py-6">No hay productos/refacciones registrados para el filtro seleccionado.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $asignaciones->links() }}</div>
        </div>
    </div>
</div>

<script>
const productoSelect = document.getElementById('producto_codigo');
const bodegaSelect = document.getElementById('bodega_id');
const allBodegas = Array.from(bodegaSelect.options).map(option => option.cloneNode(true));

function filtrarBodegas() {
    const producto = productoSelect.value;
    bodegaSelect.innerHTML = '';
    allBodegas.forEach(option => {
        if (!option.dataset.producto || !producto || option.dataset.producto === producto) {
            bodegaSelect.appendChild(option.cloneNode(true));
        }
    });
}

productoSelect.addEventListener('change', filtrarBodegas);
filtrarBodegas();
</script>
@endsection

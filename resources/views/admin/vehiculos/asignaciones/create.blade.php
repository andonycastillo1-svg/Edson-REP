@extends('layouts.admin')

@section('title', 'Nueva asignación vehículo')

@section('content')
<style>
    .veh-form-wrap { max-width: 900px; margin: 0 auto; }
    .veh-header { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:20px; }
    .veh-title { font-size:24px; font-weight:700; color:#111827; margin:0; }
    .veh-card { background:#fff; border:1px solid #e5e7eb; border-radius:14px; padding:22px; box-shadow:0 8px 24px rgba(15,23,42,.04); }
    .veh-grid-2 { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; }
    .veh-field { display:flex; flex-direction:column; gap:6px; }
    .veh-field label { font-weight:600; color:#111827; font-size:14px; }
    .veh-field input, .veh-field select, .veh-field textarea { width:100%; border:1px solid #cbd5e1; border-radius:10px; padding:10px 12px; min-height:42px; background:#fff; color:#111827; outline:none; }
    .veh-field input:focus, .veh-field select:focus, .veh-field textarea:focus { border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.12); }
    .veh-field textarea { min-height:90px; resize:vertical; }
    .veh-help { font-size:12px; color:#6b7280; }
    .veh-actions { display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-top:18px; }
    .veh-btn-secondary { border:1px solid #cbd5e1; border-radius:10px; padding:10px 14px; background:#fff; cursor:pointer; color:#111827; font-weight:600; text-decoration:none; display:inline-flex; align-items:center; justify-content:center; }
    .veh-btn-secondary:hover { background:#f1f5f9; }
    .veh-error { border:1px solid #fecaca; background:#fef2f2; color:#991b1b; padding:12px 14px; border-radius:10px; margin-bottom:16px; }
    .veh-info-note { border:1px solid #bfdbfe; background:#eff6ff; color:#1e40af; padding:12px 14px; border-radius:10px; margin-bottom:16px; font-size:14px; }
    @media (max-width: 768px) { .veh-header { flex-direction:column; align-items:flex-start; } .veh-grid-2 { grid-template-columns:1fr; } }
</style>

<div class="ui-panel p-6">
    <x-internal-navigation :back-url="route('admin.vehiculos.asignaciones.index')" />
    <div class="veh-form-wrap">
        <div class="veh-header">
            <h1 class="veh-title">Nueva asignación de vehículo</h1>
        </div>

        @if(session('error'))
            <div class="veh-error">{{ session('error') }}</div>
        @endif

        @if($errors->any())
            <div class="veh-error">{{ implode(' | ', $errors->all()) }}</div>
        @endif

        <div class="veh-info-note">
            Este formulario registra únicamente la relación <strong>Vehículo → Colaborador</strong>. Los productos/refacciones se administran en el módulo separado de Productos del vehículo.
        </div>

        <form method="POST" action="{{ route('admin.vehiculos.asignaciones.store') }}">
            @csrf

            <div class="veh-card">
                <h3 class="text-lg font-bold text-slate-900 mb-4">Datos de asignación</h3>

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
                        <span class="veh-help">Una unidad solo puede tener una asignación activa.</span>
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
                        <span class="veh-help">Una persona solo puede tener un vehículo activo.</span>
                    </div>

                    <div class="veh-field">
                        <label>Fecha de asignación</label>
                        <input type="date" name="fecha_inicio" value="{{ old('fecha_inicio', date('Y-m-d')) }}" required>
                    </div>

                    <div class="veh-field">
                        <label>Estado inicial del vehículo</label>
                        <input type="text" name="estado_inicial_vehiculo" value="{{ old('estado_inicial_vehiculo') }}" placeholder="Ejemplo: Bueno, regular, con rayones..." required>
                    </div>
                </div>

                <div class="veh-field" style="margin-top:16px;">
                    <label>Observaciones</label>
                    <textarea name="observaciones_asignacion" placeholder="Observaciones generales de la asignación">{{ old('observaciones_asignacion') }}</textarea>
                </div>

                <div class="veh-actions">
                    <button class="ui-btn-primary" type="submit">Guardar asignación</button>
                    <a class="veh-btn-secondary" href="{{ route('admin.vehiculos.productos.index') }}">Ir a Productos del vehículo</a>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

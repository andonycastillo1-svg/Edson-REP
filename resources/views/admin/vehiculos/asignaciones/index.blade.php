@extends('layouts.admin')

@section('title', 'Asignaciones de vehículos')

@section('content')
<style>
    .veh-wrap {
        max-width: 1150px;
        margin: 0 auto;
    }

    .veh-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        margin-bottom: 18px;
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
        padding: 20px;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.04);
    }

    .veh-alert-success {
        border: 1px solid #bbf7d0;
        background: #f0fdf4;
        color: #166534;
        padding: 12px 14px;
        border-radius: 10px;
        margin-bottom: 14px;
    }

    .veh-alert-error {
        border: 1px solid #fecaca;
        background: #fef2f2;
        color: #991b1b;
        padding: 12px 14px;
        border-radius: 10px;
        margin-bottom: 14px;
    }

    .veh-table-wrap {
        overflow-x: auto;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
    }

    .veh-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
        background: #ffffff;
    }

    .veh-table thead {
        background: #f8fafc;
    }

    .veh-table th {
        text-align: left;
        padding: 12px;
        font-weight: 700;
        color: #334155;
        border-bottom: 1px solid #e5e7eb;
        white-space: nowrap;
    }

    .veh-table td {
        padding: 12px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: top;
    }

    .veh-table tr:hover {
        background: #f8fafc;
    }

    .veh-main {
        font-weight: 700;
        color: #111827;
    }

    .veh-sub {
        font-size: 12px;
        color: #64748b;
        margin-top: 2px;
    }

    .badge {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 4px 10px;
        font-size: 12px;
        font-weight: 700;
    }

    .badge-active {
        background: #dcfce7;
        color: #166534;
    }

    .badge-closed {
        background: #e5e7eb;
        color: #374151;
    }

    .veh-actions {
        display: flex;
        flex-direction: column;
        gap: 10px;
        min-width: 360px;
    }

    .veh-docs {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .btn-light {
        border: 1px solid #cbd5e1;
        background: #ffffff;
        color: #111827;
        border-radius: 10px;
        padding: 8px 11px;
        font-weight: 600;
        font-size: 13px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
    }

    .btn-light:hover {
        background: #f1f5f9;
    }

    .btn-danger {
        border: 1px solid #ef4444;
        background: #ffffff;
        color: #dc2626;
        border-radius: 10px;
        padding: 8px 11px;
        font-weight: 700;
        font-size: 13px;
        cursor: pointer;
    }

    .btn-danger:hover {
        background: #fef2f2;
    }

    .des-form {
        display: grid;
        grid-template-columns: 145px 1fr auto;
        gap: 8px;
        align-items: center;
    }

    .des-form input {
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        padding: 8px 10px;
        min-height: 38px;
        width: 100%;
    }

    .empty-box {
        text-align: center;
        color: #64748b;
        padding: 28px;
    }

    .top-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        align-items: center;
    }

    .btn-back {
        border: 1px solid #cbd5e1;
        background: #ffffff;
        color: #111827;
        border-radius: 10px;
        padding: 10px 14px;
        font-weight: 600;
        text-decoration: none;
    }

    .btn-back:hover {
        background: #f1f5f9;
    }

    @media (max-width: 900px) {
        .veh-header {
            flex-direction: column;
            align-items: flex-start;
        }

        .des-form {
            grid-template-columns: 1fr;
        }

        .veh-actions {
            min-width: 260px;
        }
    }
</style>

<div class="ui-panel p-6">
    <div class="veh-wrap">
        <div class="veh-header">
            <div>
                <h1 class="veh-title">Asignaciones de vehículos</h1>
                <p class="text-sm text-slate-500 mt-1">
                    Consulta asignaciones activas, cerradas y documentos imprimibles.
                </p>
            </div>

            <div class="top-actions">
                <a href="{{ route('dashboard') }}" class="btn-back">
                    ← Volver al menú
                </a>

                <a class="ui-btn-primary" href="{{ route('admin.vehiculos.asignaciones.create') }}">
                    + Nueva asignación
                </a>
            </div>
        </div>

        @if(session('success'))
            <div class="veh-alert-success">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="veh-alert-error">
                {{ session('error') }}
            </div>
        @endif

        <div class="veh-card">
            <div class="veh-table-wrap">
                <table class="veh-table">
                    <thead>
                        <tr>
                            <th>Vehículo</th>
                            <th>Colaborador</th>
                            <th>Fecha asignación</th>
                            <th>Fecha desasignación</th>
                            <th>Estado</th>
                            <th>Documentos / acciones</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($asignaciones as $a)
                            <tr>
                                <td>
                                    <div class="veh-main">
                                        {{ optional($a->vehiculo)->marca ?? 'Sin marca' }} - {{ optional($a->vehiculo)->placa ?? 'Sin placa' }}
                                    </div>
                                    <div class="veh-sub">VIN: {{ $a->vehiculo_vin }}</div>
                                </td>

                                <td>
                                    <div class="veh-main">{{ optional($a->colaborador)->nombre ?? 'Sin colaborador' }}</div>
                                    <div class="veh-sub">Código: {{ $a->colaborador_codigo }}</div>
                                </td>

                                <td>{{ optional($a->fecha_inicio)?->format('d/m/Y') ?? '—' }}</td>
                                <td>{{ optional($a->fecha_fin)?->format('d/m/Y') ?? '—' }}</td>

                                <td>
                                    @if($a->activa)
                                        <span class="badge badge-active">Activa</span>
                                    @else
                                        <span class="badge badge-closed">Cerrada</span>
                                    @endif
                                </td>

                                <td>
                                    <div class="veh-actions">
                                        <div class="veh-docs">
                                            <a class="btn-light" target="_blank" href="{{ route('admin.vehiculos.asignaciones.pdf_asignacion', $a) }}">PDF asignación</a>

                                            @if(!$a->activa)
                                                <a class="btn-light" target="_blank" href="{{ route('admin.vehiculos.asignaciones.pdf_desasignacion', $a) }}">PDF desasignación</a>
                                            @endif

                                            <a class="btn-light" href="{{ route('admin.vehiculos.productos.index', ['vehiculo_vin' => $a->vehiculo_vin]) }}">Productos/refacciones del vehículo</a>
                                        </div>

                                        @if($a->activa)
                                            <form method="POST"
                                                  action="{{ route('admin.vehiculos.asignaciones.desasignar', $a) }}"
                                                  class="des-form"
                                                  onsubmit="return confirm('La desasignación no cerrará productos/refacciones activos del vehículo. ¿Deseas continuar?');">
                                                @csrf

                                                <input type="date" name="fecha_fin" required title="Fecha de desasignación">
                                                <input type="text" name="estado_final_vehiculo" placeholder="Estado final del vehículo" required>
                                                <input type="text" name="observaciones_desasignacion" placeholder="Observaciones de desasignación">

                                                <button class="btn-danger" type="submit">Desasignar vehículo</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="empty-box">No hay asignaciones de vehículos registradas.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $asignaciones->links() }}
            </div>
        </div>
    </div>
</div>
@endsection

@extends('layouts.admin')

@section('title','Vehículos')

@section('content')

<style>
    .veh-wrap {
        max-width: 1150px;
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
        font-weight: 700;
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
        font-size: 14px;
        color: #64748b;
        margin-top: 4px;
    }

    .veh-actions-top {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        justify-content: flex-end;
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
        background: #ffffff;
        color: #111827;
        border-color: #cbd5e1;
    }

    .veh-btn-light:hover {
        background: #f1f5f9;
    }

    .veh-btn-primary {
        background: #2563eb;
        color: #ffffff;
        border-color: #2563eb;
    }

    .veh-btn-primary:hover {
        background: #1d4ed8;
    }

    .veh-btn-success {
        background: #16a34a;
        color: #ffffff;
        border-color: #16a34a;
    }

    .veh-btn-success:hover {
        background: #15803d;
    }

    .veh-card {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 18px;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05);
    }

    .veh-table-wrap {
        overflow-x: auto;
    }

    .veh-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
    }

    .veh-table thead {
        background: #f8fafc;
    }

    .veh-table th {
        padding: 14px;
        text-align: left;
        font-weight: 800;
        color: #334155;
        border-bottom: 1px solid #e5e7eb;
        white-space: nowrap;
    }

    .veh-table td {
        padding: 14px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }

    .veh-table tr:hover {
        background: #f8fafc;
    }

    .veh-main {
        font-weight: 800;
        color: #111827;
    }

    .veh-sub {
        font-size: 12px;
        color: #64748b;
        margin-top: 2px;
    }

    .veh-plate {
        display: inline-flex;
        align-items: center;
        border: 1px solid #cbd5e1;
        background: #f8fafc;
        color: #111827;
        border-radius: 10px;
        padding: 7px 11px;
        font-weight: 800;
        letter-spacing: .03em;
    }

    .veh-badge {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 5px 11px;
        font-size: 12px;
        font-weight: 800;
        white-space: nowrap;
    }

    .veh-badge-uso {
        background: #dbeafe;
        color: #1d4ed8;
    }

    .veh-badge-disponible {
        background: #dcfce7;
        color: #166534;
    }

    .veh-badge-mantenimiento {
        background: #fef3c7;
        color: #92400e;
    }

    .veh-badge-otro {
        background: #e5e7eb;
        color: #374151;
    }

    .veh-row-actions {
        display: flex;
        justify-content: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .veh-btn-edit {
        border: 1px solid #bfdbfe;
        background: #eff6ff;
        color: #1d4ed8;
        border-radius: 10px;
        padding: 8px 11px;
        font-size: 13px;
        font-weight: 700;
        text-decoration: none;
    }

    .veh-btn-edit:hover {
        background: #dbeafe;
    }

    .veh-btn-danger {
        border: 1px solid #fecaca;
        background: #fff;
        color: #dc2626;
        border-radius: 10px;
        padding: 8px 11px;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
    }

    .veh-btn-danger:hover {
        background: #fef2f2;
    }

    .veh-empty {
        text-align: center;
        padding: 36px;
        color: #64748b;
    }

    @media (max-width: 768px) {
        .veh-header {
            flex-direction: column;
        }

        .veh-actions-top {
            justify-content: flex-start;
        }

        .veh-title {
            font-size: 24px;
        }
    }
</style>

<div class="ui-panel w-full p-6 md:p-8">
    <x-internal-navigation :back-url="route('dashboard')" />
    <div class="veh-wrap">

        <div class="veh-header">
            <div>
                <div class="veh-kicker">Flota</div>
                <h1 class="veh-title">Vehículos</h1>
                <p class="veh-subtitle">
                    Control visual de vehículos disponibles, en uso o en mantenimiento.
                </p>
            </div>

            <div class="veh-actions-top">                <a href="{{ route('admin.vehiculos.asignaciones.index') }}" class="veh-btn veh-btn-primary">
                    Asignaciones
                </a>

                <a href="{{ route('admin.vehiculos.create') }}" class="veh-btn veh-btn-success">
                    + Nuevo vehículo
                </a>
            </div>
        </div>

        <div class="veh-card">
            <div class="veh-table-wrap">
                <table class="veh-table">
                    <thead>
                        <tr>
                            <th>Vehículo</th>
                            <th>Placa</th>
                            <th>VIN</th>
                            <th>Modelo</th>
                            <th>Estado</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($vehiculos as $vehiculo)
                            @php
                                $estado = strtolower(trim($vehiculo->estado ?? ''));

                                if ($estado === 'en uso') {
                                    $badgeClass = 'veh-badge-uso';
                                } elseif ($estado === 'disponible') {
                                    $badgeClass = 'veh-badge-disponible';
                                } elseif ($estado === 'mantenimiento') {
                                    $badgeClass = 'veh-badge-mantenimiento';
                                } else {
                                    $badgeClass = 'veh-badge-otro';
                                }
                            @endphp

                            <tr>
                                <td>
                                    <div class="veh-main">
                                        {{ $vehiculo->marca ?? 'Sin marca' }}
                                    </div>
                                    <div class="veh-sub">
                                        {{ $vehiculo->modelo ? 'Modelo: '.$vehiculo->modelo : 'Modelo no registrado' }}
                                    </div>
                                </td>

                                <td>
                                    <span class="veh-plate">
                                        {{ $vehiculo->placa ?? 'Sin placa' }}
                                    </span>
                                </td>

                                <td>
                                    <div class="veh-main">
                                        {{ $vehiculo->vin }}
                                    </div>
                                </td>

                                <td>
                                    {{ $vehiculo->modelo ?? '—' }}
                                </td>

                                <td>
                                    <span class="veh-badge {{ $badgeClass }}">
                                        {{ $vehiculo->estado ?? 'Sin estado' }}
                                    </span>
                                </td>

                                <td>
                                    <div class="veh-row-actions">
                                        <a href="{{ route('admin.vehiculos.edit', $vehiculo->vin) }}"
                                           class="veh-btn-edit">
                                            Editar
                                        </a>

                                        <form action="{{ route('admin.vehiculos.destroy', $vehiculo->vin) }}"
                                              method="POST"
                                              onsubmit="return confirm('¿Seguro que deseas eliminar este vehículo?');">
                                            @csrf
                                            @method('DELETE')

                                            <button class="veh-btn-danger" type="submit">
                                                Eliminar
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="veh-empty">
                                        No hay vehículos registrados.
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-6">
            {{ $vehiculos->links() }}
        </div>

    </div>
</div>

@endsection
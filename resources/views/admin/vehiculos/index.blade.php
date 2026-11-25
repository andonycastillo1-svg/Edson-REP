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
        font-size: 14px;
        color: var(--ui-muted);
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

    .veh-card {
        background: var(--ui-surface);
        border: 1px solid var(--ui-border);
        border-radius: 18px;
        overflow: hidden;
        box-shadow: var(--ui-shadow-sm);
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
        background: var(--ui-bg);
    }

    .veh-table th {
        padding: 14px;
        text-align: left;
        font-weight: 800;
        color: var(--ui-text);
        border-bottom: 1px solid var(--ui-border);
        white-space: nowrap;
    }

    .veh-table td {
        padding: 14px;
        border-bottom: 1px solid var(--ui-subtle);
        vertical-align: middle;
    }

    .veh-table tr:hover {
        background: var(--ui-bg);
    }

    .veh-main {
        font-weight: 800;
        color: var(--ui-text);
    }

    .veh-sub {
        font-size: 12px;
        color: var(--ui-muted);
        margin-top: 2px;
    }

    .veh-plate {
        display: inline-flex;
        align-items: center;
        border: 1px solid var(--ui-border);
        background: var(--ui-bg);
        color: var(--ui-text);
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
        background: var(--ui-tint);
        color: var(--ui-primary);
    }

    .veh-badge-disponible {
        background: var(--ui-success-bg);
        color: var(--ui-success-dark);
    }

    .veh-badge-mantenimiento {
        background: var(--ui-warning-bg);
        color: var(--ui-warning-dark);
    }

    .veh-badge-otro {
        background: var(--ui-border);
        color: var(--ui-text);
    }

    .veh-row-actions {
        display: flex;
        justify-content: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .veh-btn-edit {
        border: 1px solid var(--ui-info-border);
        background: var(--ui-info-bg);
        color: var(--ui-primary);
        border-radius: 10px;
        padding: 8px 11px;
        font-size: 13px;
        font-weight: 700;
        text-decoration: none;
    }

    .veh-btn-edit:hover {
        background: var(--ui-tint);
    }

    .veh-btn-danger {
        border: 1px solid var(--ui-danger-border);
        background: var(--ui-surface);
        color: var(--ui-danger);
        border-radius: 10px;
        padding: 8px 11px;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
    }

    .veh-btn-danger:hover {
        background: var(--ui-danger-bg);
    }

    .veh-empty {
        text-align: center;
        padding: 36px;
        color: var(--ui-muted);
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
    <x-sistema.enlace-datos modulo="vehiculos" />
    <x-sistema.consulta-campos :tabla="$tablaCampos" />

    <div class="veh-wrap">

        <div class="veh-header">
            <div>
                <div class="veh-kicker">Flota</div>
                <h1 class="ui-page-title veh-title">Vehículos</h1>
                <x-ui.exportar-excel modulo="vehiculos" />
                <p class="veh-subtitle">
                    Control visual de vehículos disponibles, en uso o en mantenimiento.
                </p>
            </div>

            <div class="veh-actions-top">
                @rutaDisponible('admin.vehiculos.asignaciones.index')
                <a href="{{ route('admin.vehiculos.asignaciones.index') }}" class="veh-btn veh-btn-primary">
                    Asignaciones
                </a>
                @endrutaDisponible

                @rutaDisponible('admin.vehiculos.create')
                <a href="{{ route('admin.vehiculos.create') }}" class="ui-btn-primary">
                    + Nuevo vehículo
                </a>
                @endrutaDisponible
            </div>
        </div>

        <div class="veh-card">
            <div class="veh-table-wrap">
                <table class="ui-table veh-table">
                    <thead>
                        <tr>
                            <th>Vehículo</th>
                            <th>Placa</th>
                            <th>VIN</th>
                            <th>Modelo</th>
                            <th>Estado</th>
                            <x-sistema.cabeceras-campos :tabla="$tablaCampos" />
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

                                <x-sistema.celdas-campos :tabla="$tablaCampos" :registro="$vehiculo->vin" />
                                <td>
                                    <div class="veh-row-actions">
                                        @rutaDisponible('admin.vehiculos.edit')
                                        <a href="{{ route('admin.vehiculos.edit', $vehiculo->vin) }}"
                                           class="veh-btn-edit">
                                            Editar
                                        </a>
                                        @endrutaDisponible

                                        @rutaDisponible('admin.vehiculos.destroy')
                                        <form action="{{ route('admin.vehiculos.destroy', $vehiculo->vin) }}"
                                              method="POST"
                                              onsubmit="return confirm('¿Seguro que deseas eliminar este vehículo?');">
                                            @csrf
                                            @method('DELETE')

                                            <button class="veh-btn-danger" type="submit">
                                                Eliminar
                                            </button>
                                        </form>
                                        @endrutaDisponible
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ 6 + $tablaCampos['campos']->count() }}">
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
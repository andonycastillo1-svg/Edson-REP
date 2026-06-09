@php
    $esDevolucion = $tipoDocumento === 'devolucion';
    $titulo = $esDevolucion ? 'Hoja de devolución de productos del vehículo' : 'Hoja de asignación de productos al vehículo';
    $vehiculo = $asignacion->vehiculo;
    $producto = $asignacion->producto;
    $responsable = optional($asignacion->asignacionVehiculo)->colaborador
        ?? $asignacion->colaboradorResponsable;
    $usuarioDocumento = $esDevolucion ? $asignacion->cerradoPor : $asignacion->asignadoPor;
    $fechaDocumento = $esDevolucion
        ? ($asignacion->fecha_cierre ?? $asignacion->updated_at)
        : ($asignacion->fecha ?? $asignacion->created_at);
@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $titulo }} #{{ $asignacion->id }}</title>
    <style>
        @page { margin: 28px; }
        body { font-family: DejaVu Sans, Arial, sans-serif; color: #111827; font-size: 12px; line-height: 1.45; }
        .top { display: flex; justify-content: space-between; gap: 18px; border-bottom: 3px solid #1d4ed8; padding-bottom: 14px; margin-bottom: 18px; }
        .title { font-size: 22px; font-weight: 800; color: #0f172a; margin: 0 0 6px; }
        .subtitle { color: #64748b; font-size: 12px; }
        .badge { display: inline-block; border-radius: 999px; padding: 6px 10px; background: #dbeafe; color: #1d4ed8; font-weight: 800; font-size: 11px; text-transform: uppercase; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px; }
        .box { border: 1px solid #dbe3ef; border-radius: 12px; padding: 12px; background: #fff; }
        .box h2 { font-size: 13px; margin: 0 0 8px; color: #1e3a8a; text-transform: uppercase; letter-spacing: .04em; }
        .row { display: flex; margin-bottom: 5px; gap: 8px; }
        .label { min-width: 120px; color: #64748b; font-weight: 700; }
        .value { font-weight: 700; color: #111827; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th { background: #eff6ff; color: #1e3a8a; text-align: left; font-size: 11px; padding: 9px; border: 1px solid #bfdbfe; text-transform: uppercase; }
        td { padding: 9px; border: 1px solid #dbe3ef; vertical-align: top; }
        .note { border: 1px solid #fde68a; background: #fffbeb; color: #78350f; border-radius: 12px; padding: 12px; margin: 14px 0; font-weight: 700; }
        .signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 28px; margin-top: 54px; }
        .signature { text-align: center; padding-top: 36px; border-top: 1px solid #111827; font-weight: 800; }
        .signature small { display: block; color: #64748b; font-weight: 600; margin-top: 4px; }
        .actions { position: fixed; top: 12px; right: 12px; }
        .print-btn { background: #2563eb; color: #fff; border: 0; border-radius: 8px; padding: 8px 12px; font-weight: 800; cursor: pointer; }
        @media print { .actions { display: none; } body { font-size: 11px; } }
    </style>
</head>
<body>
    <div class="actions">
        <button type="button" class="print-btn" onclick="window.print()">Imprimir / Guardar PDF</button>
    </div>

    <div class="top">
        <div>
            <h1 class="title">{{ $titulo }}</h1>
            <div class="subtitle">Documento #{{ $asignacion->id }} · Generado el {{ now()->format('d/m/Y H:i') }}</div>
        </div>
        <div><span class="badge">{{ $esDevolucion ? 'Devolución' : 'Asignación' }}</span></div>
    </div>

    <div class="grid">
        <div class="box">
            <h2>Vehículo</h2>
            <div class="row"><div class="label">Vehículo:</div><div class="value">{{ trim(($vehiculo->marca ?? 'Sin marca') . ' ' . ($vehiculo->modelo ?? '')) }}</div></div>
            <div class="row"><div class="label">Placa:</div><div class="value">{{ $vehiculo->placa ?? '—' }}</div></div>
            <div class="row"><div class="label">VIN:</div><div class="value">{{ $asignacion->vehiculo_vin }}</div></div>
        </div>

        <div class="box">
            <h2>Responsable del vehículo</h2>
            <div class="row"><div class="label">Colaborador:</div><div class="value">{{ optional($responsable)->nombre ?? 'Sin responsable activo' }}</div></div>
            <div class="row"><div class="label">Código:</div><div class="value">{{ optional($responsable)->codigo ?? optional($asignacion->asignacionVehiculo)->colaborador_codigo ?? $asignacion->colaborador_responsable_codigo ?? '—' }}</div></div>
            <div class="row"><div class="label">Puesto:</div><div class="value">{{ optional($responsable)->puesto ?? '—' }}</div></div>
        </div>
    </div>

    <div class="grid">
        <div class="box">
            <h2>Documento</h2>
            <div class="row"><div class="label">Fecha:</div><div class="value">{{ optional($fechaDocumento)->format('d/m/Y H:i') ?? '—' }}</div></div>
            <div class="row"><div class="label">Usuario genera:</div><div class="value">{{ optional($usuarioDocumento)->name ?? optional(auth()->user())->name ?? '—' }}</div></div>
            <div class="row"><div class="label">Bodega:</div><div class="value">{{ optional($asignacion->bodega)->nombre ?? ('Bodega #' . $asignacion->bodega_id) }}</div></div>
        </div>

        <div class="box">
            <h2>Estado</h2>
            <div class="row"><div class="label">Estado:</div><div class="value">{{ $asignacion->estado ?? ($asignacion->activa ? 'activo' : 'cerrado') }}</div></div>
            <div class="row"><div class="label">Acción cierre:</div><div class="value">{{ $asignacion->accion_cierre ?? ($asignacion->activa ? 'No aplica' : '—') }}</div></div>
            <div class="row"><div class="label">Motivo:</div><div class="value">{{ $asignacion->motivo ?? '—' }}</div></div>
        </div>
    </div>

    <div class="box">
        <h2>Productos {{ $esDevolucion ? 'devueltos / retirados' : 'asignados' }}</h2>
        <table>
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Producto</th>
                    <th>Cantidad</th>
                    <th>Estado</th>
                    <th>Observaciones</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ $asignacion->producto_codigo }}</td>
                    <td>{{ $producto->nombre ?? $producto->descripcion ?? '—' }}</td>
                    <td>{{ (int) $asignacion->cantidad }}</td>
                    <td>{{ $asignacion->estado ?? ($asignacion->activa ? 'activo' : 'cerrado') }}</td>
                    <td>{{ $asignacion->observaciones ?? '—' }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="note">
        Los productos detallados en este documento quedan bajo responsabilidad del colaborador que tiene asignado el vehículo indicado. El colaborador se compromete a cuidar, utilizar correctamente y devolver los productos cuando sean requeridos o al finalizar la asignación del vehículo.
    </div>

    <div class="signatures">
        <div class="signature">
            Firma colaborador responsable
            <small>{{ optional($responsable)->nombre ?? 'Nombre y firma' }}</small>
        </div>
        <div class="signature">
            Firma quien entrega / recibe
            <small>{{ optional($usuarioDocumento)->name ?? optional(auth()->user())->name ?? 'Usuario del sistema' }}</small>
        </div>
    </div>
</body>
</html>

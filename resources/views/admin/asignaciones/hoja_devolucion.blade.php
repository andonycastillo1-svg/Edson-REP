<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Hoja de devolución</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
            margin: 0;
            padding: 28px;
            background: #f8fafc;
            color: #0f172a;
            font-size: 12px;
        }

        .page {
            width: 900px;
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid #dbe3ea;
            padding: 30px 38px;
        }

        .print-actions {
            width: 900px;
            margin: 0 auto 14px auto;
            text-align: right;
        }

        .print-actions button {
            background: #0f172a;
            color: #ffffff;
            border: none;
            padding: 8px 14px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: bold;
            cursor: pointer;
        }

        .header {
            display: table;
            width: 100%;
            border-bottom: 3px solid #0f172a;
            padding-bottom: 14px;
            margin-bottom: 18px;
        }

        .header-left,
        .header-center,
        .header-right {
            display: table-cell;
            vertical-align: middle;
        }

        .header-left {
            width: 150px;
        }

        .header-right {
            width: 150px;
            text-align: right;
        }

        .logo {
            width: 120px;
            max-height: 65px;
            object-fit: contain;
        }

        .title {
            text-align: center;
        }

        .title h1 {
            margin: 0;
            font-size: 18px;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        .title p {
            margin: 4px 0 0;
            font-size: 11px;
            color: #475569;
        }

        .badge {
            display: inline-block;
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            border-radius: 6px;
            padding: 6px 8px;
            font-size: 10px;
            font-weight: bold;
        }

        .info-box {
            border: 1px solid #dbe3ea;
            background: #f8fafc;
            border-radius: 6px;
            padding: 12px 14px;
            margin-bottom: 16px;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
        }

        .info-table td {
            border: none;
            padding: 4px 0;
            font-size: 12px;
        }

        .label {
            width: 130px;
            font-weight: bold;
            color: #0f172a;
        }

        .section-title {
            background: #0f172a;
            color: #ffffff;
            padding: 8px 10px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            border-radius: 5px 5px 0 0;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #cbd5e1;
            margin-bottom: 16px;
        }

        .items-table th {
            background: #e5e7eb;
            color: #111827;
            border: 1px solid #cbd5e1;
            padding: 8px 6px;
            font-size: 11px;
            text-align: center;
        }

        .items-table td {
            border: 1px solid #cbd5e1;
            padding: 8px 6px;
            font-size: 11px;
            text-align: center;
        }

        .items-table .left {
            text-align: left;
        }

        .terms {
            border: 1px solid #cbd5e1;
            border-top: none;
            padding: 12px 14px;
            line-height: 1.45;
            text-align: justify;
            margin-bottom: 22px;
        }

        .signatures {
            width: 100%;
            border-collapse: collapse;
            margin-top: 30px;
        }

        .signatures td {
            width: 50%;
            border: none;
            vertical-align: top;
            padding: 0 18px;
        }

        .signature-box {
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 14px;
        }

        .signature-title {
            text-align: center;
            font-weight: bold;
            font-size: 12px;
            margin-bottom: 20px;
            text-transform: uppercase;
        }

        .line-row {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        .line-row td {
            border: none;
            padding: 0;
            vertical-align: bottom;
        }

        .line-label {
            width: 55px;
            font-weight: bold;
            font-size: 11px;
        }

        .line {
            border-bottom: 1px solid #111827 !important;
            height: 20px;
            padding-left: 5px !important;
            font-size: 11px;
        }

        .footer {
            margin-top: 22px;
            padding-top: 8px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 9px;
            color: #64748b;
        }

        @media print {
            body {
                background: #ffffff;
                padding: 0;
            }

            .print-actions {
                display: none;
            }

            .page {
                width: 100%;
                border: none;
                padding: 20px 28px;
            }
        }
    </style>
</head>

<body>

<div class="print-actions">
    <button onclick="window.print()">🖨 Imprimir hoja</button>
</div>

<div class="page">

    <div class="header">
        <div class="header-left">
            <img src="{{ asset('img/logo.png') }}" class="logo" alt="Logo">
        </div>

        <div class="header-center">
            <div class="title">
                <h1>Formato de devolución de equipos y herramientas</h1>
                <p>Grupo Net Solutions, S.A.</p>
                <p>Departamento de Bodega</p>
            </div>
        </div>

        <div class="header-right">
            <span class="badge">Devolución</span>
        </div>
    </div>

    <div class="info-box">
        <table class="info-table">
            <tr>
                <td class="label">Grupo devolución:</td>
                <td>{{ $grupo }}</td>
            </tr>

            <tr>
                <td class="label">Fecha devolución:</td>
                <td>{{ optional($primerMovimiento->created_at)->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i') }}</td>
            </tr>

            <tr>
                <td class="label">Colaborador:</td>
                <td>
                    {{ optional(optional($primerMovimiento->asignacion)->colaborador)->nombre ?? 'N/A' }}
                    /
                    <strong>Código:</strong>
                    {{ optional($primerMovimiento->asignacion)->colaborador_codigo ?? 'N/A' }}
                </td>
            </tr>

            <tr>
                <td class="label">Recibido por:</td>
                <td>{{ optional($primerMovimiento->user)->name ?? 'N/A' }}</td>
            </tr>

            <tr>
                <td class="label">Detalle:</td>
                <td>{{ $primerMovimiento->detalle ?? 'Devolución de equipo asignado.' }}</td>
            </tr>
        </table>
    </div>

    <div class="section-title">Detalle de productos devueltos</div>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 45%;">Producto</th>
                <th style="width: 15%;">Código</th>
                <th style="width: 15%;">Cantidad</th>
                <th style="width: 25%;">Bodega</th>
            </tr>
        </thead>

        <tbody>
            @forelse($movimientos as $movimiento)
                @php
                    $asignacion = $movimiento->asignacion;
                    $producto = optional($asignacion)->producto;
                    $bodega = optional($asignacion)->bodega;
                @endphp

                <tr>
                    <td class="left">
                        {{ $producto->descripcion ?? $producto->nombre ?? 'N/A' }}
                    </td>

                    <td>
                        {{ optional($asignacion)->producto_codigo ?? 'N/A' }}
                    </td>

                    <td>
                        {{ $movimiento->cantidad }}
                    </td>

                    <td>
                        {{ $bodega->nombre ?? 'N/A' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">No hay productos en esta devolución.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-title">Condiciones de devolución</div>

    <div class="terms">
        Por este medio se deja constancia de la devolución del equipo, herramienta o material indicado en este documento.
        El área de bodega recibe los artículos detallados para su revisión física, validación de estado y actualización
        del inventario correspondiente. Cualquier daño, faltante o diferencia detectada podrá ser reportada según las
        políticas internas de la empresa.
    </div>

    <table class="signatures">
        <tr>
            <td>
                <div class="signature-box">
                    <div class="signature-title">Entrega colaborador</div>

                    <table class="line-row">
                        <tr>
                            <td class="line-label">Firma</td>
                            <td class="line"></td>
                        </tr>
                    </table>

                    <table class="line-row">
                        <tr>
                            <td class="line-label">Nombre</td>
                            <td class="line">
                                {{ optional(optional($primerMovimiento->asignacion)->colaborador)->nombre ?? '' }}
                            </td>
                        </tr>
                    </table>

                    <table class="line-row">
                        <tr>
                            <td class="line-label">Fecha</td>
                            <td class="line">
                                {{ optional($primerMovimiento->created_at)->format('d/m/Y') ?? now()->format('d/m/Y') }}
                            </td>
                        </tr>
                    </table>
                </div>
            </td>

            <td>
                <div class="signature-box">
                    <div class="signature-title">Recibe bodega</div>

                    <table class="line-row">
                        <tr>
                            <td class="line-label">Firma</td>
                            <td class="line"></td>
                        </tr>
                    </table>

                    <table class="line-row">
                        <tr>
                            <td class="line-label">Nombre</td>
                            <td class="line">
                                {{ optional($primerMovimiento->user)->name ?? '' }}
                            </td>
                        </tr>
                    </table>

                    <table class="line-row">
                        <tr>
                            <td class="line-label">Fecha</td>
                            <td class="line">
                                {{ optional($primerMovimiento->created_at)->format('d/m/Y') ?? now()->format('d/m/Y') }}
                            </td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <div class="footer">
        Documento generado por el sistema de bodega de Grupo Net Solutions, S.A.
    </div>

</div>

</body>
</html>
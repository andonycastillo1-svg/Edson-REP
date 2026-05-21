@php
    $primerAsignacion = $primerMovimiento->asignacion ?? null;
    $colaborador = $primerAsignacion?->colaborador;
    $usuarioRecibe = $primerMovimiento->user;

    $fechaAsignacion = $primerAsignacion?->fecha
        ? \Carbon\Carbon::parse($primerAsignacion->fecha)->format('d/m/Y')
        : '____/____/________';

    $fechaDevolucion = $primerMovimiento->created_at
        ? $primerMovimiento->created_at->format('d/m/Y')
        : now()->format('d/m/Y');

    $detalleGeneral = $movimientos
        ->pluck('detalle')
        ->filter()
        ->unique()
        ->implode(' / ');

    $nombreColaborador = $colaborador->nombre ?? '____________________________';
    $codigoColaborador = $colaborador->codigo ?? $primerAsignacion?->colaborador_codigo ?? '__________';

    $unidad = $colaborador->unidad
        ?? $colaborador->departamento
        ?? $colaborador->area
        ?? '____________________________';

    $region = $colaborador->region
        ?? $colaborador->zona
        ?? '____________________________';

    $responsableRecibe = $usuarioRecibe?->name ?? '____________________________';

    /*
      Ajusta aquí la ruta si tu logo está en otra carpeta.
      El código intenta encontrarlo en varias rutas comunes.
    */
    $logoCandidates = [
        'images/logo.png',
        'img/logo.png',
        'assets/logo.png',
        'logos/logo.png',
        'images/grupo-net-solutions.png',
        'img/grupo-net-solutions.png',
    ];

    $logoPath = null;

    foreach ($logoCandidates as $candidate) {
        if (file_exists(public_path($candidate))) {
            $logoPath = asset($candidate);
            break;
        }
    }
@endphp

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Acta de devolución de herramienta / descargue de responsabilidad</title>

    <style>
        @page {
            size: letter;
            margin: 16mm 14mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #e5e7eb;
            color: #111827;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            line-height: 1.35;
        }

        .page {
            width: 216mm;
            min-height: 279mm;
            margin: 0 auto;
            background: #ffffff;
            padding: 20px 26px 24px;
            position: relative;
            overflow: hidden;
        }

        .page::after {
            content: "NS";
            position: absolute;
            right: -35px;
            top: 120px;
            font-size: 210px;
            font-weight: 800;
            color: rgba(146, 196, 125, 0.10);
            transform: rotate(-8deg);
            z-index: 0;
            letter-spacing: -18px;
        }

        .content {
            position: relative;
            z-index: 1;
        }

        .header {
            display: grid;
            grid-template-columns: 120px 1fr 120px;
            align-items: start;
            margin-bottom: 16px;
        }

        .logo-wrap {
            min-height: 58px;
            display: flex;
            align-items: flex-start;
            justify-content: flex-start;
        }

        .logo {
            width: 74px;
            height: auto;
        }

        .logo-text {
            font-size: 10px;
            color: #64748b;
            font-weight: 700;
            line-height: 1.1;
        }

        .title {
            text-align: center;
            font-weight: 800;
            font-size: 15px;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-top: 20px;
            line-height: 1.45;
        }

        .company-block {
            margin-top: 8px;
            margin-bottom: 22px;
            font-weight: 700;
        }

        .company-block div {
            margin-bottom: 2px;
        }

        .section-title {
            margin-top: 18px;
            margin-bottom: 6px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .paragraph {
            text-align: justify;
            margin: 0 0 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .products-table {
            margin-top: 8px;
            margin-bottom: 24px;
        }

        .products-table th,
        .products-table td {
            border: 1px solid #75c96b;
            padding: 5px 6px;
            vertical-align: top;
        }

        .products-table th {
            font-weight: 800;
            text-align: left;
        }

        .products-table .item-col {
            width: 34px;
            text-align: center;
        }

        .products-table .cantidad-col {
            width: 58px;
            text-align: center;
        }

        .products-table .costo-col {
            width: 210px;
        }

        .observaciones {
            margin-top: 24px;
            margin-bottom: 18px;
        }

        .line {
            border-bottom: 1px solid #8b8b8b;
            height: 28px;
            margin-bottom: 4px;
        }

        .declaracion {
            margin-top: 10px;
            margin-bottom: 34px;
        }

        .signature-table {
            margin-top: 16px;
        }

        .signature-table th,
        .signature-table td {
            border: 1px solid #75c96b;
            padding: 5px 7px;
            height: 22px;
            vertical-align: middle;
        }

        .signature-table th {
            text-align: left;
            font-weight: 400;
        }

        .signature-table .half {
            width: 50%;
        }

        .signature-space {
            height: 38px;
        }

        .print-actions {
            width: 216mm;
            margin: 12px auto;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }

        .btn {
            border: 1px solid #cbd5e1;
            background: #ffffff;
            color: #0f172a;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }

        .btn-primary {
            background: #0f172a;
            color: #ffffff;
            border-color: #0f172a;
        }

        .text-muted {
            color: #475569;
        }

        @media print {
            body {
                background: #ffffff;
            }

            .page {
                width: auto;
                min-height: auto;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }

            .print-actions {
                display: none;
            }
        }
    </style>
</head>

<body>

<div class="print-actions">
    <button type="button" class="btn" onclick="window.close()">Cerrar</button>
    <button type="button" class="btn btn-primary" onclick="window.print()">Imprimir / Guardar PDF</button>
</div>

<div class="page">
    <div class="content">

        <div class="header">
            <div class="logo-wrap">
                @if($logoPath)
                    <img src="{{ $logoPath }}" alt="Grupo NetSolutions" class="logo">
                @else
                    <div class="logo-text">
                        Grupo NetSolutions
                    </div>
                @endif
            </div>

            <div class="title">
                ACTA DE DEVOLUCIÓN DE HERRAMIENTA / DESCARGUE DE<br>
                RESPONSABILIDAD
            </div>

            <div></div>
        </div>

        <div class="company-block">
            <div>Grupo Net Solutions, S.A</div>
            <div>Departamento de Recursos Humanos</div>
            <div>Nombre del Colaborador: {{ $nombreColaborador }}</div>
            <div>Unidad: {{ $unidad }}</div>
            <div>Región: {{ $region }}</div>
        </div>

        <div class="section-title">ANTECEDENTES:</div>

        <p class="paragraph">
            Con fecha {{ $fechaAsignacion }}, el colaborador recibió la(s) herramienta(s)
            detallada(s) en la hoja de entrega firmada. La presente acta deja constancia de la
            devolución de dichos elementos y el cierre de su responsabilidad sobre los mismos.
        </p>

        <table class="products-table">
            <thead>
                <tr>
                    <th class="item-col">Ítem</th>
                    <th>Descripción de la herramienta</th>
                    <th class="cantidad-col">Cantidad</th>
                    <th class="costo-col">Costo/Estado al momento de devolución</th>
                </tr>
            </thead>

            <tbody>
                @foreach($movimientos as $index => $movimiento)
                    @php
                        $asignacion = $movimiento->asignacion;
                        $producto = $asignacion?->producto;

                        $descripcion = $producto?->descripcion
                            ?? $producto?->nombre
                            ?? $asignacion?->producto_codigo
                            ?? 'Producto sin descripción';

                        $codigoProducto = $asignacion?->producto_codigo;

                        $costoUnitario = $asignacion?->costo_unitario ?? 0;
                        $estadoAsignacion = $asignacion?->estado ?? 'Devuelto';
                    @endphp

                    <tr>
                        <td class="item-col">{{ $index + 1 }}</td>

                        <td>
                            {{ $descripcion }}

                            @if($codigoProducto)
                                <br>
                                <span class="text-muted">Código: {{ $codigoProducto }}</span>
                            @endif
                        </td>

                        <td class="cantidad-col">
                            {{ $movimiento->cantidad }}
                        </td>

                        <td>
                            @if((float) $costoUnitario > 0)
                                Q{{ number_format((float) $costoUnitario, 2) }}
                            @else
                                {{ $estadoAsignacion }}
                            @endif
                        </td>
                    </tr>
                @endforeach

                @for($i = $movimientos->count() + 1; $i <= 8; $i++)
                    <tr>
                        <td class="item-col">{{ $i }}</td>
                        <td>&nbsp;</td>
                        <td class="cantidad-col">&nbsp;</td>
                        <td>&nbsp;</td>
                    </tr>
                @endfor
            </tbody>
        </table>

        <div class="observaciones">
            <div class="section-title">OBSERVACIONES:</div>

            <div class="line">
                {{ $detalleGeneral }}
            </div>
            <div class="line"></div>
        </div>

        <div class="declaracion">
            <div class="section-title">DECLARACIÓN:</div>

            <p class="paragraph">
                Por medio del presente documento, el colaborador deja constancia de la devolución de la
                herramienta detallada y se exonera de toda responsabilidad sobre dichos elementos a partir
                de esta fecha.
            </p>
        </div>

        <table class="signature-table">
            <tr>
                <th class="half">Responsable de Recepción:</th>
                <th class="half">Responsable de Entrega:</th>
            </tr>

            <tr>
                <td>Fecha</td>
                <td>Fecha</td>
            </tr>

            <tr>
                <td>{{ $fechaDevolucion }}</td>
                <td>{{ $fechaDevolucion }}</td>
            </tr>

            <tr>
                <td>Firma</td>
                <td>Nombre</td>
            </tr>

            <tr>
                <td class="signature-space"></td>
                <td>{{ $nombreColaborador }}</td>
            </tr>

            <tr>
                <td>Nombre</td>
                <td>DPI</td>
            </tr>

            <tr>
                <td>{{ $responsableRecibe }}</td>
                <td>{{ $colaborador->dpi ?? '' }}</td>
            </tr>

            <tr>
                <td>DPI</td>
                <td></td>
            </tr>
        </table>

    </div>
</div>

</body>
</html>
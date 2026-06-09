<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Formato de entrega</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
            margin: 0;
            padding: 0;
            background: #ffffff;
            color: #020617;
            font-size: 12px;
        }

        .no-print {
            width: 100%;
            max-width: 980px;
            margin: 20px auto 0 auto;
            text-align: right;
        }

        .btn-print {
            background: #0f172a;
            color: #ffffff;
            border: none;
            padding: 9px 16px;
            border-radius: 6px;
            font-size: 13px;
            cursor: pointer;
        }

        .sheet {
            width: 980px;
            margin: 18px auto 40px auto;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            padding: 32px 42px;
        }

        .top-bar {
            width: 100%;
            border-bottom: 3px solid #0f172a;
            padding-bottom: 14px;
            margin-bottom: 20px;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table td {
            border: none;
            vertical-align: middle;
            padding: 0;
        }

        .logo-box {
            width: 190px;
        }

        .logo {
            width: 130px;
            max-height: 70px;
            object-fit: contain;
        }

        .title-box {
            text-align: center;
        }

        .title-box h1 {
            margin: 0;
            font-size: 18px;
            text-transform: uppercase;
            letter-spacing: .6px;
            color: #0f172a;
            line-height: 1.25;
        }

        .title-box .company {
            margin-top: 5px;
            font-size: 12px;
            font-weight: bold;
            color: #1f2937;
        }

        .title-box .department {
            margin-top: 2px;
            font-size: 11px;
            color: #4b5563;
        }

        .doc-info {
            width: 190px;
            text-align: right;
            font-size: 10px;
            color: #374151;
        }

        .badge {
            display: inline-block;
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            padding: 5px 8px;
            border-radius: 5px;
            font-weight: bold;
        }

        .info-card {
            border: 1px solid #d1d5db;
            background: #f9fafb;
            padding: 12px 14px;
            margin-bottom: 18px;
            border-radius: 6px;
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
            font-weight: bold;
            color: #111827;
            width: 145px;
        }

        .value {
            color: #111827;
        }

        .section-title {
            background: #0f172a;
            color: #ffffff;
            font-size: 12px;
            font-weight: bold;
            padding: 8px 11px;
            border-radius: 5px 5px 0 0;
            margin-top: 14px;
            text-transform: uppercase;
            letter-spacing: .3px;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
            border: 1px solid #cbd5e1;
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

        .items-table .desc {
            text-align: left;
        }

        .responsibility-box {
            border: 1px solid #cbd5e1;
            border-top: none;
            padding: 13px 15px;
            text-align: justify;
            line-height: 1.48;
            margin-bottom: 20px;
        }

        .responsibility-box p {
            margin: 0 0 8px 0;
        }

        .responsibility-box ul {
            margin: 7px 0 0 18px;
            padding: 0;
        }

        .responsibility-box li {
            margin-bottom: 5px;
        }

        .accept-text {
            margin-top: 10px;
            font-weight: bold;
        }

        .signature-wrapper {
            margin-top: 28px;
        }

        .signature-table {
            width: 100%;
            border-collapse: collapse;
        }

        .signature-table td {
            border: none;
            vertical-align: top;
            width: 50%;
        }

        .signature-box {
            width: 92%;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 13px 14px 10px 14px;
        }

        .signature-box.right {
            margin-left: auto;
        }

        .signature-title {
            text-align: center;
            font-weight: bold;
            font-size: 12px;
            color: #0f172a;
            margin-bottom: 18px;
            text-transform: uppercase;
        }

        .field-row {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        .field-row td {
            border: none;
            padding: 0;
            vertical-align: bottom;
        }

        .field-label {
            width: 55px;
            font-weight: bold;
            font-size: 11px;
            color: #111827;
        }

        .field-line {
            border-bottom: 1px solid #111827 !important;
            height: 20px;
            font-size: 11px;
            padding-left: 5px !important;
        }

        .footer-note {
            margin-top: 22px;
            padding-top: 8px;
            border-top: 1px solid #e5e7eb;
            font-size: 9px;
            color: #6b7280;
            text-align: center;
        }

        @media print {
            body {
                background: #ffffff;
            }

            .no-print {
                display: none;
            }

            .sheet {
                width: 100%;
                margin: 0;
                border: none;
                padding: 20px 30px;
            }
        }
    </style>
</head>

<body>

@php
    /*
    |--------------------------------------------------------------------------
    | Preparar lista de asignaciones / items
    |--------------------------------------------------------------------------
    */

    $listaItems = $items ?? $asignaciones ?? null;

    if (!$listaItems) {
        if (isset($grupo) && isset($grupo->asignaciones)) {
            $listaItems = $grupo->asignaciones;
        } elseif (isset($asignacion)) {
            $listaItems = collect([$asignacion]);
        } else {
            $listaItems = collect([]);
        }
    }

    if (!($listaItems instanceof \Illuminate\Support\Collection)) {
        $listaItems = collect($listaItems);
    }

    $primerItem = $listaItems->first();

    /*
    |--------------------------------------------------------------------------
    | Colaborador / técnico
    |--------------------------------------------------------------------------
    */

    $colaborador =
        $grupo->colaborador
        ?? $grupo->tecnico
        ?? $grupo->empleado
        ?? $grupo->persona
        ?? $asignacion->colaborador
        ?? $asignacion->tecnico
        ?? $asignacion->empleado
        ?? $asignacion->persona
        ?? $primerItem->colaborador
        ?? $primerItem->tecnico
        ?? $primerItem->empleado
        ?? $primerItem->persona
        ?? null;

    $nombreColaborador =
        $colaborador->nombre
        ?? $colaborador->name
        ?? $colaborador->nombre_completo
        ?? $colaborador->full_name
        ?? $grupo->colaborador_nombre
        ?? $grupo->nombre_colaborador
        ?? $grupo->nombre_tecnico
        ?? $grupo->tecnico_nombre
        ?? $grupo->empleado_nombre
        ?? $grupo->persona_nombre
        ?? $grupo->nombre
        ?? $asignacion->colaborador_nombre
        ?? $asignacion->nombre_colaborador
        ?? $asignacion->nombre_tecnico
        ?? $asignacion->tecnico_nombre
        ?? $asignacion->empleado_nombre
        ?? $asignacion->persona_nombre
        ?? $asignacion->nombre
        ?? $primerItem->colaborador_nombre
        ?? $primerItem->nombre_colaborador
        ?? $primerItem->nombre_tecnico
        ?? $primerItem->tecnico_nombre
        ?? $primerItem->empleado_nombre
        ?? $primerItem->persona_nombre
        ?? $primerItem->nombre
        ?? 'N/A';

    $codigoColaborador =
        $colaborador->codigo
        ?? $colaborador->codigo_colaborador
        ?? $colaborador->id_empleado
        ?? $colaborador->id
        ?? $grupo->codigo_colaborador
        ?? $grupo->colaborador_codigo
        ?? $grupo->codigo_tecnico
        ?? $grupo->tecnico_codigo
        ?? $grupo->codigo
        ?? $asignacion->codigo_colaborador
        ?? $asignacion->colaborador_codigo
        ?? $asignacion->codigo_tecnico
        ?? $asignacion->tecnico_codigo
        ?? $asignacion->codigo
        ?? $primerItem->codigo_colaborador
        ?? $primerItem->colaborador_codigo
        ?? $primerItem->codigo_tecnico
        ?? $primerItem->tecnico_codigo
        ?? $primerItem->codigo
        ?? 'N/A';

    $dpiColaborador =
        $colaborador->dpi
        ?? $colaborador->identidad
        ?? $colaborador->documento
        ?? $grupo->dpi
        ?? $grupo->identidad
        ?? $grupo->documento
        ?? $asignacion->dpi
        ?? $asignacion->identidad
        ?? $asignacion->documento
        ?? $primerItem->dpi
        ?? $primerItem->identidad
        ?? $primerItem->documento
        ?? '';

    $puestoColaborador =
        $colaborador->puesto
        ?? $colaborador->cargo
        ?? $colaborador->posicion
        ?? $colaborador->position
        ?? $grupo->puesto
        ?? $grupo->cargo
        ?? $grupo->puesto_colaborador
        ?? $grupo->cargo_colaborador
        ?? $grupo->puesto_tecnico
        ?? $grupo->cargo_tecnico
        ?? $asignacion->puesto
        ?? $asignacion->cargo
        ?? $asignacion->puesto_colaborador
        ?? $asignacion->cargo_colaborador
        ?? $asignacion->puesto_tecnico
        ?? $asignacion->cargo_tecnico
        ?? $primerItem->puesto
        ?? $primerItem->cargo
        ?? $primerItem->puesto_colaborador
        ?? $primerItem->cargo_colaborador
        ?? $primerItem->puesto_tecnico
        ?? $primerItem->cargo_tecnico
        ?? 'N/A';

    /*
    |--------------------------------------------------------------------------
    | Área / unidad
    |--------------------------------------------------------------------------
    */

    $unidadArea =
        $grupo->unidad_area
        ?? $grupo->area
        ?? $grupo->departamento
        ?? $grupo->unidad
        ?? $asignacion->unidad_area
        ?? $asignacion->area
        ?? $asignacion->departamento
        ?? $asignacion->unidad
        ?? $primerItem->unidad_area
        ?? $primerItem->area
        ?? $primerItem->departamento
        ?? $primerItem->unidad
        ?? 'N/A';

    /*
    |--------------------------------------------------------------------------
    | Fecha general
    |--------------------------------------------------------------------------
    */

    $fechaGeneral =
        $grupo->fecha_entrega
        ?? $grupo->fecha_asignacion
        ?? $asignacion->fecha_entrega
        ?? $asignacion->fecha_asignacion
        ?? $primerItem->fecha_entrega
        ?? $primerItem->fecha_asignacion
        ?? now();
@endphp

<div class="no-print">
    <button type="button" class="btn-print" onclick="window.print()">🖨 Imprimir ficha</button>
</div>

<div class="sheet">

    <div class="top-bar">
        <table class="header-table">
            <tr>
                <td class="logo-box">
                    <img src="{{ asset('img/logo.png') }}" class="logo" alt="Logo NMS">
                </td>

                <td class="title-box">
                    <h1>Formato de entrega de equipos y herramientas</h1>
                    <div class="company">Grupo Net Solutions, S.A.</div>
                    <div class="department">Departamento de Bodega</div>
                </td>

                <td class="doc-info">
                    <span class="badge">Ficha de asignación</span>
                </td>
            </tr>
        </table>
    </div>

    <div class="info-card">
        <table class="info-table">
            <tr>
                <td class="label">Unidad o Área:</td>
                <td class="value">
                    {{ $unidadArea }}
                </td>
            </tr>

            <tr>
                <td class="label">Nombre de técnico:</td>
                <td class="value">
                    {{ $nombreColaborador }}
                    &nbsp; / &nbsp;
                    <strong>Código:</strong>
                    {{ $codigoColaborador }}
                </td>
            </tr>

            <tr>
                <td class="label">Puesto:</td>
                <td class="value">
                    {{ $puestoColaborador }}
                </td>
            </tr>

            <tr>
                <td class="label">Detalle:</td>
                <td class="value">
                    Asignación de equipos EPP, herramienta y/o utilidad en campo.
                </td>
            </tr>
        </table>
    </div>

    <div class="section-title">Detalle de equipo entregado</div>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 52%;">Descripción</th>
                <th style="width: 12%;">Cantidad</th>
                <th style="width: 18%;">Tiempo vida útil</th>
                <th style="width: 18%;">Fecha de entrega</th>
            </tr>
        </thead>

        <tbody>
            @forelse($listaItems as $item)
                @php
                    $descripcion =
                        $item->producto->descripcion
                        ?? $item->producto->nombre
                        ?? $item->producto_descripcion
                        ?? $item->descripcion
                        ?? $item->nombre_producto
                        ?? $item->nombre
                        ?? 'N/A';

                    $cantidad =
                        $item->cantidad
                        ?? $item->qty
                        ?? 1;

                    $vidaUtil =
                        $item->vida_util_meses
                        ?? $item->producto->vida_util_meses
                        ?? $item->vida_util
                        ?? $item->producto->vida_util
                        ?? null;

                    $fechaEntrega =
                        $item->fecha_entrega
                        ?? $item->fecha_asignacion
                        ?? $fechaGeneral
                        ?? now();
                @endphp

                <tr>
                    <td class="desc">
                        {{ $descripcion }}
                    </td>

                    <td>
                        {{ $cantidad }}
                    </td>

                    <td>
                        {{ $vidaUtil ? $vidaUtil . ' meses' : 'N/A' }}
                    </td>

                    <td>
                        {{ \Carbon\Carbon::parse($fechaEntrega)->format('d/m/Y') }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td class="desc">N/A</td>
                    <td>1</td>
                    <td>N/A</td>
                    <td>{{ now()->format('d/m/Y') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-title">Condiciones de responsabilidad</div>

    <div class="responsibility-box">
        <p>
            Por este acto me reconozco responsable ante Grupo Net Solutions de cualquier daño que le ocurra
            a la herramienta y/o equipo asignado, comprometiéndome a lo siguiente:
        </p>

        <ul>
            <li>
                Utilizar la herramienta y equipo única y exclusivamente para fines de los proyectos asignados.
            </li>

            <li>
                Notificar inicial e inmediatamente de manera verbal y por escrito vía correo electrónico,
                adjuntando imágenes según sea el caso, en el plazo de veinticuatro horas cualquier incidente,
                percance, robo o daño que sufra la herramienta y equipo a mi cargo, junto a denuncia del
                Ministerio Público cuando aplique.
            </li>

            <li>
                Asumir la responsabilidad civil derivada de negligencia, percance, robo o daño.
            </li>

            <li>
                Velar porque la herramienta, equipo y uniformes se mantengan en buenas condiciones de
                funcionamiento durante el tiempo que estén a mi cargo.
            </li>

            <li>
                Velar porque los mismos sean almacenados o guardados en lugares seguros mientras no estén
                siendo utilizados en tareas propias de la labor asignada.
            </li>

            <li>
                Permitir que la herramienta y equipo sean objeto de inspecciones, auditorías o revisiones
                por parte de mis superiores, sin previo aviso, para verificar su estado en cualquier momento.
            </li>
        </ul>

        <p class="accept-text">
            He leído y acepto las condiciones del uso de herramienta y equipo de la empresa.
        </p>
    </div>

    <div class="signature-wrapper">
        <table class="signature-table">
            <tr>
                <td>
                    <div class="signature-box">
                        <div class="signature-title">Responsable de Recepción</div>

                        <table class="field-row">
                            <tr>
                                <td class="field-label">Fecha</td>
                                <td class="field-line">
                                    {{ \Carbon\Carbon::parse($fechaGeneral)->format('d/m/Y') }}
                                </td>
                            </tr>
                        </table>

                        <table class="field-row">
                            <tr>
                                <td class="field-label">Firma</td>
                                <td class="field-line"></td>
                            </tr>
                        </table>

                        <table class="field-row">
                            <tr>
                                <td class="field-label">Nombre</td>
                                <td class="field-line">
                                    {{ $nombreColaborador }}
                                </td>
                            </tr>
                        </table>

                        <table class="field-row">
                            <tr>
                                <td class="field-label">Puesto</td>
                                <td class="field-line">
                                    {{ $puestoColaborador }}
                                </td>
                            </tr>
                        </table>

                        <table class="field-row">
                            <tr>
                                <td class="field-label">DPI</td>
                                <td class="field-line">
                                    {{ $dpiColaborador }}
                                </td>
                            </tr>
                        </table>
                    </div>
                </td>

                <td>
                    <div class="signature-box right">
                        <div class="signature-title">Responsable de Entrega</div>

                        <table class="field-row">
                            <tr>
                                <td class="field-label">Fecha</td>
                                <td class="field-line">
                                    {{ now()->format('d/m/Y') }}
                                </td>
                            </tr>
                        </table>

                        <table class="field-row">
                            <tr>
                                <td class="field-label">Firma</td>
                                <td class="field-line"></td>
                            </tr>
                        </table>

                        <table class="field-row">
                            <tr>
                                <td class="field-label">Nombre</td>
                                <td class="field-line">
                                    {{ auth()->user()->name ?? 'Edson Alexander Gallina Tinti' }}
                                </td>
                            </tr>
                        </table>

                        <table class="field-row">
                            <tr>
                                <td class="field-label">Puesto</td>
                                <td class="field-line">
                                    Bodega
                                </td>
                            </tr>
                        </table>

                        <table class="field-row">
                            <tr>
                                <td class="field-label">DPI</td>
                                <td class="field-line"></td>
                            </tr>
                        </table>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="footer-note">
        Documento generado por el sistema de bodega de Grupo Net Solutions, S.A.
    </div>

</div>

</body>
</html>
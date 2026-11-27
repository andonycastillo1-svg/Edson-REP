<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $formato['titulo'] }}</title>
    <style>
        @page { size: letter; margin: .48in .58in; }

        * { box-sizing: border-box; }
        body { margin: 0; color: #172b4d; font: 10pt/1.36 Arial, "DejaVu Sans", sans-serif; }
        .hoja { width: 100%; }
        .cabecera, .datos, .lineas, .firmas { width: 100%; border-collapse: collapse; }
        .cabecera { border-bottom: 2px solid #1d4f91; margin-bottom: 12pt; }
        .cabecera td { padding: 0 0 10pt; vertical-align: middle; }
        .cabecera .marca { padding-right: 12pt; }
        .marca img { display: block; max-width: 100%; max-height: 64pt; height: auto; }
        h1 { margin: 2pt 0 0; font-size: 20pt; line-height: 1.15; color: #10263f; }
        h2 { margin: 12pt 0 5pt; font-size: 1.12em; color: #10263f; }
        p { margin: 0; word-wrap: break-word; overflow-wrap: anywhere; }
        .empresa { color: #1d4f91; }
        .meta { color: #52647a; font-size: .88em; }
        .referencia { margin-top: 4pt; }

        .datos td {
            width: 33.33%;
            padding: 6pt 7pt;
            border: 1px solid #d8e1ec;
            background: #f3f6fa;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: anywhere;
        }
        .datos-verticales td { width: 100%; }
        .datos strong { display: block; margin-bottom: 1pt; color: #52647a; font-size: .82em; }

        .lineas { table-layout: fixed; }
        .lineas th, .lineas td {
            padding: 6pt 7pt;
            border: 1px solid #d8e1ec;
            text-align: left;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: anywhere;
        }
        .lineas th { background: #eaf1f9; color: #10263f; font-size: .88em; }
        .lineas th:first-child { width: 18%; }
        .lineas-amplias th:nth-child(2) { width: 30%; }
        .lineas .cantidad { width: 14%; text-align: right; font-variant-numeric: tabular-nums; }
        .lineas thead { display: table-header-group; }
        .lineas tr { page-break-inside: avoid; }
        .lineas tbody tr:nth-child(even) { background: #f8fafc; }

        .texto { margin: 10pt 0; white-space: pre-line; }
        .condiciones { padding-left: 9pt; border-left: 2px solid #1d4f91; font-size: .92em; }
        .firmas { margin-top: 12pt; table-layout: fixed; page-break-inside: avoid; }
        .firmas td { width: 50%; padding: 21pt 8pt 0 0; vertical-align: top; }
        .firmas td + td { padding-right: 0; padding-left: 8pt; }
        .firma { border-top: 1px solid #52647a; padding-top: 4pt; word-wrap: break-word; }
        .firma p { margin-top: 2pt; }
        .pie { padding-top: 5pt; border-top: 1px solid #d8e1ec; }

        .visor { display: none; }

        @media screen {
            html { background: #edf2f7; }
            body { font-size: 16px; line-height: 1.48; }
            .visor {
                display: block;
                background: #10263f;
                color: white;
            }
            .visor-contenido {
                width: min(calc(100% - 32px), 8.5in);
                margin: auto;
                padding: 14px 0;
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                justify-content: space-between;
                gap: 10px 20px;
            }
            .visor-titulo { font-size: 16px; font-weight: 700; }
            .visor-formato { color: #d6e4f5; font-size: 13px; }
            .visor-acciones { display: flex; flex-wrap: wrap; gap: 8px; }
            .visor-accion {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-height: 44px;
                padding: 8px 15px;
                border: 1px solid #aac4e6;
                border-radius: 8px;
                background: #1d4f91;
                color: white;
                font: inherit;
                font-size: 14px;
                font-weight: 700;
                text-decoration: none;
                cursor: pointer;
            }
            .visor-accion:hover { background: #2562aa; }
            .visor-descarga { background: transparent; }
            .visor-accion:focus-visible { outline: 3px solid #d6e4f5; outline-offset: 3px; }
            .contenedor-tabla:focus-visible { outline: 3px solid #2563eb; outline-offset: 2px; }

            .hoja {
                width: min(calc(100% - 32px), 8.5in);
                min-height: 11in;
                margin: 26px auto 36px;
                padding: clamp(24px, 5vw, .60in);
                background: white;
                border: 1px solid #d8e1ec;
                border-radius: 4px;
                box-shadow: 0 12px 32px #10263f17;
            }
            .cabecera { margin-bottom: 20px; }
            .cabecera td { padding-bottom: 14px; }
            h1 { font-size: clamp(23px, 3.1vw, 32px); }
            .datos { table-layout: fixed; }
            .contenedor-tabla { overflow-x: auto; }
        }

        @media screen and (max-width: 600px) {
            .visor-contenido { width: calc(100% - 24px); padding: 12px 0; }
            .hoja { width: calc(100% - 24px); min-height: 0; margin: 12px auto 24px; padding: 16px; }
            .cabecera .marca { width: 62px !important; padding-right: 10px; }
            .marca img { max-height: 56px; }
            .datos { display: block; }
            .datos tbody { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1px; }
            .datos-verticales tbody { grid-template-columns: minmax(0, 1fr); }
            .datos tr { display: contents; }
            .datos td { width: auto; }
            .lineas th:first-child { width: 22%; }
            .lineas .cantidad { width: 20%; }
            .lineas-amplias, .lineas-amplias tbody { display: block; }
            .lineas-amplias thead {
                position: absolute;
                width: 1px;
                height: 1px;
                overflow: hidden;
                clip-path: inset(50%);
            }
            .lineas-amplias tbody tr {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 8px 12px;
                margin-bottom: 8px;
                padding: 10px 12px;
                border: 1px solid #d8e1ec;
                border-radius: 6px;
            }
            .lineas-amplias td, .lineas-amplias td.cantidad {
                width: auto;
                padding: 0;
                border: 0;
                text-align: left;
                font-size: 14px;
            }
            .lineas-amplias td::before {
                content: attr(data-etiqueta);
                display: block;
                color: #52647a;
                font-size: 12px;
                font-weight: 700;
            }
        }

        @media print {
            html, body { background: white; }
            .hoja { margin: 0; padding: 0; }
            .cabecera, h2 { page-break-after: avoid; }
            .contenedor-tabla { overflow: visible; page-break-after: avoid; }
            .visor { display: none !important; }
        }
    </style>
</head>
<body>
    @unless($pdfServidor ?? false)
        <header class="visor" aria-label="Controles de impresión">
            <div class="visor-contenido">
                <div>
                    <p class="visor-titulo">Vista previa de impresión</p>
                    <p class="visor-formato">Tamaño carta · 8.5 × 11 pulgadas</p>
                </div>
                <div class="visor-acciones">
                    @isset($urlDescarga)
                        <a class="visor-accion visor-descarga" href="{{ $urlDescarga }}">Descargar PDF</a>
                    @endisset
                    <button class="visor-accion" type="button" onclick="window.print()">Imprimir / Guardar PDF</button>
                </div>
            </div>
        </header>
    @endunless
    @php
        $vertical = $formato['distribucion'] === 'vertical';
        $columnaCantidad = array_search('Cantidad', $columnas, true);
    @endphp
    <main class="hoja" aria-labelledby="titulo-documento">
        <table class="cabecera" role="presentation">
            <tr>
                <td class="marca" style="width: {{ $formato['ancho_logo'] + 16 }}px">
                    <img src="{{ ($portatil ?? false) && ($formato['predeterminado'] ?? false) ? 'logo.png' : $formato['logo_uri'] }}"
                         width="{{ $formato['ancho_logo'] }}" alt="Logotipo">
                </td>
                <td>
                    <p class="empresa"><strong>{{ $formato['empresa'] }}</strong>@if($formato['departamento']) · {{ $formato['departamento'] }}@endif</p>
                    @if($formato['encabezado'])<p class="meta">{{ $formato['encabezado'] }}</p>@endif
                    <h1 id="titulo-documento">{{ $formato['titulo'] }}</h1>
                    <p class="meta referencia">{{ $referencia }}</p>
                </td>
            </tr>
        </table>
        @if($formato['introduccion'])<p class="texto">{{ $formato['introduccion'] }}</p>@endif
        <table @class(['datos', 'datos-verticales' => $vertical]) role="presentation">
            @foreach(array_chunk($datos, $vertical ? 1 : 3, true) as $grupoDatos)
                <tr>
                    @foreach($grupoDatos as $etiqueta => $valor)
                        <td><strong>{{ $etiqueta }}</strong>{{ filled($valor) ? $valor : '—' }}</td>
                    @endforeach
                </tr>
            @endforeach
        </table>
        <h2 id="titulo-productos">Productos</h2>
        <div class="contenedor-tabla" role="region" aria-labelledby="titulo-productos" tabindex="0">
            <table @class(['lineas', 'lineas-amplias' => count($columnas) > 3]) role="table">
                <thead>
                    <tr>
                        @foreach($columnas as $indice => $columna)
                            <th scope="col" @class(['cantidad' => $indice === $columnaCantidad])>{{ $columna }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($filas as $fila)
                        <tr>
                            @foreach($fila as $indice => $valor)
                                <td @class(['cantidad' => $indice === $columnaCantidad]) data-etiqueta="{{ $columnas[$indice] }}">{{ $valor ?? '—' }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($formato['condiciones'])<p class="texto condiciones">{{ $formato['condiciones'] }}</p>@endif
        @if($formato['aceptacion'])<p class="texto"><strong>{{ $formato['aceptacion'] }}</strong></p>@endif
        <table class="firmas" role="presentation">
            <tr>
                @foreach($firmantes as $indice => $firmante)
                    <td><div class="firma"><strong>{{ $formato['firma_'.($indice + 1)] }}</strong><p>{{ $firmante ?? '' }}</p></div></td>
                @endforeach
            </tr>
        </table>
        @if($formato['pie'])<p class="meta texto pie">{{ $formato['pie'] }}</p>@endif
    </main>
</body>
</html>

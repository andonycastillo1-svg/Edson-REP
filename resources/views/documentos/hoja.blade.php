<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $formato['titulo'] }}</title>
    <style>
        @page { size: letter; margin: 0.48in 0.55in; }
        * { box-sizing: border-box; }
        body { color: #172b4d; font: 10pt/1.28 Arial, "DejaVu Sans", sans-serif; margin: 0; }
        .hoja { max-width: 8in; margin: 18px auto; }
        .cabecera, .datos, .lineas, .firmas { width: 100%; border-collapse: collapse; }
        .cabecera { border-bottom: 2px solid #1d4f91; margin-bottom: 10px; }
        .cabecera td { padding: 4px 8px 8px 0; vertical-align: middle; }
        h1 { margin: 2px 0 0; font-size: 17pt; color: #10263f; }
        p { margin: 3px 0; overflow-wrap: anywhere; }
        .meta { color: #52647a; font-size: 9pt; }
        .datos { margin: 0 0 8px; }
        .datos td { width: 33.33%; vertical-align: top; padding: 5px 8px; border-bottom: 1px solid #d8e1ec; }
        .datos strong { display: block; color: #10263f; font-size: 8pt; }
        .lineas { margin-bottom: 8px; }
        .lineas th, .lineas td { border: 1px solid #d8e1ec; padding: 5px 7px; text-align: left; vertical-align: top; overflow-wrap: anywhere; }
        .lineas th { background: #eaf1f9; color: #10263f; font-size: 9pt; }
        .lineas thead { display: table-header-group; }
        .lineas tr { page-break-inside: avoid; }
        .texto { white-space: pre-line; overflow-wrap: anywhere; margin: 7px 0; }
        .condiciones { border-left: 2px solid #1d4f91; padding-left: 8px; font-size: 8.5pt; }
        .firmas { margin-top: 18px; page-break-inside: avoid; }
        .firmas td { width: 50%; padding: 14px 8px 1px 0; vertical-align: top; }
        .firma { border-top: 1px solid #52647a; padding-top: 4px; }
        @media screen and (max-width: 680px) { .hoja { margin: 12px 8px; } .contenedor-tabla { overflow-x: auto; } }
        @media print { .hoja { margin: 0; } .imprimir { display: none; } }
    </style>
</head>
<body>
    <main class="hoja">
        <table class="cabecera">
            <tr>
                <td style="width: {{ $formato['ancho_logo'] + 10 }}px"><img src="{{ ($portatil ?? false) && ($formato['predeterminado'] ?? false) ? 'logo.png' : $formato['logo_uri'] }}" width="{{ $formato['ancho_logo'] }}" alt="Logotipo"></td>
                <td>
                    <p><strong>{{ $formato['empresa'] }}</strong>@if($formato['departamento']) · {{ $formato['departamento'] }}@endif</p>
                    @if($formato['encabezado'])<p class="meta">{{ $formato['encabezado'] }}</p>@endif
                    <h1>{{ $formato['titulo'] }}</h1>
                    <p class="meta">{{ $referencia }}</p>
                </td>
            </tr>
        </table>
        @if($formato['introduccion'])<p class="texto">{{ $formato['introduccion'] }}</p>@endif
        <table class="datos">
            @foreach(array_chunk($datos, $formato['distribucion'] === 'vertical' ? 1 : 3, true) as $grupoDatos)
                <tr>@foreach($grupoDatos as $etiqueta => $valor)
                    <td><strong>{{ $etiqueta }}</strong>{{ filled($valor) ? $valor : '—' }}</td>
                @endforeach</tr>
            @endforeach
        </table>
        <div class="contenedor-tabla">
            <table class="lineas">
                <thead><tr>@foreach($columnas as $columna)<th scope="col">{{ $columna }}</th>@endforeach</tr></thead>
                <tbody>
                    @foreach($filas as $fila)
                        <tr>@foreach($fila as $valor)<td>{{ $valor ?? '—' }}</td>@endforeach</tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($formato['condiciones'])<p class="texto condiciones">{{ $formato['condiciones'] }}</p>@endif
        @if($formato['aceptacion'])<p class="texto"><strong>{{ $formato['aceptacion'] }}</strong></p>@endif
        <table class="firmas">
            <tr>
                @foreach($firmantes as $indice => $firmante)
                    <td><div class="firma"><strong>{{ $formato['firma_'.($indice + 1)] }}</strong><p>{{ $firmante ?? '' }}</p></div></td>
                @endforeach
            </tr>
        </table>
        @if($formato['pie'])<p class="meta texto">{{ $formato['pie'] }}</p>@endif
        @unless($pdfServidor ?? false)
            <p class="imprimir"><button type="button" onclick="window.print()">Imprimir o guardar como PDF</button></p>
        @endunless
    </main>
</body>
</html>

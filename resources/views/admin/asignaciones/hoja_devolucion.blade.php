@php
    $asignacion = $primerMovimiento->asignacion;
    $colaborador = $asignacion?->colaborador;
    $fecha = ($primerMovimiento->ocurrio_en ?? $primerMovimiento->created_at)?->format('d/m/Y H:i');
    $responsable = $primerMovimiento->user?->name ?? 'Usuario histórico';
    $logo = public_path('img/logo1.png');
    $faltantes = $movimientos->where('estado_devolucion', 'sin_retorno')->sum('cantidad');
    $total = $movimientos->sum('cantidad');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Acta de devolución de artículos</title>
    <style>
        @page { margin: 40px 45px; size: letter; }
        body { color: #172b4d; font-family: "DejaVu Sans", sans-serif; font-size: 10px; line-height: 1.45; }
        h1 { color: #10263f; font-size: 19px; margin: 0 0 4px; }
        p { margin: 0 0 4px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 7px; text-align: left; vertical-align: top; }
        .encabezado { border-bottom: 2px solid #1d4f91; margin-bottom: 16px; }
        .encabezado td { padding: 0 0 12px; vertical-align: middle; }
        .productos { margin-top: 14px; }
        .productos th { background: #eaf1f9; color: #10263f; }
        .productos td { border-bottom: 1px solid #d8e1ec; }
        .cantidad { text-align: right; width: 50px; }
        .muted { color: #52647a; }
        .firmas { margin-top: 30px; page-break-inside: avoid; }
        .firma { border-top: 1px solid #52647a; padding-top: 8px; }
        .resumen { border-bottom: 1px solid #d8e1ec; margin: 12px 0; padding: 8px 0; }
        .folio { font-size: 8px; color: #52647a; }
    </style>
</head>
<body>
<table class="encabezado">
    <tr>
        @if(is_file($logo))
            <td style="width: 78px"><img src="data:image/png;base64,{{ base64_encode(file_get_contents($logo)) }}" width="64" alt="GNS"></td>
        @endif
        <td>
            <h1>Acta de devolución de artículos</h1>
            <p>Grupo Net Solutions, S.A. · Recursos Humanos</p>
            <p class="folio">Referencia {{ $grupo }}</p>
        </td>
    </tr>
</table>

<table>
    <tr>
        <td><strong>Colaborador</strong><p>{{ $colaborador?->nombre ?? '—' }}</p></td>
        <td><strong>Código</strong><p>{{ $colaborador?->codigo ?? $asignacion?->colaborador_codigo ?? '—' }}</p></td>
        <td><strong>Región</strong><p>{{ $colaborador?->region?->nombre ?? '—' }}</p></td>
    </tr>
    <tr>
        <td><strong>Registró</strong><p>{{ $responsable }}</p></td>
        <td colspan="2"><strong>Fecha y hora</strong><p>{{ $fecha ?? '—' }}</p></td>
    </tr>
</table>

<table class="productos">
    <thead><tr><th>Producto</th><th>Bodega de origen</th><th>Condición</th><th class="cantidad">Cantidad</th></tr></thead>
    <tbody>
    @foreach($movimientos as $movimiento)
        @php($fila = $movimiento->asignacion)
        <tr>
            <td><strong>{{ $fila->producto?->nombre ?? $fila->producto_codigo }}</strong><p class="muted">{{ $fila->producto_codigo }}</p></td>
            <td>{{ $fila->bodega?->nombre ?? '—' }}</td>
            <td>{{ $movimiento->condicion_devolucion }}</td>
            <td class="cantidad">{{ $movimiento->cantidad }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<p class="resumen"><strong>Unidades verificadas {{ $total }}</strong> · Recuperadas {{ $total - $faltantes }} · No recuperadas {{ $faltantes }}</p>

<table class="firmas">
    <tr>
        <td style="width: 46%"><div class="firma">Firma de quien registra<p>{{ $responsable }}</p></div></td>
        <td></td>
        <td style="width: 46%"><div class="firma">Firma del colaborador<p>{{ $colaborador?->nombre ?? '—' }}</p></div></td>
    </tr>
</table>
</body>
</html>

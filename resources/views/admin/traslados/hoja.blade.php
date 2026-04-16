<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Hoja de Traslado #{{ $operacion->id }}</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 24px; color: #111; }
    .row { display:flex; justify-content:space-between; gap:16px; }
    .box { border:1px solid #ddd; border-radius:10px; padding:14px; }
    table { width:100%; border-collapse: collapse; margin-top: 14px; }
    th, td { border:1px solid #ddd; padding:10px; font-size: 13px; }
    th { background:#f5f5f5; text-align:left; }
    .muted { color:#666; font-size: 12px; }
    .title { font-size: 18px; font-weight: 700; margin-bottom: 4px; }
    .badge { display:inline-block; padding:4px 10px; border-radius:999px; font-size:12px; font-weight:700; border:1px solid #ddd; }
    @media print { button { display:none; } }
  </style>
</head>
<body>
  <div class="row">
    <div>
      <div class="title">Hoja de traslado</div>
      <div class="muted">Solicitud #{{ $operacion->id }} • {{ $operacion->created_at->format('d/m/Y H:i') }}</div>
    </div>
    <div class="badge">{{ $operacion->estado }}</div>
  </div>

  <div class="row" style="margin-top:14px;">
    <div class="box" style="flex:1;">
      <div><b>Origen:</b> {{ optional($operacion->bodegaOrigen)->nombre }}</div>
      <div class="muted">ID: {{ $operacion->bodega_origen_id }}</div>
    </div>
    <div class="box" style="flex:1;">
      <div><b>Destino:</b> {{ optional($operacion->bodegaDestino)->nombre }}</div>
      <div class="muted">ID: {{ $operacion->bodega_destino_id }}</div>
    </div>
  </div>

  <div class="box" style="margin-top:14px;">
    <div><b>Creado por:</b> {{ optional($operacion->creador)->name ?? '—' }}</div>
    @if($operacion->observacion)
      <div style="margin-top:8px;"><b>Observación:</b> {{ $operacion->observacion }}</div>
    @endif
  </div>

  <table>
    <thead>
      <tr>
        <th style="width:60%;">Producto</th>
        <th style="width:15%;">Código</th>
        <th style="width:25%;">Cantidad</th>
      </tr>
    </thead>
    <tbody>
      @foreach($operacion->detalles as $d)
        <tr>
          <td>{{ optional($d->producto)->nombre ?? '—' }}</td>
          <td>{{ $d->producto_codigo }}</td>
          <td>{{ $d->cantidad }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>

  <div class="row" style="margin-top:18px;">
    <div class="box" style="flex:1; height:70px;">
      <div class="muted">Firma entrega (origen)</div>
    </div>
    <div class="box" style="flex:1; height:70px;">
      <div class="muted">Firma recepción (destino)</div>
    </div>
  </div>

  <div style="margin-top:18px;">
    <button onclick="window.print()">Imprimir</button>
  </div>
</body>
</html>

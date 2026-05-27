<!doctype html><html><body>
<h2>Hoja de asignación de vehículo</h2>
<p>Fecha: {{ optional($asignacion->fecha_inicio)?->format('d/m/Y H:i') }}</p>
<p>Vehículo: {{ $asignacion->vehiculo_vin }} | Colaborador: {{ optional($asignacion->colaborador)->nombre }}</p>
<p>Estado inicial: {{ $asignacion->estado_inicial_vehiculo }}</p>
<p>Observaciones: {{ $asignacion->observaciones_asignacion }}</p>
<table border="1" width="100%"><tr><th>Producto</th><th>Cantidad</th><th>Tipo</th><th>Serial</th></tr>@foreach($asignacion->productos as $p)<tr><td>{{ optional($p->producto)->nombre ?? $p->producto_codigo }}</td><td>{{ $p->cantidad }}</td><td>{{ $p->tipo_control }}</td><td>{{ $p->serial }}</td></tr>@endforeach</table>
<p>Firma entrega: __________ Firma recibe: __________ Firma autoriza: __________</p>
</body></html>

<!doctype html><html><body>
<h2>Hoja de desasignación de vehículo</h2>
<p>Fecha: {{ optional($asignacion->fecha_fin)?->format('d/m/Y H:i') }}</p>
<p>Vehículo: {{ $asignacion->vehiculo_vin }} | Colaborador: {{ optional($asignacion->colaborador)->nombre }}</p>
<p>Estado final: {{ $asignacion->estado_final_vehiculo }}</p>
<p>Observaciones: {{ $asignacion->observaciones_desasignacion }}</p>
<p>Firma entrega: __________ Firma recibe: __________ Firma autoriza: __________</p>
</body></html>

<!doctype html><html><body>
<h2>Hoja de desasignación de vehículo</h2>
<p>Fecha: {{ optional($asignacion->fecha_fin)?->format('d/m/Y H:i') }}</p>
<p>Vehículo: {{ optional($asignacion->vehiculo)->marca }} {{ optional($asignacion->vehiculo)->placa }} | VIN: {{ $asignacion->vehiculo_vin }}</p>
<p>Colaborador: {{ optional($asignacion->colaborador)->nombre }} | Código: {{ $asignacion->colaborador_codigo }}</p>
<p>Estado final: {{ $asignacion->estado_final_vehiculo }}</p>
<p>Observaciones: {{ $asignacion->observaciones_desasignacion }}</p>
<p><strong>Nota:</strong> La desasignación cierra únicamente la relación vehículo-colaborador y no cierra automáticamente refacciones/productos instalados o activos en el vehículo.</p>
<p>Firma entrega: __________ Firma recibe: __________ Firma autoriza: __________</p>
</body></html>

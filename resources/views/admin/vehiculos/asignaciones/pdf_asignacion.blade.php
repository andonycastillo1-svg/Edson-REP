<!doctype html><html><body>
<h2>Hoja de asignación de vehículo</h2>
<p>Fecha: {{ optional($asignacion->fecha_inicio)?->format('d/m/Y H:i') }}</p>
<p>Vehículo: {{ optional($asignacion->vehiculo)->marca }} {{ optional($asignacion->vehiculo)->placa }} | VIN: {{ $asignacion->vehiculo_vin }}</p>
<p>Colaborador: {{ optional($asignacion->colaborador)->nombre }} | Código: {{ $asignacion->colaborador_codigo }}</p>
<p>Estado inicial: {{ $asignacion->estado_inicial_vehiculo }}</p>
<p>Observaciones: {{ $asignacion->observaciones_asignacion }}</p>
<p><strong>Nota:</strong> Este documento corresponde únicamente a la asignación del vehículo al colaborador. Las refacciones/productos del vehículo se administran en su módulo independiente.</p>
<p>Firma entrega: __________ Firma recibe: __________ Firma autoriza: __________</p>
</body></html>

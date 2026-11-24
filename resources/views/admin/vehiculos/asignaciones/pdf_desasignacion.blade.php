<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    @include('partials.documentos.estilos-ficha')
</head>
<body>
<header>
    <p>Grupo NetSolutions</p>
    <h1>Hoja de desasignación de vehículo</h1>
</header>
<p>Fecha: {{ optional($asignacion->fecha_fin)?->format('d/m/Y H:i') }}</p>
<p>Vehículo: {{ optional($asignacion->vehiculo)->marca }} {{ optional($asignacion->vehiculo)->placa }} | VIN: {{ $asignacion->vehiculo_vin }}</p>
<p>Colaborador: {{ optional($asignacion->colaborador)->nombre }} | Código: {{ $asignacion->colaborador_codigo }}</p>
<p>Estado final: {{ $asignacion->estado_final_vehiculo }}</p>
<p>Observaciones: {{ $asignacion->observaciones_desasignacion }}</p>
<p><strong>Nota:</strong> La desasignación cierra únicamente la relación vehículo-colaborador y no cierra automáticamente refacciones/productos instalados o activos en el vehículo.</p>
<p class="firmas">Firma entrega: __________ Firma recibe: __________ Firma autoriza: __________</p>
</body></html>

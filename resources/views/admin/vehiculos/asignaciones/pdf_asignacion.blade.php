<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    @include('partials.documentos.estilos-ficha')
</head>
<body>
<header>
    <p>Grupo NetSolutions</p>
    <h1>Hoja de asignación de vehículo</h1>
</header>
<p>Fecha: {{ optional($asignacion->fecha_inicio)?->format('d/m/Y H:i') }}</p>
<p>Vehículo: {{ optional($asignacion->vehiculo)->marca }} {{ optional($asignacion->vehiculo)->placa }} | VIN: {{ $asignacion->vehiculo_vin }}</p>
<p>Colaborador: {{ optional($asignacion->colaborador)->nombre }} | Código: {{ $asignacion->colaborador_codigo }}</p>
<p>Estado inicial: {{ $asignacion->estado_inicial_vehiculo }}</p>
<p>Observaciones: {{ $asignacion->observaciones_asignacion }}</p>
<p><strong>Nota:</strong> Este documento corresponde únicamente a la asignación del vehículo al colaborador. Las refacciones/productos del vehículo se administran en su módulo independiente.</p>
<p class="firmas">Firma entrega: __________ Firma recibe: __________ Firma autoriza: __________</p>
</body></html>

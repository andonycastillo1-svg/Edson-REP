@php
    $datos = [
        'Estado' => $operacion->estado,
        'Fecha de solicitud' => $operacion->created_at?->format('d/m/Y H:i'),
        'Origen' => $operacion->bodegaOrigen?->nombre,
        'Destino' => $operacion->bodegaDestino?->nombre,
        'Solicitó y entrega' => $operacion->creador?->name,
        'Autorizó la recepción' => $operacion->aprobador?->name,
        ...(in_array('recepcion', $formato['campos'], true) && $operacion->aprobado_en ? ['Fecha de aprobación' => $operacion->aprobado_en->format('d/m/Y H:i')] : []),
        ...(in_array('observaciones', $formato['campos'], true) && $operacion->observacion ? ['Observación' => $operacion->observacion] : []),
    ];
@endphp
@include('documentos.hoja', [
    'referencia' => 'Solicitud #'.$operacion->id,
    'columnas' => ['Código', 'Producto', 'Cantidad'],
    'filas' => $operacion->detalles->map(fn ($fila) => [$fila->producto_codigo, $fila->producto?->nombre, $fila->cantidad]),
    'firmantes' => [$operacion->creador?->name, $operacion->aprobador?->name],
])

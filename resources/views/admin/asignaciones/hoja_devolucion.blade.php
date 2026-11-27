@php
    $colaborador = $primerMovimiento->asignacion?->colaborador;
    $visible = $formato['campos'];
    $datos = [
        'Colaborador' => $colaborador?->nombre,
        'Código' => $colaborador?->codigo,
        ...(in_array('puesto', $visible, true) ? ['Puesto' => $colaborador?->puesto] : []),
        ...(in_array('region', $visible, true) ? ['Región' => $colaborador?->region?->nombre] : []),
        'Registró' => $primerMovimiento->user?->name ?? 'Usuario histórico',
        'Fecha y hora' => ($primerMovimiento->ocurrio_en ?? $primerMovimiento->created_at)?->format('d/m/Y H:i'),
    ];
@endphp
@include('documentos.hoja', [
    'referencia' => 'Devolución · '.$grupo,
    'columnas' => ['Código', 'Producto', 'Bodega de origen', 'Condición', 'Cantidad'],
    'filas' => $movimientos->map(fn ($fila) => [
        $fila->asignacion?->producto_codigo, $fila->asignacion?->producto?->nombre, $fila->asignacion?->bodega?->nombre,
        $fila->condicion_devolucion ?? $fila->detalle, $fila->cantidad,
    ]),
    'firmantes' => [$primerMovimiento->user?->name, $colaborador?->nombre],
    'pdfServidor' => true,
])

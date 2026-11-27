@php
    $primera = $asignaciones->first();
    $entrega = $primera->entrega;
    $visible = $formato['campos'];
    $vida = in_array('vida', $visible, true);
    $valor = in_array('valor', $visible, true);
    $datos = [
        'Colaborador' => $colaborador->nombre,
        'Código de identificación' => $colaborador->codigo,
        ...(in_array('puesto', $visible, true) ? ['Puesto' => $colaborador->puesto] : []),
        ...(in_array('region', $visible, true) ? ['Región' => $colaborador->region?->nombre] : []),
        'Bodega de salida' => $primera->bodega?->nombre ?? $bodegaAsignador,
        'Fecha de entrega' => ($entrega?->salida_en ?? $primera->fecha)?->format('d/m/Y H:i'),
        'Responsable de entrega' => $asignadorNombre,
        'Responsable de recepción' => $entrega && !$entrega->confirmada_en ? 'Recepción pendiente' : $colaborador->nombre,
        ...(in_array('recepcion', $visible, true) ? ['Fecha de recepción' => $entrega?->recibida_en?->format('d/m/Y H:i')] : []),
        ...($valor && $asignaciones->every(fn ($fila) => $fila->costo_unitario !== null) ? ['Valor histórico total' => 'Q '.number_format($total, 2)] : []),
        ...(in_array('observaciones', $visible, true) && $primera->observaciones ? ['Observaciones' => $primera->observaciones] : []),
    ];
    $columnas = ['Código', 'Producto', 'Cantidad', ...($vida ? ['Vida útil al entregar'] : []), ...($valor ? ['Costo unitario'] : [])];
    $filas = $asignaciones->map(fn ($fila) => [
        $fila->producto_codigo, $fila->producto?->nombre, $fila->cantidad,
        ...($vida ? [$fila->vida_util_disponible_segundos !== null ? floor($fila->vida_util_disponible_segundos / 86400).' días'
            : ($fila->vida_util_original_meses !== null ? $fila->vida_util_original_meses.' meses' : 'Desconocida')] : []),
        ...($valor ? [$fila->costo_unitario !== null ? 'Q '.number_format((float) $fila->costo_unitario, 2) : 'Desconocido'] : []),
    ]);
@endphp
@include('documentos.hoja', [
    'referencia' => 'Asignación · '.$grupo,
    'firmantes' => [$asignadorNombre, $colaborador->nombre],
])

<?php
// Genera las etiquetas que expone Edson-REP. No inicia Laravel ni abre una conexión.
require __DIR__.'/../../app/Sistema/Modulos.php';
require __DIR__.'/../../app/Sistema/CatalogoPermisos.php';

use App\Sistema\CatalogoPermisos;

$salida = [];
foreach (CatalogoPermisos::AMBITOS as $ambito => $nombre) {
    foreach (CatalogoPermisos::disponibles($ambito) as $clave) {
        if (preg_match('/^(vehiculos|refacciones)\./', $clave)) {
            continue;
        }
        $info = CatalogoPermisos::presentacion($clave);
        $etiqueta = $info['grupo'].' | '.$info['titulo'];
        $salida[$clave] ??= ['etiqueta' => $etiqueta, 'ambitos' => []];
        $salida[$clave]['ambitos'][] = $ambito;
    }
}
$cuentas = array_count_values(array_column($salida, 'etiqueta'));
foreach ($salida as $meta) {
    if ($cuentas[$meta['etiqueta']] > 1) {
        $sufijos[$meta['etiqueta']] = 0;
    }
}
foreach ($salida as $clave => $meta) {
    if (isset($sufijos[$meta['etiqueta']])) {
        $salida[$clave]['etiqueta'] .= ' (opción '.(++$sufijos[$meta['etiqueta']]).')';
    }
}
echo json_encode($salida, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;

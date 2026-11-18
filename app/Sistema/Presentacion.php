<?php

namespace App\Sistema;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class Presentacion
{
    public function __construct(private Ajustes $ajustes) {}

    public function tabla(string $modulo, array $config): array
    {
        [, , , $nativas] = Modulos::definicion($modulo);
        $esenciales = Modulos::esenciales($modulo);
        $columnas = [];
        foreach ($nativas as $clave => $etiqueta) {
            $ajustes = $config['columnas'][$clave] ?? [];
            $esencial = in_array($clave, $esenciales, true);
            $columnas[$clave] = [
                'etiqueta' => $ajustes['etiqueta'] ?? $etiqueta,
                'orden' => (int) ($ajustes['orden'] ?? count($columnas)),
                'esencial' => $esencial,
                'visible' => $esencial || (bool) ($ajustes['visible'] ?? true),
                'filtro' => (bool) ($ajustes['filtro'] ?? false),
                'buscar' => (bool) ($ajustes['buscar'] ?? ($config === [] && $esencial)),
            ];
        }

        return Arr::sort($columnas, fn ($dato) => $dato['orden']);
    }

    public function nativos(string $modulo): array
    {
        $config = $this->ajustes->leer('presentacion.'.$modulo, [])['formulario'] ?? [];
        $campos = [];
        foreach (Modulos::FORMULARIOS_NATIVOS[$modulo] ?? [] as $clave => [$etiqueta, $max]) {
            $datos = $config[$clave] ?? [];
            $campos[$clave] = [
                'etiqueta' => $datos['etiqueta'] ?? $etiqueta, 'max' => $max,
                'ayuda' => $datos['ayuda'] ?? '', 'visible' => (bool) ($datos['visible'] ?? true),
                'obligatorio' => (bool) ($datos['obligatorio'] ?? false), 'orden' => (int) ($datos['orden'] ?? 0),
            ];
        }

        return Arr::sort($campos, fn ($dato) => $dato['orden']);
    }

    public function validar(Request $request, string $modulo, bool $nuevo): void
    {
        $reglas = [];
        $nombres = [];
        foreach ($this->nativos($modulo) as $clave => $campo) {
            if (!$campo['visible']) {
                $reglas[$clave] = ['missing'];
                continue;
            }
            $reglas[$clave] = [
                $campo['obligatorio'] && ($nuevo || $request->has($clave)) ? 'required' : 'nullable',
                'string', 'max:'.$campo['max'],
            ];
            $nombres[$clave] = $campo['etiqueta'];
        }
        $request->validate($reglas, [], $nombres);
    }
}

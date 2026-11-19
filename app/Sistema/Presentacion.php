<?php

namespace App\Sistema;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class Presentacion
{
    public function __construct(private Ajustes $ajustes, private ValoresCampos $valores) {}

    public function tabla(string $modulo, array $config): array
    {
        [, , , $nativas] = Modulos::definicion($modulo);
        $esenciales = Modulos::esenciales($modulo);
        $columnas = [];
        foreach ($nativas as $clave => $etiqueta) {
            $ajustes = $config['columnas'][$clave] ?? [];
            $esencial = in_array($clave, $esenciales, true);
            $buscarPorDefecto = ($config === [] && $esencial)
                || ($modulo === 'colaboradores' && in_array($clave, ['codigo', 'nombre'], true));
            $columnas[$clave] = [
                'etiqueta' => $ajustes['etiqueta'] ?? $etiqueta,
                'orden' => (int) ($ajustes['orden'] ?? count($columnas)),
                'esencial' => $esencial,
                'visible' => $esencial || (bool) ($ajustes['visible'] ?? true),
                'filtro' => (bool) ($ajustes['filtro'] ?? false),
                'buscar' => (bool) ($ajustes['buscar'] ?? $buscarPorDefecto),
            ];
        }

        return Arr::sort($columnas, fn ($dato) => $dato['orden']);
    }

    public function personalizadas(string $modulo, User $usuario, ?string $superficie = null): Collection
    {
        $ajustes = $this->ajustes->leer('presentacion.'.$modulo, []);
        $campos = $this->valores->campos($modulo, $usuario, $superficie === 'tabla' ? null : $superficie);

        return $campos
            ->when($superficie === 'tabla', fn ($campos) => $campos->filter(
                fn (Campo $campo) => $ajustes['campos'][$campo->id]['visible'] ?? $campo->tabla
            ))
            ->sortBy(fn (Campo $campo) => $this->ordenPersonalizado($campo, $ajustes))->values();
    }

    public function ordenPersonalizado(Campo $campo, array $ajustes): int
    {
        return (int) ($ajustes['campos'][$campo->id]['orden'] ?? $campo->orden);
    }

    public function operativa(string $modulo, User $usuario, iterable $ids): array
    {
        $campos = $this->personalizadas($modulo, $usuario, 'tabla');

        return [
            'campos' => $campos,
            'valores' => $this->valores->cargar($campos, $ids),
            'filtros' => $this->personalizadas($modulo, $usuario, 'filtro'),
        ];
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

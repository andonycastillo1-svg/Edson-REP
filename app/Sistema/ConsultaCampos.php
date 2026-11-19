<?php

namespace App\Sistema;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ConsultaCampos
{
    public function __construct(private Presentacion $presentacion) {}

    public function filtrar(Builder|EloquentBuilder $consulta, Request $request, string $modulo, ?string $llave = null): void
    {
        [, $tabla, $id] = Modulos::definicion($modulo);
        $datos = $request->validate(['campo' => ['nullable', 'array'], 'campo.*' => ['nullable', 'string', 'max:190']]);
        $filtros = array_filter($datos['campo'] ?? [], fn ($valor) => $valor !== null);
        if ($filtros === []) {
            return;
        }

        $permitidos = $this->presentacion->personalizadas($modulo, $request->user(), 'filtro')->keyBy('id');
        $llave = $consulta->getGrammar()->wrap($llave ?? $tabla.'.'.$id);
        $driver = $consulta->getConnection()->getDriverName();
        $tipo = in_array($driver, ['mysql', 'mariadb'], true) ? 'CHAR' : 'TEXT';

        foreach ($filtros as $campoId => $valor) {
            $campo = $permitidos->get($campoId);
            if (!$campo) {
                throw ValidationException::withMessages(['campo' => 'El filtro solicitado no está disponible.']);
            }
            if (in_array($campo->tipo, ['lista', 'multiple'], true) && (!ctype_digit($valor) || !$campo->opciones->contains('id', (int) $valor))) {
                throw ValidationException::withMessages(['campo' => 'Selecciona una opción válida.']);
            }
            $consulta->whereExists(function (Builder $sub) use ($llave, $tipo, $campo, $valor) {
                $sub->selectRaw('1')->from('sistema_campo_valores AS v')
                    ->where('v.campo_id', $campo->id)->whereRaw("v.registro = CAST({$llave} AS {$tipo})");
                if ($campo->tipo === 'multiple') {
                    $sub->whereJsonContains('v.valor', (int) $valor);
                } else {
                    $sub->where('v.busqueda', $valor);
                }
            });
        }
    }

    public function ordenar(Builder|EloquentBuilder $consulta, Campo $campo, string $direccion, string $llave): void
    {
        $grammar = $consulta->getGrammar();
        $mysql = in_array($consulta->getConnection()->getDriverName(), ['mysql', 'mariadb'], true);
        $llave = $grammar->wrap($llave);
        $tipoRegistro = $mysql ? 'CHAR' : 'TEXT';
        $valores = $consulta->getConnection()->table('sistema_campo_valores AS campo_orden')
            ->where('campo_orden.campo_id', $campo->id)
            ->whereRaw("campo_orden.registro = CAST({$llave} AS {$tipoRegistro})")->limit(1);
        if ($campo->tipo === 'lista') {
            $valores->leftJoin('sistema_campo_opciones AS opcion_orden', function ($join) use ($tipoRegistro) {
                $join->on('opcion_orden.campo_id', 'campo_orden.campo_id')
                    ->whereRaw("CAST(opcion_orden.id AS {$tipoRegistro}) = campo_orden.busqueda");
            })->select('opcion_orden.etiqueta');
        } elseif (in_array($campo->tipo, ['entero', 'decimal', 'casilla', 'booleano'], true)) {
            $valores->selectRaw('CAST(campo_orden.busqueda AS DECIMAL(20, 4))');
        } else {
            $valores->select('campo_orden.busqueda');
        }

        $consulta->orderBy($valores, $direccion);
    }

    public function buscar(Builder|EloquentBuilder $consulta, ?string $texto, array $columnas): void
    {
        if ($columnas === [] || blank($texto)) {
            return;
        }

        $palabras = preg_split('/[\s\p{Z}]+/u', $texto, flags: PREG_SPLIT_NO_EMPTY) ?: [];
        $mysql = in_array($consulta->getConnection()->getDriverName(), ['mysql', 'mariadb'], true);
        $columnas = array_map($consulta->getGrammar()->wrap(...), $columnas);
        foreach ($palabras as $palabra) {
            $patron = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $palabra).'%';
            $consulta->where(function ($grupo) use ($columnas, $patron, $mysql) {
                foreach ($columnas as $columna) {
                    $expresion = $mysql ? "CONVERT({$columna} USING utf8mb4) COLLATE utf8mb4_unicode_ci" : $columna;
                    $grupo->orWhereRaw("{$expresion} LIKE ? ESCAPE '!'", [$patron]);
                }
            });
        }
    }
}

<?php

namespace App\Http\Controllers\Sistema;

use App\Http\Controllers\Controller;
use App\Services\DocumentoPrivado;
use App\Sistema\Ajustes;
use App\Sistema\Campo;
use App\Sistema\Modulos;
use App\Sistema\Permisos;
use App\Sistema\ValoresCampos;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DatosController extends Controller
{
    public function __construct(private Modulos $modulos, private ValoresCampos $valores, private Ajustes $ajustes, private Permisos $permisos) {}

    public function indice(Request $request)
    {
        abort_unless($this->permisos->instalado(), 503);
        $modulos = array_filter(Modulos::DEFINICIONES, fn ($m) => $this->modulos->consultar($request->user(), $m), ARRAY_FILTER_USE_KEY);

        return view('sistema.modulos', compact('modulos'));
    }

    public function lista(Request $request, string $modulo)
    {
        abort_unless($this->permisos->instalado(), 503);
        [$nombre, , $id, $nativas] = Modulos::definicion($modulo);
        $config = $this->ajustes->leer('presentacion.'.$modulo, []);
        $columnas = $this->columnas($nativas, $config);
        $campos = $this->valores->campos($modulo, $request->user(), 'tabla');
        $filtros = $this->valores->campos($modulo, $request->user(), 'filtro');
        $registros = $this->consulta($request, $modulo, $config, $filtros)
            ->select(array_keys($nativas))->orderBy($config['ordenar'] ?? $id, $config['direccion'] ?? 'desc')->orderBy($id)
            ->paginate($config['pagina'] ?? 20)->withQueryString();
        $valores = $this->valores->cargar($campos, $registros->pluck($id));
        $distribucion = $this->distribucion($columnas, $campos, $config);
        $puedeExportar = $this->permisos->permite($request->user(), $modulo.'.datos_exportar');
        $presentacion = $config['columnas'] ?? [];

        return view('sistema.datos', compact('nombre', 'modulo', 'id', 'columnas', 'campos', 'filtros', 'registros', 'valores', 'puedeExportar', 'presentacion', 'distribucion'));
    }

    public function ficha(Request $request, string $modulo, string $registro)
    {
        abort_unless($this->permisos->instalado(), 503);
        [$nombre, , $id] = Modulos::definicion($modulo);
        $fila = $this->modulos->registro($request->user(), $modulo, $registro);
        $registro = (string) $fila->{$id};
        $campos = $this->valores->campos($modulo, $request->user());
        $actuales = $this->valores->cargar($campos, [$registro])->get($registro) ?? collect();
        $editar = $this->modulos->editar($request->user(), $modulo, $fila);

        $catalogoHistorico = $this->valores->campos($modulo, $request->user(), historicos: true)->keyBy('id');
        $historial = DB::table('sistema_campo_historial')->where('registro', $registro)
            ->whereIn('campo_id', $catalogoHistorico->keys())->orderByDesc('id')->paginate(15)->withQueryString();
        $archivados = $catalogoHistorico->reject(fn (Campo $campo) => $campos->contains('id', $campo->id));
        $valoresArchivados = $this->valores->cargar($archivados, [$registro])->get($registro) ?? collect();
        return view('sistema.ficha', compact('nombre', 'modulo', 'registro', 'campos', 'actuales', 'editar', 'catalogoHistorico', 'historial', 'valoresArchivados'));
    }

    public function actualizar(Request $request, string $modulo, string $registro)
    {
        abort_unless($this->permisos->instalado(), 503);
        [, , $id] = Modulos::definicion($modulo);
        abort_if($id === 'id' && !ctype_digit($registro), 404);
        DB::transaction(function () use ($request, $modulo, $id, $registro) {
            $fila = $this->modulos->registros($request->user(), $modulo)->where($id, $registro)->lockForUpdate()->first() ?? abort(404);
            abort_unless($this->modulos->editar($request->user(), $modulo, $fila), 403);
            $clave = (string) $fila->{$id};
            $this->valores->guardar($modulo, $clave, $this->valores->preparar($request, $modulo, false, $clave));
        });

        return back()->with('ok', 'Campos actualizados.');
    }

    public function archivo(Request $request, string $modulo, string $registro, Campo $campo)
    {
        abort_unless($this->permisos->instalado(), 503);
        abort_unless($this->permisos->permite($request->user(), $modulo.'.datos_archivos'), 403);
        [, , $id] = Modulos::definicion($modulo);
        $fila = $this->modulos->registro($request->user(), $modulo, $registro);
        $registros = $this->valores->campos($modulo, $request->user(), historicos: true);
        abort_unless($campo->tipo === 'archivo' && $registros->contains('id', $campo->id), 404);
        $clave = (string) $fila->{$id};
        $version = $request->integer('version');
        if ($version) {
            $json = DB::table('sistema_campo_historial')->where(['id' => $version, 'registro' => $clave, 'campo_id' => $campo->id])->value('valor');
            $valor = $json === null ? null : json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } else {
            $valor = $this->valores->cargar(collect([$campo]), [$clave])->get($clave)?->get($campo->id);
        }
        abort_unless(is_array($valor) && str_starts_with($valor['ruta'] ?? '', 'campos-sistema/'), 404);

        return DocumentoPrivado::responder($valor['ruta'], $valor['nombre'] ?? null, true);
    }

    public function exportar(Request $request, string $modulo)
    {
        abort_unless($this->permisos->instalado(), 503);
        abort_unless($this->permisos->permite($request->user(), $modulo.'.datos_exportar'), 403);
        [, , $id, $nativas] = Modulos::definicion($modulo);
        $config = $this->ajustes->leer('presentacion.'.$modulo, []);
        $columnas = $this->columnas($nativas, $config);
        $campos = $this->valores->campos($modulo, $request->user(), 'exportacion');
        $filtros = $this->valores->campos($modulo, $request->user(), 'filtro');
        $q = $this->consulta($request, $modulo, $config, $filtros)->select(array_keys($nativas));

        $distribucion = $this->distribucion($columnas, $campos, $config);

        return response()->streamDownload(function () use ($id, $distribucion, $campos, $q) {
            $salida = fopen('php://output', 'w');
            fputcsv($salida, array_map($this->celda(...), array_column($distribucion, 'etiqueta')), escape: '');
            $q->chunkById(250, function (Collection $registros) use ($id, $distribucion, $campos, $salida) {
                $todos = $this->valores->cargar($campos, $registros->pluck($id));
                foreach ($registros as $registro) {
                    $valores = $todos->get((string) $registro->{$id}) ?? collect();
                    $lista = [];
                    foreach ($distribucion as $columna) {
                        $clave = $columna['clave'];
                        $lista[] = $this->celda($columna['campo'] ? $this->valores->mostrar($columna['campo'], $valores->get($clave)) : (string) $registro->{$clave});
                    }
                    fputcsv($salida, $lista, escape: '');
                }
            }, $id);
            fclose($salida);
        }, $modulo.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    private function distribucion(array $columnas, Collection $campos, array $config): array
    {
        $lista = [];
        foreach ($columnas as $clave => $etiqueta) {
            $lista[] = [
                'clave' => $clave, 'etiqueta' => $etiqueta, 'campo' => null,
                'orden' => $config['columnas'][$clave]['orden'] ?? count($lista),
            ];
        }
        foreach ($campos as $campo) {
            $lista[] = ['clave' => $campo->id, 'etiqueta' => $campo->etiqueta, 'campo' => $campo, 'orden' => $campo->orden];
        }
        usort($lista, fn ($a, $b) => $a['orden'] <=> $b['orden']);

        return $lista;
    }

    private function celda(string $texto): string
    {
        return preg_match('/^[\p{Z}\t\r\n]*[=+@-]/u', $texto) ? "'".$texto : $texto;
    }

    private function columnas(array $nativas, array $config): array
    {
        $cols = $config['columnas'] ?? [];
        return collect($nativas)->filter(fn ($v, $k) => (bool) ($cols[$k]['visible'] ?? true))
            ->sortBy(fn ($v, $k) => $cols[$k]['orden'] ?? 0)
            ->mapWithKeys(fn ($v, $k) => [$k => $cols[$k]['etiqueta'] ?? $v])->all();
    }

    private function consulta(Request $request, string $modulo, array $config, Collection $filtros): Builder
    {
        [, $tabla, $id, $nativas] = Modulos::definicion($modulo);
        $q = $this->modulos->registros($request->user(), $modulo);
        $input = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'nativo' => ['nullable', 'array'],
            'nativo.*' => ['nullable', 'string', 'max:190'],
            'campo' => ['nullable', 'array'],
            'campo.*' => ['nullable', 'string', 'max:190'],
        ]);
        $cols = $config['columnas'] ?? [];
        $buscables = array_filter(array_keys($nativas), fn ($col) => ($cols[$col]['visible'] ?? true) && ($cols[$col]['buscar'] ?? false));
        if (!empty($input['q']) && $buscables !== []) {
            $q->where(function (Builder $grupo) use ($input, $buscables) {
                foreach ($buscables as $col) {
                    $grupo->orWhere($col, 'like', '%'.$input['q'].'%');
                }
            });
        }
        foreach ($input['nativo'] ?? [] as $col => $valor) {
            if ($valor !== null) {
                abort_unless(isset($nativas[$col]) && ($cols[$col]['filtro'] ?? false) && ($cols[$col]['visible'] ?? true), 422);
                $q->where($col, $valor);
            }
        }
        foreach ($input['campo'] ?? [] as $campoId => $valor) {
            if ($valor === null) {
                continue;
            }
            $campo = $filtros->firstWhere('id', $campoId);
            if (!$campo) {
                throw ValidationException::withMessages(['campo' => 'El filtro solicitado no está disponible.']);
            }
            if ($campo->tipo === 'multiple' && (!ctype_digit($valor) || !$campo->opciones->contains('id', (int) $valor))) {
                throw ValidationException::withMessages(['campo' => 'Selecciona una opción válida.']);
            }
            $q->whereExists(function (Builder $sub) use ($tabla, $id, $campo, $valor) {
                $driver = DB::getDriverName();
                $registro = $driver === 'mysql' || $driver === 'mariadb'
                    ? "CAST({$tabla}.{$id} AS CHAR)" : "CAST({$tabla}.{$id} AS TEXT)";
                $sub->selectRaw('1')->from('sistema_campo_valores AS v')
                    ->where('v.campo_id', $campo->id)->whereRaw("v.registro = {$registro}");
                if ($campo->tipo === 'multiple') {
                    $sub->whereJsonContains('v.valor', (int) $valor);
                } else {
                    $sub->where('v.busqueda', $valor);
                }
            });
        }

        return $q;
    }
}

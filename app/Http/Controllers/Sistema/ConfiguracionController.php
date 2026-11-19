<?php

namespace App\Http\Controllers\Sistema;

use App\Http\Controllers\Controller;
use App\Models\Bodega;
use App\Models\Role;
use App\Models\User;
use App\Services\BodegaAccessService;
use App\Services\DocumentoPrivado;
use App\Sistema\Ajustes;
use App\Sistema\Bitacora;
use App\Sistema\Modulos;
use App\Sistema\Permisos;
use App\Sistema\Presentacion;
use App\Sistema\Seguridad;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ConfiguracionController extends Controller
{
    public function __construct(private Ajustes $ajustes, private Permisos $permisos) {}

    public function index()
    {
        return view('sistema.index');
    }

    public function parametros()
    {
        $general = $this->ajustes->general();
        $bodegas = Bodega::where('tipo', 'Principal')->orderBy('nombre')->get();

        return view('sistema.parametros', compact('general', 'bodegas'));
    }

    public function guardarParametros(Request $request, Seguridad $seguridad, BodegaAccessService $bodegas)
    {
        $data = $request->validate([
            'nombre' => ['nullable', 'string', 'max:100'],
            'empresa' => ['nullable', 'string', 'max:100'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'quitar_logo' => ['nullable', 'boolean'],
            'bodega_principal' => ['nullable', 'integer', Rule::exists('bodegas', 'id')->where('tipo', 'Principal')],
            'inactividad' => ['nullable', 'integer', 'between:5,120'],
            'stock_minimo' => ['nullable', 'integer', 'between:1,1000000'],
            'vida_util' => ['nullable', 'integer', 'between:1,1200'],
            'categorias' => ['nullable', 'string', 'max:2000'],
            'current_password' => ['nullable', 'string', 'max:255'],
        ]);
        $critico = array_key_exists('bodega_principal', $data) || array_key_exists('inactividad', $data);
        $logo = null;
        try {
            $cambio = function () use ($request, $data, $bodegas, &$logo) {
                $this->ajustes->refrescar('general');
                $actual = $this->ajustes->general();
                if (array_key_exists('bodega_principal', $data)) {
                    $destino = $data['bodega_principal'] ?? Bodega::where('tipo', 'Principal')->orderBy('id')->value('id');
                    if ((int) $destino !== (int) $bodegas->principal()?->id &&
                        $bodegas->tieneHistorialOperativo()) {
                        throw ValidationException::withMessages(['bodega_principal' => 'La bodega principal no se puede cambiar con operaciones históricas.']);
                    }
                }
                $general = array_replace($actual, Arr::except($data, ['current_password', 'logo', 'quitar_logo']));
                if (array_key_exists('categorias', $data)) {
                    $general['categorias'] = collect(preg_split('/\R/u', $data['categorias'] ?? ''))->map(fn ($c) => trim($c))->filter()
                        ->unique()->values()->all();
                    if (collect($general['categorias'])->contains(fn ($c) => mb_strlen($c) > 50)) {
                        throw ValidationException::withMessages(['categorias' => 'Cada categoría admite hasta 50 caracteres.']);
                    }
                }
                if ($request->hasFile('logo')) {
                    $general['logo'] = $logo = DocumentoPrivado::guardar($request->file('logo'), 'sistema-identidad');
                } elseif ($request->boolean('quitar_logo')) {
                    unset($general['logo']);
                }
                $this->ajustes->guardar('general', $general, 'parametros');
            };

            if ($critico) {
                $seguridad->cambiar($request->user(), $data['current_password'] ?? '', $cambio);
            } else {
                DB::transaction(function () use ($seguridad, $cambio) {
                    $seguridad->bloquear();
                    $cambio();
                });
            }
        } catch (\Throwable $e) {
            if ($logo !== null) {
                DocumentoPrivado::descartar($logo);
            }
            throw $e;
        }

        return back()->with('ok', 'Parámetros guardados.');
    }

    public function logo()
    {
        $ruta = $this->ajustes->general('logo');
        abort_unless(is_string($ruta) && str_starts_with($ruta, 'sistema-identidad/'), 404);

        return DocumentoPrivado::responder($ruta);
    }

    public function notificaciones(Request $request)
    {
        $opciones = array_keys(Ajustes::EVENTOS);
        $filtros = $request->validate(['evento' => ['nullable', 'string', Rule::in($opciones)]]);
        $evento = $filtros['evento'] ?? $opciones[0];
        $q = $request->string('q')->trim()->limit(100)->toString();
        $regla = $this->ajustes->leer('notificaciones', [])[$evento] ?? [];
        $envioActivo = $regla['activo'] ?? true;
        if ($request->old('evento_formulario') === $evento) {
            $regla = [
                'activo' => $request->old('activo', true),
                'usuarios' => (array) $request->old('usuarios', []),
                'roles' => (array) $request->old('roles', []),
            ];
        }
        $idsValidos = fn (array $ids) => array_filter($ids, fn ($id) => is_int($id) || (is_string($id) && ctype_digit($id)));
        $roles = Role::where('activo', true)->orWhereIn('id', $idsValidos($regla['roles'] ?? []))->orderBy('nombre')->get();
        $coincidencias = User::where('acceso_activo', true)
            ->when($q !== '', fn ($usuarios) => $usuarios->where('name', 'like', '%'.$q.'%'))
            ->orderBy('name')->limit(50)->get(['id', 'name', 'acceso_activo']);
        $usuarios = $coincidencias->merge(User::whereKey($idsValidos($regla['usuarios'] ?? []))->get(['id', 'name', 'acceso_activo']))->sortBy('name');

        return view('sistema.notificaciones', compact('roles', 'usuarios', 'regla', 'evento', 'envioActivo', 'q'));
    }

    public function guardarNotificaciones(Request $request, string $evento, Seguridad $seguridad)
    {
        abort_unless(isset(Ajustes::EVENTOS[$evento]), 404);
        $data = $request->validate([
            'activo' => ['required', 'boolean'],
            'roles' => ['nullable', 'array', 'max:50'], 'roles.*' => ['integer', 'distinct', 'exists:roles,id'],
            'usuarios' => ['nullable', 'array', 'max:200'], 'usuarios.*' => ['integer', 'distinct', 'exists:users,id'],
        ]);

        DB::transaction(function () use ($evento, $data, $seguridad) {
            $seguridad->bloquear();
            $this->ajustes->refrescar('notificaciones');
            $reglas = $this->ajustes->leer('notificaciones', []);
            $reglas[$evento] = [
                'activo' => (bool) $data['activo'],
                'roles' => array_map('intval', $data['roles'] ?? []),
                'usuarios' => array_map('intval', $data['usuarios'] ?? []),
            ];
            $this->ajustes->guardar('notificaciones', $reglas, 'notificacion_'.$evento);
        });

        return back()->with('ok', 'Regla de notificación guardada.');
    }

    public function auditoria(Request $request)
    {
        $datos = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'actor' => ['nullable', 'integer', 'min:0'], 'modulo' => ['nullable', 'string', 'max:40'],
            'accion' => ['nullable', 'string', 'max:80'], 'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d'],
        ]);
        if (isset($datos['desde'], $datos['hasta']) && $datos['desde'] > $datos['hasta']) {
            throw ValidationException::withMessages(['hasta' => 'La fecha final debe ser igual o posterior a la fecha inicial.']);
        }
        $alcance = DB::table('sistema_auditoria')
            ->when(!$this->permisos->esSuperadministrador($request->user()), fn ($q) => $q->where('modulo', '!=', 'seguridad'))
            ->when($request->user()->rolOperativo() !== 1, fn ($q) => $q->where('actor_id', $request->user()->id));
        $usuarios = (clone $alcance)->leftJoin('users', 'users.id', '=', 'sistema_auditoria.actor_id')
            ->select('sistema_auditoria.actor_id', 'users.name')->distinct()->orderBy('users.name')->get();
        $modulos = (clone $alcance)->distinct()->orderBy('modulo')->pluck('modulo');
        $acciones = (clone $alcance)->distinct()->orderBy('accion')->pluck('accion');
        $eventos = (clone $alcance)
            ->when($datos['modulo'] ?? null, fn ($q, $mod) => $q->where('modulo', $mod))
            ->when($datos['accion'] ?? null, fn ($q, $ac) => $q->where('accion', $ac))
            ->when($datos['desde'] ?? null, fn ($q, $d) => $q->where('created_at', '>=', $d.' 00:00:00'))
            ->when($datos['hasta'] ?? null, fn ($q, $d) => $q->where('created_at', '<', \Illuminate\Support\Carbon::parse($d)->addDay()->toDateString().' 00:00:00'));
        if (isset($datos['actor'])) {
            (int) $datos['actor'] === 0 ? $eventos->whereNull('actor_id') : $eventos->where('actor_id', $datos['actor']);
        }
        $actores = $usuarios->keyBy('actor_id');
        $texto = Str::lower(Str::ascii($datos['q'] ?? ''));
        foreach (preg_split('/\s+/', $texto, flags: PREG_SPLIT_NO_EMPTY) as $palabra) {
            $coincide = fn (string $etiqueta) => str_contains(Str::lower(Str::ascii($etiqueta)), $palabra);
            $personas = $usuarios->filter(fn ($actor) => $coincide(Bitacora::persona($actor)));
            $eventos->where(function ($grupo) use ($modulos, $acciones, $coincide, $personas) {
                $grupo->whereIn('modulo', $modulos->filter(fn ($modulo) => $coincide(Bitacora::modulo($modulo))))
                    ->orWhereIn('accion', $acciones->filter(fn ($accion) => $coincide(Bitacora::accion($accion))))
                    ->orWhereIntegerInRaw('actor_id', $personas->pluck('actor_id')->filter());
                if ($personas->contains(fn ($actor) => $actor->actor_id === null)) {
                    $grupo->orWhereNull('actor_id');
                }
            });
        }
        $eventos = $eventos->orderByDesc('id')->paginate(30)->withQueryString();

        return view('sistema.auditoria', compact('eventos', 'usuarios', 'actores', 'modulos', 'acciones', 'datos'));
    }

    public function presentacion(Request $request, Presentacion $catalogo)
    {
        $modulo = $request->string('modulo')->toString() ?: 'bodegas';
        $definicion = Modulos::definicion($modulo);
        $presentacion = $this->ajustes->leer('presentacion.'.$modulo, []);
        $columnas = $catalogo->tabla($modulo, $presentacion);
        $formularios = $catalogo->nativos($modulo);
        $nativos = Modulos::FORMULARIOS_NATIVOS[$modulo] ?? [];
        $desdeDatos = $request->query('origen') === 'datos';

        return view('sistema.presentacion', compact('modulo', 'definicion', 'presentacion', 'columnas', 'formularios', 'nativos', 'desdeDatos'));
    }

    public function guardarPresentacion(Request $request, string $modulo, Seguridad $seguridad)
    {
        [, , $id, $columnas] = Modulos::definicion($modulo);
        $datos = $request->validate([
            'restaurar' => ['nullable', 'boolean'],
            'columnas' => ['nullable', 'array'],
            'columnas.*.etiqueta' => ['nullable', 'string', 'max:100'],
            'columnas.*.visible' => ['required', 'boolean'],
            'columnas.*.orden' => ['required', 'integer', 'between:0,65000'],
            'columnas.*.filtro' => ['required', 'boolean'],
            'columnas.*.buscar' => ['required', 'boolean'],
            'formulario' => ['nullable', 'array'],
            'formulario.*.etiqueta' => ['nullable', 'string', 'max:100'],
            'formulario.*.ayuda' => ['nullable', 'string', 'max:500'],
            'formulario.*.visible' => ['required', 'boolean'],
            'formulario.*.obligatorio' => ['required', 'boolean'],
            'formulario.*.orden' => ['required', 'integer', 'between:0,65000'],
            'pagina' => ['nullable', 'integer', Rule::in([10, 15, 20, 30, 50, 100])],
            'ordenar' => ['nullable', Rule::in(array_keys($columnas))],
            'direccion' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);
        $nativos = Modulos::FORMULARIOS_NATIVOS[$modulo] ?? [];
        $cols = $datos['columnas'] ?? [];
        $form = $datos['formulario'] ?? [];
        if (array_diff(array_keys($cols), array_keys($columnas)) || array_diff(array_keys($form), array_keys($nativos))) {
            throw ValidationException::withMessages(['columnas' => 'La configuración menciona campos fuera del catálogo.']);
        }
        foreach ($form as $campo) {
            if ($campo['obligatorio'] && !$campo['visible']) {
                throw ValidationException::withMessages(['formulario' => 'Un campo obligatorio debe estar visible.']);
            }
        }
        foreach (Modulos::esenciales($modulo) as $columna) {
            if (isset($cols[$columna])) {
                $cols[$columna]['visible'] = true;
            }
        }
        $ordenar = $datos['ordenar'] ?? $id;
        $config = [
            'columnas' => $cols, 'formulario' => $form, 'pagina' => (int) ($datos['pagina'] ?? 20),
            'ordenar' => ($cols[$ordenar]['visible'] ?? true) ? $ordenar : $id, 'direccion' => $datos['direccion'] ?? 'desc',
        ];
        DB::transaction(function () use ($request, $modulo, $config, $seguridad) {
            $seguridad->bloquear();
            $clave = 'presentacion.'.$modulo;
            $this->ajustes->refrescar($clave);
            $this->ajustes->guardar($clave, $request->boolean('restaurar') ? null : $config, 'presentacion');
        });

        return back()->with('ok', $request->boolean('restaurar') ? 'Configuración predeterminada restaurada.' : 'Presentación guardada para todas las cuentas.');
    }
}

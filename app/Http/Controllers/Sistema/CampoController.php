<?php

namespace App\Http\Controllers\Sistema;

use App\Http\Controllers\Controller;
use App\Sistema\Auditoria;
use App\Sistema\Campo;
use App\Sistema\CatalogoPermisos;
use App\Sistema\Modulos;
use App\Sistema\Seguridad;
use App\Sistema\ValoresCampos;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CampoController extends Controller
{
    public function index(Request $request)
    {
        $modulo = $request->string('modulo')->toString();
        $campos = Campo::with('opciones')->when(array_key_exists($modulo, Modulos::DEFINICIONES), fn ($q) => $q->where('modulo', $modulo))
            ->orderBy('modulo')->orderBy('orden')->paginate(20)->withQueryString();

        return view('sistema.campos', compact('campos', 'modulo'));
    }

    public function formulario(?Campo $campo = null)
    {
        $permisos = collect(array_keys(CatalogoPermisos::AMBITOS))->flatMap(fn ($ambito) => CatalogoPermisos::disponibles($ambito))->unique()->sort();

        return view('sistema.campo', ['campo' => $campo?->load('opciones'), 'permisos' => $permisos]);
    }

    public function guardar(Request $request, ValoresCampos $valores, Auditoria $auditoria, Seguridad $seguridad, ?Campo $campo = null)
    {
        $catalogo = collect(array_keys(CatalogoPermisos::AMBITOS))->flatMap(fn ($ambito) => CatalogoPermisos::disponibles($ambito))->unique()->all();
        $data = $request->validate([
            'modulo' => ['required', Rule::in(array_keys(Modulos::DEFINICIONES))],
            'clave' => ['required', 'regex:/^[a-z][a-z0-9_]{1,59}$/', Rule::unique('sistema_campos', 'clave')->where('modulo', $request->input('modulo'))->ignore($campo?->id)],
            'etiqueta' => ['required', 'string', 'max:100'],
            'ayuda' => ['nullable', 'string', 'max:500'],
            'tipo' => ['required', Rule::in(array_keys(ValoresCampos::TIPOS))],
            'permiso_lectura' => ['nullable', Rule::in($catalogo)],
            'permiso_edicion' => ['nullable', Rule::in($catalogo)],
            'current_password' => ['nullable', 'string', 'max:255'],
            'orden' => ['required', 'integer', 'between:0,65000'],
            'activo' => ['required', 'boolean'], 'obligatorio' => ['required', 'boolean'], 'formulario' => ['required', 'boolean'],
            'tabla' => ['required', 'boolean'], 'filtro' => ['required', 'boolean'], 'exportacion' => ['required', 'boolean'],
            'predeterminado' => ['nullable', 'string', 'max:5000'],
            'opciones' => ['nullable', 'array', 'max:100'],
            'opciones.*.id' => ['nullable', 'integer', 'distinct'],
            'opciones.*.etiqueta' => ['nullable', 'string', 'max:100'],
            'opciones.*.orden' => ['nullable', 'integer', 'between:0,65000'],
            'opciones.*.activo' => ['required', 'boolean'],
        ]);
        if ($data['obligatorio'] && !$data['formulario']) {
            throw ValidationException::withMessages(['obligatorio' => 'Un campo obligatorio debe aparecer en el formulario.']);
        }
        if ($data['filtro'] && in_array($data['tipo'], ['archivo', 'texto_largo'], true)) {
            throw ValidationException::withMessages(['filtro' => 'Ese tipo no admite filtros.']);
        }
        if ($data['tipo'] === 'archivo' && isset($data['predeterminado'])) {
            throw ValidationException::withMessages(['predeterminado' => 'Los archivos se adjuntan en cada registro.']);
        }

        $esCambioDeAcceso = collect(['permiso_lectura', 'permiso_edicion'])->contains(
            fn ($key) => array_key_exists($key, $data) && $data[$key] !== $campo?->{$key}
        );
        $guardar = function () use ($campo, $data, $valores, $auditoria, $esCambioDeAcceso) {
            $datos = Arr::except($data, ['opciones', 'predeterminado', 'current_password']);
            if (!$esCambioDeAcceso) {
                $datos = Arr::except($datos, ['permiso_lectura', 'permiso_edicion']);
            }
            $actual = $campo ? Campo::whereKey($campo->id)->lockForUpdate()->firstOrFail() : new Campo;
            if ($actual->exists && ($actual->archivado_en || $actual->modulo !== $data['modulo']
                || $actual->clave !== $data['clave'] || $actual->tipo !== $data['tipo'])) {
                throw ValidationException::withMessages(['tipo' => 'Módulo, clave y tipo preservan la identidad de los registros. Los campos archivados son históricos.']);
            }
            $anterior = $actual->exists ? $actual->only(array_keys($datos)) : null;
            $actual->fill($datos)->save();
            if (in_array($actual->tipo, ['lista', 'multiple'], true)) {
                foreach ($data['opciones'] ?? [] as $opcion) {
                    if (empty($opcion['etiqueta'])) {
                        continue;
                    }
                    $opcionDatos = ['etiqueta' => $opcion['etiqueta'], 'orden' => $opcion['orden'] ?? 0, 'activo' => $opcion['activo']];
                    if (!empty($opcion['id'])) {
                        $existente = $actual->opciones()->whereKey($opcion['id'])->firstOrFail();
                        $antes = $existente->only(['etiqueta', 'orden', 'activo']);
                        $existente->update($opcionDatos);
                        if ($existente->wasChanged()) {
                            $auditoria->registrar('configuracion', 'opcion_campo', $actual->id, $antes, $existente->only(array_keys($antes)));
                        }
                    } else {
                        $actual->opciones()->create($opcionDatos);
                    }
                }
                $actual->load('opciones');
                if ($actual->opciones->where('activo', true)->isEmpty() && $actual->activo) {
                    throw ValidationException::withMessages(['opciones' => 'Agrega al menos una opción activa.']);
                }
            }
            $default = $data['predeterminado'] ?? null;
            if ($default !== null && $actual->tipo === 'multiple') {
                $default = array_map('trim', explode(',', $default));
            }
            $actual->predeterminado = $valores->validar($actual, $default);
            if ($actual->modulo === 'inventario' && $actual->obligatorio && $actual->predeterminado === null) {
                throw ValidationException::withMessages(['predeterminado' => 'El inventario también crea saldos automáticamente. Para exigir un valor, configura uno predeterminado.']);
            }
            $actual->save();
            $auditoria->registrar('configuracion', $anterior ? 'actualizar_campo' : 'crear_campo', $actual->id, $anterior, $actual->only(array_keys($datos)));

            return $actual;
        };
        $campo = $esCambioDeAcceso
            ? $seguridad->cambiar($request->user(), $data['current_password'] ?? '', $guardar)
            : DB::transaction($guardar);

        return redirect()->route('sistema.campos.editar', $campo)->with('ok', 'Campo guardado.');
    }

    public function archivar(Campo $campo, Auditoria $auditoria)
    {
        DB::transaction(function () use ($campo, $auditoria) {
            $actual = Campo::whereKey($campo->id)->lockForUpdate()->firstOrFail();
            abort_if($actual->archivado_en, 409);
            $anterior = $actual->activo;
            $actual->update(['activo' => false, 'archivado_en' => now()]);
            $auditoria->registrar('configuracion', 'archivar_campo', $actual->id, ['activo' => $anterior], ['activo' => false]);
        });

        return redirect()->route('sistema.campos')->with('ok', 'Campo archivado. Sus valores se conservan.');
    }
}

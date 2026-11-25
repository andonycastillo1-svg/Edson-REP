<?php

namespace App\Http\Controllers\Sistema;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\DelegacionOperativa;
use App\Models\Region;
use App\Services\RegionesCoordinacion;
use App\Models\Bodega;
use App\Models\User;
use App\Sistema\Auditoria;
use App\Sistema\CatalogoPermisos;
use App\Sistema\Permisos;
use App\Sistema\Seguridad;
use App\Sistema\ValoresCampos;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SeguridadController extends Controller
{
    public function __construct(private Seguridad $seguridad, private Permisos $permisos, private Auditoria $auditoria) {}

    public function index(Request $request)
    {
        $roles = Role::withCount('usuarios')->orderBy('nombre')->get();
        $q = $request->string('q')->trim()->limit(100)->toString();
        $usuarios = User::with('role')->when($q !== '', fn ($query) => $query->where(
            fn ($scope) => $scope->where('name', 'like', '%'.$q.'%')->orWhere('email', 'like', '%'.$q.'%')
        ))->orderBy('name')->paginate(20)->withQueryString();

        return view('sistema.seguridad', compact('roles', 'usuarios', 'q'));
    }

    public function rol(Role $rol)
    {
        $rol->loadCount('usuarios');
        $usuarioRol = $rol->usuarios()->select('id', 'name', 'acceso_activo')->orderBy('name')->paginate(15);
        $catalogo = CatalogoPermisos::disponibles($rol->ambito());
        $reglas = $this->permisos->reglasRol($rol);
        $efectivos = collect($catalogo)->mapWithKeys(fn ($key) => [$key => $this->permisos->resultadoRol($rol, $key)]);

        return view('sistema.rol', compact('rol', 'usuarioRol', 'catalogo', 'reglas', 'efectivos'));
    }

    public function crearRol(Request $request)
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:50', 'unique:roles,nombre'],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'ambito_operativo' => ['required', 'integer', Rule::in(array_keys(CatalogoPermisos::AMBITOS))],
            'copiar_rol' => ['nullable', 'integer', 'exists:roles,id'],
            'current_password' => ['required', 'string', 'max:255'],
        ]);

        $rol = $this->seguridad->cambiar($request->user(), $data['current_password'], function () use ($data) {
            $nuevo = Role::create(Arr::only($data, ['nombre', 'descripcion', 'ambito_operativo']));
            if (!empty($data['copiar_rol'])) {
                $base = Role::findOrFail($data['copiar_rol']);
                if ($base->ambito() !== $nuevo->ambito()) {
                    throw ValidationException::withMessages(['copiar_rol' => 'Solo se pueden copiar permisos del mismo ámbito.']);
                }
                foreach (CatalogoPermisos::disponibles($nuevo->ambito()) as $permiso) {
                    if ($this->permisos->resultadoRol($base, $permiso)['permitido']) {
                        $this->escribirRegla('rol', $nuevo->id, $permiso, true);
                    }
                }
            }

            $this->auditoria->registrar('seguridad', 'crear_rol', $nuevo->id, null, [
                'rol' => $nuevo->only(['nombre', 'ambito_operativo']), 'duplicado_desde' => $data['copiar_rol'] ?? null,
            ]);

            return $nuevo;
        });

        return redirect()->route('sistema.rol', $rol->id)->with('ok', 'Rol creado.');
    }

    public function actualizarRol(Request $request, Role $rol)
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:50', Rule::unique('roles', 'nombre')->ignore($rol->id)],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'activo' => ['required', 'boolean'],
            'reglas' => ['nullable', 'array'],
            'reglas.*' => ['required', Rule::in(['heredar', 'permitir', 'denegar'])],
            'current_password' => ['required', 'string', 'max:255'],
        ]);

        $this->seguridad->cambiar($request->user(), $data['current_password'], function () use ($rol, $data) {
            $rol = Role::findOrFail($rol->id);
            if (!$data['activo'] && DB::table('sistema_superadministradores')->where('activo', true)
                ->whereIn('user_id', $rol->usuarios()->select('id'))->exists()) {
                throw ValidationException::withMessages(['activo' => 'El rol contiene Superadministradores activos.']);
            }
            $antes = $rol->only(['nombre', 'descripcion', 'activo']);
            $rol->update(Arr::only($data, ['nombre', 'descripcion', 'activo']));
            $this->cambiarReglas('rol', $rol->id, $rol->ambito(), $data['reglas'] ?? [], $this->permisos->reglasRol($rol));
            $this->auditoria->registrar('seguridad', 'actualizar_rol', $rol->id, $antes, $rol->only(array_keys($antes)));
        });

        return back()->with('ok', 'Rol actualizado.');
    }

    public function usuario(User $usuario)
    {
        $catalogo = CatalogoPermisos::disponibles($usuario->role?->ambito() ?? 0);
        $reglas = $this->permisos->reglasUsuario($usuario);
        $rol = $usuario->role;
        $efectivos = collect($catalogo)->mapWithKeys(fn ($key) => [$key => $this->permisos->resultado($usuario, $key)]);
        $heredados = collect($catalogo)->mapWithKeys(fn ($key) => [$key => $rol?->activo === false
            ? ['permitido' => false, 'origen' => 'Rol inactivo'] : $this->permisos->resultadoRol($rol, $key)]);
        $super = $this->permisos->esSuperadministrador($usuario);
        $roles = Role::where('activo', true)->orderBy('nombre')->get()->filter(fn (Role $role) => isset(CatalogoPermisos::AMBITOS[$role->ambito()]));
        $bodegas = Bodega::orderBy('nombre')->get();
        $almacenistas = User::deAmbito(2)->orderBy('name')->get(['id', 'name']);
        $almacenistaId = $usuario->almacenistasAsignados()->value('users.id');

        $regiones = Region::where('activa', true)->orderBy('nombre')->get();
        $regionesOperativas = $usuario->regionesOperativas()->pluck('regiones.id')->all();
        $regionesAdicionales = DB::table('region_coordinador_operativo')->where('user_id', $usuario->id)->pluck('region_id')->all();
        $bodegasOperativas = DB::table('bodega_usuario_operativa')->where('user_id', $usuario->id)->pluck('bodega_id')->all();
        $bodegaPrincipal = app(\App\Services\BodegaAccessService::class)->principal();
        $operaRequerimientos = $bodegaPrincipal && DB::table('bodega_usuario_operativa')->where('user_id', $usuario->id)
            ->where('bodega_id', $bodegaPrincipal->id)->where('opera_requerimientos', true)->exists();
        $titulares = User::where('acceso_activo', true)->whereKeyNot($usuario->id)->orderBy('name')->get(['id', 'name']);
        $delegaciones = DelegacionOperativa::with('titular', 'autorizador', 'regiones', 'bodegas')->where('sustituto_id', $usuario->id)
            ->latest('id')->paginate(10)->withQueryString();

        return view('sistema.usuario', compact('usuario', 'catalogo', 'reglas', 'rol', 'efectivos', 'heredados', 'super',
            'roles', 'bodegas', 'almacenistas', 'almacenistaId', 'regiones', 'regionesOperativas', 'regionesAdicionales', 'bodegasOperativas', 'bodegaPrincipal', 'operaRequerimientos', 'titulares', 'delegaciones'));
    }

    public function permisosUsuario(Request $request, User $usuario)
    {
        $data = $request->validate([
            'reglas' => ['nullable', 'array'],
            'reglas.*' => ['required', Rule::in(['heredar', 'permitir', 'denegar'])],
            'current_password' => ['required', 'string', 'max:255'],
        ]);

        $this->seguridad->cambiar($request->user(), $data['current_password'], function () use ($usuario, $data) {
            $usuario = User::findOrFail($usuario->id);
            $this->cambiarReglas('usuario', $usuario->id, $usuario->role?->ambito() ?? 0, $data['reglas'] ?? [], $this->permisos->reglasUsuario($usuario));
        });

        return redirect()->to(route('sistema.usuario', $usuario).'#permisos')->with('ok', 'Permisos individuales actualizados.');
    }

    public function relacionesUsuario(Request $request, User $usuario, RegionesCoordinacion $coordinacion)
    {
        $datos = $request->validate([
            'regiones' => ['nullable', 'array', 'list'], 'regiones.*' => ['required', 'integer', 'distinct', 'exists:regiones,id'],
            'coordinacion' => ['nullable', 'array', 'list'], 'coordinacion.*' => ['required', 'integer', 'distinct', 'exists:regiones,id'],
            'bodegas_operativas' => ['nullable', 'array', 'list'], 'bodegas_operativas.*' => ['required', 'integer', 'distinct', 'exists:bodegas,id'],
            'opera_requerimientos' => ['nullable', 'boolean'],
            'current_password' => ['required', 'string', 'max:255'],
        ]);
        $this->seguridad->cambiar($request->user(), $datos['current_password'], function (User $actor) use ($usuario, $coordinacion, $datos) {
            $usuario = User::whereKey($usuario->id)->lockForUpdate()->firstOrFail();
            $elegidas = $datos['regiones'] ?? [];
            $antes = $usuario->regionesOperativas()->pluck('regiones.id')->all();
            if (Region::whereKey($elegidas)->where('activa', false)->exists()) {
                throw ValidationException::withMessages(['regiones' => 'Selecciona únicamente regiones activas.']);
            }
            $inactivas = Region::whereKey($antes)->where('activa', false)->pluck('id')->all();
            $usuario->regionesOperativas()->sync([...$elegidas, ...$inactivas]);
            $this->auditoria->registrar('seguridad', 'actualizar_regiones_operativas', $usuario->id,
                ['regiones' => $antes], ['regiones' => [...$elegidas, ...$inactivas]]);

            $bodegas = DB::table('bodega_usuario_operativa')->where('user_id', $usuario->id);
            $previas = $bodegas->pluck('opera_requerimientos', 'bodega_id')->all();
            $nuevas = array_map('intval', $datos['bodegas_operativas'] ?? []);
            $principal = app(\App\Services\BodegaAccessService::class)->principal()?->id;
            $operaRequerimientos = (bool) ($datos['opera_requerimientos'] ?? false);
            if ($operaRequerimientos && (!$principal || !in_array((int) $principal, $nuevas, true))) {
                throw ValidationException::withMessages(['bodegas_operativas' => 'Selecciona la bodega central para operar requerimientos de central.']);
            }
            $bodegas->whereNotIn('bodega_id', $nuevas)->delete();
            $asignaciones = array_map(fn ($id) => ['user_id' => $usuario->id, 'bodega_id' => $id,
                'opera_requerimientos' => $operaRequerimientos && $id === (int) $principal], $nuevas);
            if ($asignaciones !== []) {
                DB::table('bodega_usuario_operativa')->upsert($asignaciones, ['bodega_id', 'user_id'], ['opera_requerimientos']);
            }
            $this->auditoria->registrar('seguridad', 'actualizar_bodegas_operativas', $usuario->id,
                ['bodegas' => $previas], ['bodegas' => $asignaciones]);

            if ($usuario->rolOperativo() === 5 || ($datos['coordinacion'] ?? []) !== []) {
                $coordinacion->guardarAdicionales($usuario, $datos['coordinacion'] ?? []);
            }
        });

        return redirect()->to(route('sistema.usuario', $usuario).'#relaciones')->with('ok', 'Relaciones actualizadas.');
    }

    public function estadoCuenta(Request $request, User $usuario)
    {
        $data = $request->validate([
            'activo' => ['required', 'boolean'], 'current_password' => ['required', 'string', 'max:255'],
        ]);
        $this->seguridad->cambiar($request->user(), $data['current_password'], fn () => $this->seguridad->activarCuenta(User::findOrFail($usuario->id), (bool) $data['activo']));

        return redirect()->to(route('sistema.usuario', $usuario).'#seguridad')->with('ok', 'Estado de la cuenta actualizado.');
    }

    public function actualizarContrasena(Request $request, User $usuario)
    {
        $data = $request->validate([
            'password' => ['required', 'confirmed', $this->seguridad->politicaContrasena()],
            'current_password' => ['required', 'string', 'max:255'],
        ]);

        $this->seguridad->cambiar($request->user(), $data['current_password'], function () use ($usuario, $data) {
            $cuenta = User::findOrFail($usuario->id);
            $cuenta->forceFill(['password' => Hash::make($data['password']), 'remember_token' => null])->save();
            $this->auditoria->registrar('seguridad', 'actualizar_contrasena', $cuenta->id, null, ['credencial_actualizada' => true]);
        });

        return redirect()->to(route('sistema.usuario', $usuario).'#seguridad')->with('ok', 'Contraseña de la cuenta actualizada.');
    }

    public function superadministrador(Request $request, User $usuario)
    {
        $data = $request->validate([
            'activo' => ['required', 'boolean'], 'current_password' => ['required', 'string', 'max:255'],
        ]);
        $this->seguridad->cambiar($request->user(), $data['current_password'], fn () => $this->seguridad->cambiarSuper(User::findOrFail($usuario->id), (bool) $data['activo']));

        return redirect()->route('dashboard')->with('ok', 'Acceso de Superadministrador actualizado.');
    }

    public function crearCuentaAdministrativa(Request $request, ValoresCampos $campos)
    {
        $usuario = $this->seguridad->crearCuentaAdministrativa($request, $campos);

        return redirect()->route('sistema.usuario', $usuario)->with('ok', 'Cuenta administrativa creada.');
    }

    public function actualizarCuenta(Request $request, User $usuario, ValoresCampos $campos)
    {
        $this->seguridad->actualizarCuenta($request, $usuario, $campos);

        return back()->with('ok', 'Cuenta actualizada.');
    }

    private function cambiarReglas(string $tipo, int $id, int $ambito, array $reglas, array $anteriores): void
    {
        if (array_diff(array_keys($reglas), CatalogoPermisos::disponibles($ambito))) {
            throw ValidationException::withMessages(['reglas' => 'Hay permisos desconocidos o fuera del ámbito.']);
        }
        foreach ($reglas as $permiso => $valor) {
            $nuevo = $valor === 'heredar' ? null : $valor === 'permitir';
            $anterior = $anteriores[$permiso] ?? null;
            if ($nuevo !== $anterior) {
                $this->escribirRegla($tipo, $id, $permiso, $nuevo);
                $this->auditoria->registrar('seguridad', 'permiso_'.$tipo, $id, [$permiso => $anterior], [$permiso => $nuevo]);
            }
        }
    }

    private function escribirRegla(string $tipo, int $id, string $permiso, ?bool $permitido): void
    {
        [$tabla, $llave] = $tipo === 'rol' ? ['sistema_permisos_rol', 'role_id'] : ['sistema_permisos_usuario', 'user_id'];
        $clave = [$llave => $id, 'permiso' => $permiso];
        if ($permitido === null) {
            DB::table($tabla)->where($clave)->delete();
        } else {
            DB::table($tabla)->upsert($clave + ['permitido' => $permitido], [$llave, 'permiso'], ['permitido']);
        }
    }
}

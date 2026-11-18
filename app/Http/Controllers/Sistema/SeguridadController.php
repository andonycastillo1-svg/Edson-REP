<?php

namespace App\Http\Controllers\Sistema;

use App\Http\Controllers\Controller;
use App\Models\Role;
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
use Illuminate\Validation\Rules\Password;
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
        $super = $this->permisos->esSuperadministrador($usuario);
        $roles = Role::where('activo', true)->orderBy('nombre')->get()->filter(fn (Role $role) => isset(CatalogoPermisos::AMBITOS[$role->ambito()]));
        $bodegas = Bodega::orderBy('nombre')->get();
        $almacenistas = User::deAmbito(2)->orderBy('name')->get(['id', 'name']);
        $almacenistaId = $usuario->almacenistasAsignados()->value('users.id');

        return view('sistema.usuario', compact('usuario', 'catalogo', 'reglas', 'rol', 'efectivos', 'super', 'roles', 'bodegas', 'almacenistas', 'almacenistaId'));
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

        return back()->with('ok', 'Permisos individuales actualizados.');
    }

    public function estadoCuenta(Request $request, User $usuario)
    {
        $data = $request->validate([
            'activo' => ['required', 'boolean'], 'current_password' => ['required', 'string', 'max:255'],
        ]);
        $this->seguridad->cambiar($request->user(), $data['current_password'], fn () => $this->seguridad->activarCuenta(User::findOrFail($usuario->id), (bool) $data['activo']));

        return back()->with('ok', 'Estado de la cuenta actualizado.');
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
        $data = $request->validate([
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(16)->letters()->numbers()->mixedCase()->symbols()],
            'current_password' => ['required', 'string', 'max:255'],
        ]);
        $valores = $campos->preparar($request, 'usuarios', true);
        $usuario = $this->seguridad->cambiar($request->user(), $data['current_password'], function (User $actor) use ($data, $campos, $valores) {
            $rol = Role::findOrFail($data['role_id']);
            abort_unless($rol->activo && $rol->ambito() === 1, 422);
            $usuario = User::create(Arr::only($data, ['role_id', 'name', 'email']) + [
                'password' => Hash::make($data['password']), 'created_by' => $actor->id,
            ]);
            $campos->guardar('usuarios', $usuario->id, $valores);
            $this->auditoria->registrar('seguridad', 'crear_cuenta_administrativa', $usuario->id, null, ['role_id' => $rol->id]);

            return $usuario;
        });

        return redirect()->route('sistema.usuario', $usuario)->with('ok', 'Cuenta administrativa creada.');
    }

    public function actualizarCuenta(Request $request, User $usuario)
    {
        $ambito = Role::find($request->integer('role_id'))?->ambito();
        $data = $request->validate([
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($usuario->id)],
            'bodega_id' => [Rule::requiredIf($ambito === 2), 'nullable', 'integer', 'exists:bodegas,id'],
            'almacenista_id' => [
                Rule::requiredIf($ambito === 3), 'nullable', 'integer',
                Rule::exists('users', 'id')->where(fn ($q) => $q->whereIn('id', User::deAmbito(2)->select('users.id'))),
            ],
            'password' => ['nullable', 'confirmed', Password::min(16)->letters()->numbers()->mixedCase()->symbols()],
            'current_password' => ['required', 'string', 'max:255'],
        ]);
        $this->seguridad->cambiar($request->user(), $data['current_password'], function () use ($usuario, $data) {
            $usuario = User::findOrFail($usuario->id);
            $rol = Role::findOrFail($data['role_id']);
            abort_unless($rol->activo && isset(CatalogoPermisos::AMBITOS[$rol->ambito()]), 422);
            if ($this->permisos->esSuperadministrador($usuario) && $rol->ambito() !== 1) {
                throw ValidationException::withMessages(['role_id' => 'Revoca primero la autorización de Superadministrador.']);
            }
            $antes = $usuario->only(['role_id', 'bodega_id']);
            $antes['almacenista_id'] = $usuario->almacenistasAsignados()->value('users.id');
            $cambio = Arr::only($data, ['role_id', 'name', 'email']) + [
                'bodega_id' => $rol->ambito() === 2 ? $data['bodega_id'] : null,
            ];
            if (!empty($data['password'])) {
                $cambio['password'] = Hash::make($data['password']);
                $cambio['remember_token'] = null;
            }
            if ((int) $usuario->role_id !== (int) $rol->id) {
                $reglas = $this->permisos->reglasUsuario($usuario);
                DB::table('sistema_permisos_usuario')->where('user_id', $usuario->id)->delete();
                if ($reglas !== []) {
                    $this->auditoria->registrar('seguridad', 'retirar_permisos_por_cambio_rol', $usuario->id, $reglas, []);
                }
            }
            $usuario->forceFill($cambio)->save();
            if ($rol->ambito() !== 2) {
                $usuario->supervisoresAsignados()->detach();
            }
            $almacenista = $rol->ambito() === 3 ? (int) $data['almacenista_id'] : null;
            $usuario->almacenistasAsignados()->sync($almacenista === null ? [] : [$almacenista]);
            $this->auditoria->registrar('seguridad', 'actualizar_acceso_cuenta', $usuario->id, $antes,
                $usuario->only(['role_id', 'bodega_id']) + ['almacenista_id' => $almacenista, 'credencial_actualizada' => isset($cambio['password'])]);
        });

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

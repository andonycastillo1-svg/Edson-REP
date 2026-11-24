<?php

namespace App\Sistema;

use App\Models\Role;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class Seguridad
{
    public function __construct(private Permisos $permisos, private Auditoria $auditoria) {}

    public function bloquear(): void
    {
        if ($this->permisos->instalado()) {
            DB::table('sistema_ajustes')->where('clave', 'seguridad.mutex')->lockForUpdate()->first();
        }
    }

    public function cambiar(User $actor, string $password, Closure $operacion): mixed
    {
        try {
            return DB::transaction(function () use ($actor, $password, $operacion) {
                $this->bloquear();
                $actor = User::findOrFail($actor->id);
                abort_unless($this->permisos->esSuperadministrador($actor), 403);
                if (!Hash::check($password, $actor->password)) {
                    throw ValidationException::withMessages(['current_password' => 'La contraseña es incorrecta.']);
                }

                return $operacion($actor);
            }, 3);
        } catch (ValidationException $e) {
            if (isset($e->errors()['current_password'])) {
                $this->auditoria->registrar('seguridad', 'confirmacion_invalida', $actor->id, resultado: 'denegado');
            }
            throw $e;
        }
    }

    public function tieneAccesoCritico(User $usuario): bool
    {
        if ($usuario->role?->ambito() === 1) {
            return true;
        }

        if (!$this->permisos->instalado()) {
            return false;
        }

        return DB::table('sistema_superadministradores')->where('user_id', $usuario->id)->where('activo', true)->exists()
            || collect(array_keys(CatalogoPermisos::CONFIGURACION))->contains(fn ($key) =>
                ($this->permisos->reglasUsuario($usuario)[$key] ?? false)
                || $this->permisos->resultadoRol($usuario->role, $key)['permitido']);
    }

    public function rolesAsignables(User $actor, bool $incluirAdministrativos = false): Collection
    {
        return Role::orderBy('nombre')->get()->filter(fn (Role $rol) => $this->rolAsignable($actor, $rol, $incluirAdministrativos));
    }

    public function rolAsignable(User $actor, Role $rol, bool $incluirAdministrativos = false): bool
    {
        if ($rol->activo === false || !isset(CatalogoPermisos::AMBITOS[$rol->ambito()])) {
            return false;
        }
        if (!$this->permisos->instalado()) {
            return $actor->rolOperativo() === 1 || in_array($rol->ambito(), $actor->rolOperativo() === 4 ? [2, 3, 5] : [2, 3], true);
        }
        if ($rol->ambito() === 1) {
            return $incluirAdministrativos && $this->rolAdministrativoPersonalizado($rol) && $this->permisos->esSuperadministrador($actor);
        }
        if ($actor->rolOperativo() !== 1 && !in_array($rol->ambito(), $actor->rolOperativo() === 4 ? [2, 3, 5] : [2, 3], true)) {
            return false;
        }

        return collect(array_keys(CatalogoPermisos::CONFIGURACION))->doesntContain(fn ($key) => $this->permisos->resultadoRol($rol, $key)['permitido']);
    }

    public function rolAdministrativoPersonalizado(?Role $rol): bool
    {
        return $rol?->ambito() === 1 && (int) $rol->id !== 1;
    }

    public function puedeEditarDesdeUsuarios(User $actor, User $usuario): bool
    {
        return $this->puedeGestionar($actor, $usuario)
            || ($this->rolAdministrativoPersonalizado($usuario->role)
                && $this->permisos->esSuperadministrador($actor)
                && !DB::table('sistema_superadministradores')->where('user_id', $usuario->id)->where('activo', true)->exists());
    }

    public function puedeGestionar(User $actor, User $usuario): bool
    {
        return ($actor->rolOperativo() === 1 || (int) $usuario->created_by === (int) $actor->id)
            && ($actor->rolOperativo() !== 4 || in_array($usuario->role?->ambito(), [2, 3, 5], true))
            && (!$this->permisos->instalado() || !$this->tieneAccesoCritico($usuario));
    }

    public function crearCuentaAdministrativa(Request $request, ValoresCampos $campos, bool $desdeUsuarios = false): User
    {
        if ($desdeUsuarios) {
            $this->limitarCambiosDeCuenta($request->user());
        }
        $data = $request->validate([
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', $this->politicaContrasena()],
            'current_password' => ['required', 'string', 'max:255'],
        ]);
        $valores = $campos->preparar($request, 'usuarios', true);
        return $this->cambiar($request->user(), $data['current_password'], function (User $actor) use ($data, $campos, $valores, $desdeUsuarios) {
            $rol = Role::findOrFail($data['role_id']);
            abort_unless($rol->activo && $rol->ambito() === 1, 422);
            abort_if($desdeUsuarios && (!$this->permisos->ruta($actor, 'admin.usuarios.store')
                || !$this->rolAsignable($actor, $rol, incluirAdministrativos: true)), 403);
            $usuario = User::create(Arr::only($data, ['role_id', 'name', 'email']) + [
                'password' => Hash::make($data['password']), 'created_by' => $actor->id,
            ]);
            $campos->guardar('usuarios', $usuario->id, $valores);
            $this->auditoria->registrar('seguridad', 'crear_cuenta_administrativa', $usuario->id, null, ['role_id' => $rol->id]);

            return $usuario;
        });
    }

    public function actualizarCuenta(Request $request, User $usuario, ValoresCampos $campos, bool $desdeUsuarios = false): void
    {
        if ($desdeUsuarios) {
            $this->limitarCambiosDeCuenta($request->user());
        }
        $ambito = Role::find($request->integer('role_id'))?->ambito();
        $data = $request->validate([
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($usuario->id)],
            'bodega_id' => [Rule::requiredIf($ambito === 2), 'nullable', 'integer', 'exists:bodegas,id'],
            'coordinador_id' => [
                Rule::prohibitedIf(fn () => $ambito !== 3 || ($desdeUsuarios && !$this->permisos->permite($request->user(), 'flujo.configurar'))),
                'nullable', 'integer',
                Rule::exists('users', 'id')->where(fn ($q) => $q->whereIn('id', User::deAmbito(5)->select('users.id'))),
            ],
            'almacenista_id' => [
                Rule::requiredIf($ambito === 3), 'nullable', 'integer',
                Rule::exists('users', 'id')->where(fn ($q) => $q->whereIn('id', User::deAmbito(2)->select('users.id'))),
            ],
            'password' => ['nullable', 'confirmed', $this->politicaContrasena()],
            'current_password' => ['required', 'string', 'max:255'],
        ]);
        $this->cambiar($request->user(), $data['current_password'], function (User $actor) use ($usuario, $data, $request, $campos, $desdeUsuarios) {
            $usuario = User::findOrFail($usuario->id);
            $rol = Role::findOrFail($data['role_id']);
            abort_unless($rol->activo && isset(CatalogoPermisos::AMBITOS[$rol->ambito()]), 422);
            if ($desdeUsuarios) {
                abort_unless($this->permisos->ruta($actor, 'admin.usuarios.update')
                    && $this->puedeEditarDesdeUsuarios($actor, $usuario)
                    && $this->rolAsignable($actor, $rol, incluirAdministrativos: true), 403);
                abort_if($actor->is($usuario) && (int) $usuario->role_id !== (int) $rol->id, 403);
            }
            if ($this->permisos->esSuperadministrador($usuario) && $rol->ambito() !== 1) {
                throw ValidationException::withMessages(['role_id' => 'Revoca primero la autorización de Superadministrador.']);
            }
            $antes = $usuario->only(['role_id', 'bodega_id']);
            $antes['almacenista_id'] = $usuario->almacenistasAsignados()->value('users.id');
            $antes['coordinador_id'] = $usuario->coordinador_id;
            $cambio = Arr::only($data, ['role_id', 'name', 'email']) + [
                'bodega_id' => $rol->ambito() === 2 ? $data['bodega_id'] : null,
                'coordinador_id' => $rol->ambito() === 3 ? ($data['coordinador_id'] ?? $usuario->coordinador_id) : null,
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
            $campos->guardar('usuarios', $usuario->id, $campos->preparar($request, 'usuarios', false, (string) $usuario->id));
            $this->auditoria->registrar('seguridad', 'actualizar_acceso_cuenta', $usuario->id, $antes,
                $usuario->only(['role_id', 'bodega_id']) + ['almacenista_id' => $almacenista, 'coordinador_id' => $usuario->coordinador_id, 'credencial_actualizada' => isset($cambio['password'])]);
        });
    }

    private function limitarCambiosDeCuenta(User $usuario): void
    {
        $clave = 'cuentas-administrativas:'.$usuario->id;
        abort_if(RateLimiter::tooManyAttempts($clave, 20), 429);
        RateLimiter::hit($clave);
    }

    public function politicaContrasena(): Password
    {
        return Password::min(16)->letters()->numbers()->mixedCase()->symbols();
    }

    public function superActivos(): int
    {
        return DB::table('sistema_superadministradores')->where('activo', true)
            ->whereIn('user_id', User::deAmbito(1)->select('users.id'))->count();
    }

    public function cambiarSuper(User $objetivo, bool $activo): void
    {
        $estado = DB::table('sistema_superadministradores')->where('user_id', $objetivo->id)->value('activo');
        if ($activo && (!$objetivo->acceso_activo || $objetivo->rolOperativo() !== 1)) {
            throw ValidationException::withMessages(['usuario' => 'Selecciona una cuenta administrativa activa.']);
        }
        if (!$activo && $estado && $this->permisos->esSuperadministrador($objetivo) && $this->superActivos() <= 1) {
            throw ValidationException::withMessages(['usuario' => 'Debe quedar un Superadministrador activo.']);
        }
        if ((bool) $estado === $activo) {
            return;
        }

        DB::table('sistema_superadministradores')->upsert([
            'user_id' => $objetivo->id, 'activo' => $activo, 'created_at' => now(), 'updated_at' => now(),
        ], ['user_id'], ['activo', 'updated_at']);
        $this->auditoria->registrar('seguridad', 'superadministrador', $objetivo->id, ['activo' => (bool) $estado], ['activo' => $activo]);
    }

    public function activarCuenta(User $usuario, bool $activo): void
    {
        if (!$activo && $this->permisos->esSuperadministrador($usuario)) {
            throw ValidationException::withMessages(['usuario' => 'Revoca el permiso de Superadministrador antes de desactivar la cuenta.']);
        }
        $anterior = (bool) $usuario->acceso_activo;
        $usuario->forceFill(['acceso_activo' => $activo])->save();
        $this->auditoria->registrar('seguridad', 'estado_cuenta', $usuario->id, ['activo' => $anterior], ['activo' => $activo]);
    }
}

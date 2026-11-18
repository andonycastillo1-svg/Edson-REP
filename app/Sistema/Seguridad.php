<?php

namespace App\Sistema;

use App\Models\Role;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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

    public function rolAsignable(User $actor, Role $rol): bool
    {
        if ($rol->activo === false || !isset(CatalogoPermisos::AMBITOS[$rol->ambito()])) {
            return false;
        }
        if (!$this->permisos->instalado()) {
            return $actor->rolOperativo() === 1 || in_array($rol->ambito(), [2, 3], true);
        }
        if ($rol->ambito() === 1 || ($actor->rolOperativo() !== 1 && !in_array($rol->ambito(), [2, 3], true))) {
            return false;
        }

        return collect(array_keys(CatalogoPermisos::CONFIGURACION))->doesntContain(fn ($key) => $this->permisos->resultadoRol($rol, $key)['permitido']);
    }

    public function puedeGestionar(User $actor, User $usuario): bool
    {
        return ($actor->rolOperativo() === 1 || (int) $usuario->created_by === (int) $actor->id)
            && (!$this->permisos->instalado() || !$this->tieneAccesoCritico($usuario));
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

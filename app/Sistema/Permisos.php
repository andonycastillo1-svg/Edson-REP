<?php

namespace App\Sistema;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Permisos
{
    private ?bool $instalado = null;

    public function instalado(): bool
    {
        return $this->instalado ??= Schema::hasTable('sistema_ajustes');
    }

    public function esSuperadministrador(User $user): bool
    {
        return $this->instalado() && $user->acceso_activo !== false && $user->rolOperativo() === 1
            && DB::table('sistema_superadministradores')->where('user_id', $user->id)->where('activo', true)->exists();
    }

    public function ruta(User $user, string $ruta): bool
    {
        if (!config('modulos.vehiculos') && (str_starts_with($ruta, 'admin.vehiculos.') || $ruta === 'rrhh.alertas.documento_refaccion')) {
            return false;
        }
        if ($operativo = CatalogoPermisos::permisoRutaInventario($ruta)) {
            return app(\App\Services\AccesoInventarioOperativo::class)->tiene($user, $operativo);
        }
        $permiso = CatalogoPermisos::rutas($user->rolOperativo())[$ruta] ?? null;

        return $permiso !== null && $this->permite($user, $permiso);
    }

    public function permite(User $user, string $permiso): bool
    {
        return $this->resultado($user, $permiso)['permitido'];
    }

    public function resultado(User $user, string $permiso): array
    {
        $ambito = $user->rolOperativo();
        if (!Modulos::habilitado(explode('.', $permiso, 2)[0])) {
            return ['permitido' => false, 'origen' => 'Módulo temporalmente deshabilitado'];
        }
        if ($user->acceso_activo === false || !in_array($permiso, CatalogoPermisos::disponibles($ambito), true)) {
            return ['permitido' => false, 'origen' => 'Fuera del ámbito'];
        }

        if ($this->instalado()) {
            if ($permiso === 'sistema.auditoria' && $this->esSuperadministrador($user)) {
                return ['permitido' => true, 'origen' => 'Auditoría de seguridad'];
            }
            $directo = $this->reglasUsuario($user)[$permiso] ?? null;
            if ($directo !== null) {
                return ['permitido' => $directo, 'origen' => 'Regla individual'];
            }
            if (isset(CatalogoPermisos::CONFIGURACION[$permiso]) && $this->esSuperadministrador($user)) {
                return ['permitido' => true, 'origen' => 'Superadministrador'];
            }
        }

        $anterior = CatalogoPermisos::permisoAnterior($permiso, $ambito);
        if ($anterior && !array_key_exists($permiso, $user->role ? $this->reglasRol($user->role) : [])) {
            return $this->resultado($user, $anterior);
        }

        return $this->resultadoRol($user->role, $permiso, $ambito);
    }

    public function resultadoRol(?Role $rol, string $permiso, ?int $ambito = null): array
    {
        $regla = $rol && $this->instalado() ? ($this->reglasRol($rol)[$permiso] ?? null) : null;
        if ($regla !== null) {
            return ['permitido' => $regla, 'origen' => 'Regla del rol'];
        }
        if ($anterior = CatalogoPermisos::permisoAnterior($permiso, $ambito ?? $rol?->ambito() ?? 0)) {
            return $this->resultadoRol($rol, $anterior, $ambito);
        }
        $heredado = in_array((int) $rol?->id, [1, 2, 3, 4], true)
            && !isset(CatalogoPermisos::ACCIONES_RESTRINGIDAS[$permiso])
            && !(($ambito ?? $rol->ambito()) === 1 && $permiso === 'traslados.aprobar')
            && in_array($permiso, [...CatalogoPermisos::rutas($ambito ?? $rol->ambito()),
                ...CatalogoPermisos::flujoPara($ambito ?? $rol->ambito())], true);

        return ['permitido' => $heredado, 'origen' => $heredado ? 'Acceso preexistente' : 'Denegación por defecto'];
    }

    public function reglasRol(Role $rol): array
    {
        return $rol->loadMissing('reglasSistema')->reglasSistema->mapWithKeys(fn ($regla) => [$regla->permiso => $regla->permitido])->all();
    }

    public function reglasUsuario(User $user): array
    {
        return $user->loadMissing('reglasSistema')->reglasSistema->mapWithKeys(fn ($regla) => [$regla->permiso => $regla->permitido])->all();
    }
}

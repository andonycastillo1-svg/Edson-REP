<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Sistema\Permisos;
use App\Sistema\Seguridad;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class InicializarSuperadministrador extends Command
{
    protected $signature = 'sistema:superadministrador {usuario : ID de una cuenta administrativa existente}';
    protected $description = 'Habilita una cuenta cuando no existe un Superadministrador activo';

    public function handle(Seguridad $seguridad, Permisos $permisos): int
    {
        if (!$permisos->instalado()) {
            $this->error('Aplica primero la migración de configuración.');

            return self::FAILURE;
        }
        if (!$this->input->isInteractive() || !$this->confirm('¿Confirmas la promoción explícita de esta cuenta?')) {
            return self::FAILURE;
        }

        $password = $this->secret('Contraseña actual de la cuenta');
        return DB::transaction(function () use ($seguridad, $password) {
            $seguridad->bloquear();
            $usuario = User::find($this->argument('usuario'));
            if ($seguridad->superActivos() !== 0 || !$usuario || !$usuario->acceso_activo
                || $usuario->rolOperativo() !== 1 || !is_string($password) || !Hash::check($password, $usuario->password)) {
                $this->error('No se pudo promover la cuenta. Verifica que sea elegible y que no exista un Superadministrador activo.');

                return self::FAILURE;
            }

            auth()->setUser($usuario);
            $seguridad->cambiarSuper($usuario, true);
            $this->info('Superadministrador habilitado.');

            return self::SUCCESS;
        }, 3);
    }
}

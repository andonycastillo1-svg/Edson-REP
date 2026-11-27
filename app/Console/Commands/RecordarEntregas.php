<?php

namespace App\Console\Commands;

use App\Models\EntregaInventario;
use App\Models\User;
use App\Notifications\AsignacionPendienteNotification;
use App\Services\AccesoCustodia;
use App\Sistema\Ajustes;
use App\Sistema\CatalogoPermisos;
use App\Sistema\Permisos;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecordarEntregas extends Command
{
    protected $signature = 'inventario:recordar-entregas';

    protected $description = 'Envía recordatorios de hoja firmada a las 24 horas de la salida';

    public function handle(AccesoCustodia $acceso, Ajustes $ajustes, Permisos $permisos): int
    {
        $regla = $ajustes->leer('notificaciones', [])['asignacion_pendiente'] ?? [];
        if (($regla['activo'] ?? true) === false) {
            return self::SUCCESS;
        }

        $corte = now()->subHours(24);
        $enviados = 0;
        EntregaInventario::where('salida_en', '<=', $corte)->whereDoesntHave('documentos',
            fn ($archivos) => $archivos->where('tipo_documento', 'asignacion_firmada'))->chunkById(100, function ($entregas) use ($acceso, $permisos, $regla, &$enviados) {
            foreach ($entregas as $candidata) {
                $enviados += DB::transaction(function () use ($candidata, $acceso, $permisos, $regla) {
                    $entrega = EntregaInventario::with('documentos', 'asignaciones.user.role', 'asignaciones.colaborador')
                        ->whereKey($candidata->id)->lockForUpdate()->firstOrFail();
                    $filas = $entrega->asignaciones;
                    if (!$entrega->faltaHojaFirmada() || $filas->isEmpty()) {
                        return 0;
                    }

                    $primera = $filas->first();
                    $autor = $primera->user;
                    $usuarios = collect([$autor])->merge($acceso->responsablesRegionales($primera, 'asignaciones.evidencias'));
                    if ($autor?->rolOperativo() === 2) {
                        $usuarios = $usuarios->merge($autor->supervisoresAsignados()->deAmbito(3)->with('role')->get());
                    }
                    if (!$autor || !$permisos->ruta($autor, CatalogoPermisos::prefijo($autor->rolOperativo()).'.asignaciones.documento_entrega')) {
                        $usuarios = $usuarios->merge(User::deAmbito(1)->with('role')->get());
                    }

                    $enviados = 0;
                    foreach ($usuarios->filter()->unique('id') as $usuario) {
                        $ruta = AccesoCustodia::rutaEntrega($usuario);
                        if (!$permisos->ruta($usuario, $ruta) || !$filas->every(fn ($fila) => $acceso->puedeVer($usuario, $fila))) {
                            continue;
                        }
                        $roles = $regla['roles'] ?? [];
                        $permitidos = $regla['usuarios'] ?? [];
                        if (($roles !== [] || $permitidos !== []) && !in_array((int) $usuario->role_id, $roles, true)
                            && !in_array((int) $usuario->id, $permitidos, true)) {
                            continue;
                        }
                        if (!DB::table('entrega_avisos')->insertOrIgnore([
                            'entrega_id' => $entrega->id, 'user_id' => $usuario->id, 'enviado_en' => now(),
                        ])) {
                            continue;
                        }
                        $usuario->notifyNow(new AsignacionPendienteNotification([
                            'titulo' => 'Hoja firmada faltante',
                            'mensaje' => ($primera->colaborador?->nombre ?? $primera->colaborador_codigo).' · Carga el PDF de la hoja firmada.',
                            'url' => route($ruta, $entrega),
                            'entidad_id' => $entrega->id,
                            'entidad_tipo' => class_basename($entrega),
                        ]));
                        $enviados++;
                    }

                    return $enviados;
                }, 3);
            }
        });
        $this->info("Recordatorios enviados: {$enviados}");

        return self::SUCCESS;
    }
}

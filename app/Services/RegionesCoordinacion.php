<?php

namespace App\Services;

use App\Models\Region;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegionesCoordinacion
{
    public function resolver(Collection $regiones, bool $bloquear = false): User
    {
        $filas = Region::whereKey($regiones->unique()->all())->orderBy('id');
        $filas = ($bloquear ? $filas->lockForUpdate() : $filas)->get();
        $usuarios = User::deAmbito(5)->whereKey($filas->pluck('coordinador_id')->filter()->unique()->all())->orderBy('id');
        $activos = ($bloquear ? $usuarios->lockForUpdate() : $usuarios)->get()->keyBy('id');
        $sinResponsable = $filas->filter(fn (Region $region) => !$region->activa || !$activos->has($region->coordinador_id));
        if ($filas->isEmpty() || $sinResponsable->isNotEmpty()) {
            throw ValidationException::withMessages(['inspecciones' => 'Configura un coordinador activo para cada región seleccionada. '.$sinResponsable->pluck('nombre')->implode(', ')]);
        }
        if ($activos->count() !== 1) {
            $grupos = $filas->groupBy('coordinador_id')->map(fn ($grupo, $id) => $activos->get($id)->name.' ('.$grupo->pluck('nombre')->implode(', ').')');
            throw ValidationException::withMessages(['inspecciones' => 'Separa los requerimientos de estos coordinadores. '.$grupos->implode(' · ')]);
        }

        return $activos->first();
    }

    public function asignar(User $actor, User $coordinador, array $ids, bool $reasignar): void
    {
        DB::transaction(function () use ($actor, $coordinador, $ids, $reasignar) {
            app(\App\Sistema\Seguridad::class)->bloquear();
            abort_unless(User::deAmbito(5)->whereKey($coordinador->id)->exists(), 404);
            $regiones = Region::where(fn ($q) => $q->where('coordinador_id', $coordinador->id)->orWhereIn('id', $ids))
                ->orderBy('id')->lockForUpdate()->get();
            foreach ($regiones as $region) {
                $elegida = in_array((int) $region->id, $ids, true);
                $nuevo = $elegida ? $coordinador->id : null;
                if ($elegida && !$region->activa) {
                    throw ValidationException::withMessages(['regiones' => 'Activa '.$region->nombre.' antes de asignarle un coordinador.']);
                }
                if ($elegida && $region->coordinador_id && (int) $region->coordinador_id !== (int) $coordinador->id && !$reasignar) {
                    throw ValidationException::withMessages(['regiones' => $region->nombre.' ya tiene responsable. Confirma la reasignación.']);
                }
                if (!$region->activa || $region->coordinador_id == $nuevo) {
                    continue;
                }
                DB::table('region_coordinador_historial')->insert([
                    'region_id' => $region->id, 'anterior_id' => $region->coordinador_id, 'coordinador_id' => $nuevo,
                    'user_id' => $actor->id, 'created_at' => now(),
                ]);
                $region->coordinador_id = $nuevo;
                $region->save();
            }
        }, 3);
    }
}

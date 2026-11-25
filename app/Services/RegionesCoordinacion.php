<?php

namespace App\Services;

use App\Models\Region;
use App\Models\User;
use App\Sistema\Permisos;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegionesCoordinacion
{
    public function opciones(array $ids, bool $bloquear = false): Collection
    {
        $consulta = Region::whereKey(array_unique($ids))->orderBy('id');
        $filas = ($bloquear ? $consulta->lockForUpdate() : $consulta)->get();
        $consulta = DB::table('region_coordinador_operativo')->whereIn('region_id', $filas->modelKeys())
            ->orderBy('region_id')->orderBy('user_id');
        $adicionales = ($bloquear ? $consulta->lockForUpdate() : $consulta)->get();
        $consulta = User::deAmbito(5)->whereKey($filas->pluck('coordinador_id')->merge($adicionales->pluck('user_id'))->filter()->unique()->all())->orderBy('id');
        $permisos = app(Permisos::class);
        $activos = ($bloquear ? $consulta->lockForUpdate() : $consulta)->with('role.reglasSistema', 'reglasSistema')->get()
            ->filter(fn (User $usuario) => $permisos->permite($usuario, 'flujo.consolidados') && $permisos->permite($usuario, 'flujo.revisar'))
            ->keyBy('id');
        $asignaciones = $adicionales->groupBy('region_id');

        return $filas->mapWithKeys(fn (Region $region) => [
            $region->id => !$region->activa ? collect() : collect([$region->coordinador_id])
                ->merge($asignaciones->get($region->id, collect())->pluck('user_id'))
                ->filter()->unique()->map(fn ($id) => $activos->get($id))->filter()->values(),
        ]);
    }

    public function resolver(Collection $regiones, bool $bloquear = false, ?int $eleccion = null): User
    {
        $opciones = $this->opciones($regiones->all(), $bloquear);
        if ($opciones->isEmpty() || $opciones->contains(fn ($candidatos) => $candidatos->isEmpty())) {
            $nombres = Region::whereKey($opciones->filter(fn ($candidatos) => $candidatos->isEmpty())->keys()->all())->pluck('nombre');
            throw ValidationException::withMessages(['inspecciones' => 'Configura un coordinador activo para cada región seleccionada. '.$nombres->implode(', ')]);
        }
        if ($eleccion !== null && $opciones->every(fn ($candidatos) => $candidatos->contains('id', $eleccion))) {
            return $opciones->first()->firstWhere('id', $eleccion);
        }
        if ($eleccion === null && $opciones->every(fn ($candidatos) => $candidatos->count() === 1)) {
            $unicos = $opciones->map(fn ($candidatos) => $candidatos->sole())->unique('id');
            if ($unicos->count() === 1) {
                return $unicos->sole();
            }
            throw ValidationException::withMessages(['inspecciones' => 'Separa los requerimientos de estos coordinadores. '.$unicos->pluck('name')->implode(' · ')]);
        }

        throw ValidationException::withMessages(['coordinador_id' => 'Selecciona un coordinador activo en todas las regiones del consolidado.']);
    }

    public function guardarAdicionales(User $usuario, array $ids): void
    {
        if (!User::deAmbito(5)->whereKey($usuario->id)->exists()) {
            throw ValidationException::withMessages(['coordinacion' => 'Selecciona una cuenta activa de coordinación.']);
        }
        $consulta = DB::table('region_coordinador_operativo')->where('user_id', $usuario->id);
        $antes = $consulta->orderBy('region_id')->pluck('region_id')->all();
        $regiones = Region::whereKey(array_unique([...$antes, ...$ids]))->orderBy('id')->lockForUpdate()->get();
        if ($regiones->whereIn('id', $ids)->where('activa', false)->isNotEmpty()) {
            throw ValidationException::withMessages(['coordinacion' => 'Selecciona únicamente regiones activas.']);
        }
        $ids = array_values(array_unique([...$ids, ...$regiones->where('activa', false)->whereIn('id', $antes)->modelKeys()]));
        $consulta->whereNotIn('region_id', $ids)->delete();
        $altas = array_diff($ids, $antes);
        if ($altas !== []) {
            DB::table('region_coordinador_operativo')->insert(array_map(fn ($id) => [
                'region_id' => $id, 'user_id' => $usuario->id, 'created_at' => now(), 'updated_at' => now(),
            ], $altas));
        }
        app(\App\Sistema\Auditoria::class)->registrar('seguridad', 'actualizar_coordinacion_adicional', $usuario->id,
            ['regiones' => $antes], ['regiones' => $ids]);
    }

    public function guardarUsuario(User $actor, User $usuario, array $datos, ?int $ambitoAnterior = null): void
    {
        $ambito = $usuario->rolOperativo();
        if ($ambitoAnterior === 5 && $ambito !== 5) {
            foreach ($usuario->regionesCoordinadas()->orderBy('id')->lockForUpdate()->get() as $region) {
                $this->cambiarResponsable($region, null, $actor);
            }
        }
        if (!isset($datos['configurar_regiones']) && !array_key_exists('regiones', $datos) && $ambito === $ambitoAnterior) {
            return;
        }
        $ids = array_map('intval', $datos['regiones'] ?? []);
        if ($ambito === 5) {
            if (isset($datos['configurar_regiones']) || array_key_exists('regiones', $datos)) {
                $this->asignar($actor, $usuario, $ids, (bool) ($datos['reasignar_regiones'] ?? false));
            }
        }
        if (!in_array($ambito, [2, 3], true)) {
            $ids = [];
        }
        if (Region::whereKey($ids)->where('activa', false)->exists()) {
            throw ValidationException::withMessages(['regiones' => 'Selecciona únicamente regiones activas.']);
        }
        $antes = $usuario->regionesOperativas()->orderBy('regiones.id')->pluck('regiones.id')->map(fn ($id) => (int) $id)->all();
        if ($ambito === $ambitoAnterior) {
            $ids = array_unique([...$ids, ...Region::whereKey($antes)->where('activa', false)->pluck('id')->map(fn ($id) => (int) $id)->all()]);
        }
        sort($ids);
        if ($antes !== $ids) {
            $usuario->regionesOperativas()->sync($ids);
            app(\App\Sistema\Auditoria::class)->registrar('seguridad', 'actualizar_regiones_operativas', $usuario->id,
                ['regiones' => $antes, 'ambito' => $ambitoAnterior], ['regiones' => $ids, 'ambito' => $ambito]);
        }
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
                if ($elegida && $region->coordinador_id && (int) $region->coordinador_id !== (int) $coordinador->id) {
                    $seguridad = app(\App\Sistema\Seguridad::class);
                    if ($actor->rolOperativo() === 4 && !$seguridad->puedeGestionar($actor, User::findOrFail($region->coordinador_id))) {
                        throw ValidationException::withMessages(['regiones' => 'Administración debe autorizar el cambio de responsable de '.$region->nombre.'.']);
                    }
                    if (!$reasignar) {
                        throw ValidationException::withMessages(['regiones' => $region->nombre.' ya tiene responsable. Confirma la reasignación.']);
                    }
                }
                if (!$region->activa || $region->coordinador_id == $nuevo) {
                    continue;
                }
                $this->cambiarResponsable($region, $nuevo, $actor);
            }
        }, 3);
    }

    private function cambiarResponsable(Region $region, ?int $nuevo, User $actor): void
    {
        DB::table('region_coordinador_historial')->insert([
            'region_id' => $region->id, 'anterior_id' => $region->coordinador_id, 'coordinador_id' => $nuevo,
            'user_id' => $actor->id, 'created_at' => now(),
        ]);
        $region->coordinador_id = $nuevo;
        $region->save();
    }
}

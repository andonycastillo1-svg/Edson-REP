<?php

namespace App\Services;

use App\Models\CorteRequerimiento;
use App\Models\Inspeccion;
use App\Models\User;
use App\Sistema\Auditoria;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as Registros;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CortesRequerimientos
{
    public function __construct(
        private FlujoAcceso $acceso,
        private RegionesCoordinacion $regiones,
        private ConsolidacionInspecciones $consolidacion,
        private Auditoria $auditoria,
    ) {}

    public function disponibles(User $usuario, CorteRequerimiento $corte, bool $anteriores): Builder
    {
        return $this->acceso->inspecciones($usuario)
            ->when($anteriores, fn ($q) => $q->where('fecha', '<', $corte->desde->toDateString()),
                fn ($q) => $q->whereBetween('fecha', [$corte->desde->toDateString(), $corte->hasta->toDateString().' 23:59:59']))
            ->where(fn ($q) => $q->whereDoesntHave('consolidados')
                ->orWhereIn('inspecciones.id', $corte->inspecciones()->select('inspecciones.id')));
    }

    public function guardarSeleccion(User $usuario, CorteRequerimiento $corte, array $visibles, array $periodo, array $anteriores): void
    {
        $ids = [...$periodo, ...$anteriores];
        if (count(array_unique($ids)) !== count($ids) || array_diff($ids, $visibles) !== []) {
            throw ValidationException::withMessages(['inspecciones' => 'Selecciona cada inspección una sola vez en su sección.']);
        }
        foreach ([false => $periodo, true => $anteriores] as $anterior => $grupo) {
            if ($grupo !== [] && $this->disponibles($usuario, $corte, (bool) $anterior)->whereKey($grupo)->whereDoesntHave('consolidados')->count() !== count($grupo)) {
                throw ValidationException::withMessages(['inspecciones' => 'Alguna inspección cambió de disponibilidad o pertenece a otro período. Desmarca las filas ocupadas y revisa la selección.']);
            }
        }

        $corte->inspecciones()->detach(array_diff($visibles, $ids));
        $seleccion = [];
        foreach ($ids as $id) {
            $seleccion[$id] = ['anterior' => in_array($id, $anteriores, true)];
        }
        $corte->inspecciones()->syncWithoutDetaching($seleccion);
    }

    public function vistaPrevia(CorteRequerimiento $corte): array
    {
        $inspecciones = $corte->inspecciones()->with('region', 'colaborador')->withExists('consolidados')
            ->orderBy('inspecciones.id')->get();

        return $this->agrupar($corte, $inspecciones);
    }

    public function confirmar(User $usuario, CorteRequerimiento $corte, string $firma): void
    {
        $ids = $corte->inspecciones()->orderBy('inspecciones.id')->pluck('inspecciones.id')->all();
        if ($ids === []) {
            throw ValidationException::withMessages(['inspecciones' => 'Selecciona al menos una inspección.']);
        }
        if ($this->acceso->inspecciones($usuario)->whereKey($ids)->count() !== count($ids)) {
            throw ValidationException::withMessages(['inspecciones' => 'La selección contiene inspecciones fuera de tu alcance.']);
        }
        $inspecciones = $this->consolidacion->bloquearDisponibles($ids)->load('region', 'colaborador');
        $resumen = $this->agrupar($corte, $inspecciones, bloquear: true);
        if ($resumen['alertas'] !== []) {
            throw ValidationException::withMessages(['inspecciones' => $resumen['alertas']]);
        }
        if (!hash_equals($resumen['firma'], $firma)) {
            throw ValidationException::withMessages(['inspecciones' => 'La selección o su coordinación cambió. Revisa la vista previa actualizada antes de confirmar.']);
        }

        $consolidados = [];
        foreach ($resumen['grupos'] as $grupo) {
            $coordinador = $grupo['coordinador'];
            $consolidado = $this->consolidacion->generar($usuario, $coordinador, $grupo['inspecciones'],
                Str::limit($corte->nombre.' · '.$coordinador->name, 160, ''), $corte->desde->toDateString(), $corte->hasta->toDateString(), $corte);
            $consolidados[] = $consolidado->id;
        }
        $corte->update(['estado' => 'confirmado']);
        $this->auditoria->registrar('flujo', 'confirmar_corte', $corte->id, ['estado' => 'borrador'],
            ['estado' => $corte->estado, 'consolidados' => $consolidados]);
    }

    private function agrupar(CorteRequerimiento $corte, Registros $inspecciones, bool $bloquear = false): array
    {
        $opciones = $this->regiones->opciones($inspecciones->pluck('region_id')->all(), $bloquear);
        $nombres = $inspecciones->mapWithKeys(fn ($fila) => [$fila->region_id => $fila->region?->nombre]);
        $elecciones = $corte->coordinadores_elegidos ?? [];
        $responsables = $opciones->map(fn ($candidatos, $id) => isset($elecciones[$id])
            ? $candidatos->firstWhere('id', $elecciones[$id])
            : ($candidatos->count() === 1 ? $candidatos->sole() : null));
        $alertas = [];
        $grupos = [];
        $ocupadas = $inspecciones->where('consolidados_exists', true);
        if ($ocupadas->isNotEmpty()) {
            $alertas[] = 'Ya se consolidaron estas inspecciones. Retíralas de la selección: #'.$ocupadas->pluck('id')->implode(', #').'.';
        }
        if ($inspecciones->contains(fn (Inspeccion $fila) => $fila->fecha->gt($corte->hasta))) {
            $alertas[] = 'La selección incluye inspecciones posteriores a la fecha final.';
        }
        foreach ($opciones as $id => $candidatos) {
            if ($candidatos->isEmpty()) {
                $alertas[] = 'Configura un coordinador activo para '.$nombres[$id].' o retira sus inspecciones.';
            } elseif (!$responsables->get($id)) {
                $alertas[] = 'Selecciona un coordinador para '.$nombres[$id].'.';
            }
        }
        foreach ($inspecciones->filter(fn ($fila) => $responsables->get($fila->region_id))
            ->groupBy(fn (Inspeccion $fila) => $responsables[$fila->region_id]->id)->sortKeys() as $grupo) {
            $coordinador = $responsables[$grupo->first()->region_id];
            foreach ($grupo->groupBy('colaborador_codigo') as $persona) {
                if ($persona->pluck('region_id')->unique()->count() > 1) {
                    $alertas[] = 'Selecciona una sola región para '.$persona->first()->colaborador_codigo.' en el consolidado de '.$coordinador->name.'.';
                }
            }
            $grupos[] = [
                'coordinador' => $coordinador,
                'inspecciones' => $grupo,
                'colaboradores' => $grupo->pluck('colaborador_codigo')->unique()->count(),
                'regiones' => $grupo->pluck('region.nombre')->unique()->sort()->values(),
            ];
        }

        $firma = hash('sha256', json_encode([
            $corte->id, $corte->desde->toDateString(), $corte->hasta->toDateString(),
            $inspecciones->map(fn (Inspeccion $fila) => [$fila->id, $fila->region_id, $responsables->get($fila->region_id)?->id])->values(),
        ], JSON_THROW_ON_ERROR));

        return [
            'inspecciones' => $inspecciones,
            'grupos' => $grupos,
            'opciones' => $opciones->filter(fn ($candidatos, $id) => $candidatos->count() > 1
                || ($candidatos->isNotEmpty() && !$responsables->get($id))),
            'regiones_nombres' => $nombres,
            'alertas' => $alertas,
            'firma' => $firma,
            'total' => $inspecciones->count(),
            'colaboradores' => $inspecciones->pluck('colaborador_codigo')->unique()->count(),
            'regiones' => $inspecciones->pluck('region_id')->unique()->count(),
            'coordinadores' => count($grupos),
            'anteriores' => $inspecciones->filter(fn (Inspeccion $fila) => $fila->fecha->lt($corte->desde))->count(),
        ];
    }
}

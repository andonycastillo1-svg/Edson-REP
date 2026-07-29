<?php

namespace App\Services;

use App\Models\AsignacionInventario;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AsignacionReplacementService
{
    public const MOTIVOS = [
        'desgaste_prematuro',
        'dano_accidental',
        'mal_uso',
        'perdida',
        'cambio_especificacion',
        'otro',
    ];

    public function activas(User $usuario, string $colaboradorCodigo, string $productoCodigo): Collection
    {
        return $this->queryActivas($usuario, $colaboradorCodigo, $productoCodigo)
            ->with(['producto', 'periodos' => fn ($query) => $query->whereNull('devuelto_en')])
            ->latest('fecha')
            ->get()
            ->map(fn (AsignacionInventario $asignacion) => $this->resumen($asignacion));
    }

    public function clasificarYValidar(User $usuario, array $item, string $colaboradorCodigo): array
    {
        $anteriores = $this->queryActivas($usuario, $colaboradorCodigo, $item['producto_codigo'])
            ->with(['producto', 'periodos' => fn ($query) => $query->whereNull('devuelto_en')])
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if ($anteriores->isEmpty()) {
            if (!empty($item['asignacion_anterior_id'])) {
                throw ValidationException::withMessages([
                    'items' => 'La asignación anterior indicada no está activa o no corresponde al colaborador y producto.',
                ]);
            }

            return $this->datosIniciales();
        }

        $anteriorId = (int) ($item['asignacion_anterior_id'] ?? 0);
        $anterior = $anteriores->get($anteriorId);
        if (!$anterior) {
            throw ValidationException::withMessages([
                'items' => 'Selecciona una asignación anterior activa válida para este colaborador y producto.',
            ]);
        }

        $resumen = $this->resumen($anterior);
        $modo = $item['modo_entrega'] ?? 'reposicion';
        $justificacion = trim((string) ($item['justificacion_reposicion'] ?? ''));

        if ($modo === 'adicional') {
            if ($justificacion === '') {
                throw ValidationException::withMessages(['items' => 'La entrega adicional requiere una justificación.']);
            }

            return [
                'tipo_entrega' => 'adicional',
                'asignacion_anterior_id' => $anterior->id,
                'solicitado_por' => null,
                'motivo_reposicion' => null,
                'justificacion_reposicion' => $justificacion,
                'vida_restante_anterior_segundos' => $resumen['vida_restante_segundos'],
            ];
        }

        if ($resumen['vida_restante_segundos'] === null || $resumen['vida_restante_segundos'] <= 0) {
            return [
                'tipo_entrega' => 'reposicion_normal',
                'asignacion_anterior_id' => $anterior->id,
                'solicitado_por' => null,
                'motivo_reposicion' => null,
                'justificacion_reposicion' => null,
                'vida_restante_anterior_segundos' => $resumen['vida_restante_segundos'],
            ];
        }

        $solicitadoPor = trim((string) ($item['solicitado_por'] ?? ''));
        $motivo = $item['motivo_reposicion'] ?? null;
        if ($solicitadoPor === '' || !in_array($motivo, self::MOTIVOS, true) || $justificacion === '') {
            throw ValidationException::withMessages([
                'items' => 'La reposición anticipada requiere solicitado por, motivo y justificación.',
            ]);
        }

        return [
            'tipo_entrega' => 'reposicion_anticipada',
            'asignacion_anterior_id' => $anterior->id,
            'solicitado_por' => $solicitadoPor,
            'motivo_reposicion' => $motivo,
            'justificacion_reposicion' => $justificacion,
            'vida_restante_anterior_segundos' => $resumen['vida_restante_segundos'],
        ];
    }

    private function queryActivas(User $usuario, string $colaboradorCodigo, string $productoCodigo): Builder
    {
        return AsignacionInventario::query()
            ->where('colaborador_codigo', $colaboradorCodigo)
            ->where('producto_codigo', $productoCodigo)
            ->where('estado', 'Activa')
            ->where('cantidad_asignada', '>', 0)
            ->when((int) $usuario->role_id === 2, fn (Builder $query) => $query->where('user_id', $usuario->id));
    }

    private function resumen(AsignacionInventario $asignacion): array
    {
        $ahora = now();
        $periodos = $asignacion->periodos;
        if ($periodos->isNotEmpty()) {
            $vidas = $periodos->map(function ($periodo) use ($ahora) {
                if ($periodo->vida_util_al_asignar_segundos === null) {
                    return null;
                }

                return max(0, (int) $periodo->vida_util_al_asignar_segundos - $periodo->asignado_en->diffInSeconds($ahora));
            });
            $vidaRestante = $vidas->contains(null) ? null : $vidas->min();
        } elseif ($asignacion->vida_util_restante_meses !== null) {
            $vidaInicial = max(0, (int) $asignacion->vida_util_restante_meses) * 30 * 86400;
            $vidaRestante = max(0, $vidaInicial - $asignacion->fecha->diffInSeconds($ahora));
        } else {
            $vidaRestante = null;
        }

        $vidaTotal = $asignacion->vida_util_original_meses ?? $asignacion->producto?->vida_util_meses;
        $utilizado = max(0, $asignacion->fecha->diffInSeconds($ahora));

        return [
            'id' => $asignacion->id,
            'producto' => $asignacion->producto?->descripcion ?: $asignacion->producto?->nombre,
            'producto_codigo' => $asignacion->producto_codigo,
            'fecha' => $asignacion->fecha?->toIso8601String(),
            'vida_total_meses' => $vidaTotal,
            'tiempo_utilizado_segundos' => $utilizado,
            'vida_restante_segundos' => $vidaRestante,
            'cantidad_activa' => (int) $asignacion->cantidad_asignada,
        ];
    }

    private function datosIniciales(): array
    {
        return [
            'tipo_entrega' => 'inicial',
            'asignacion_anterior_id' => null,
            'solicitado_por' => null,
            'motivo_reposicion' => null,
            'justificacion_reposicion' => null,
            'vida_restante_anterior_segundos' => null,
        ];
    }
}

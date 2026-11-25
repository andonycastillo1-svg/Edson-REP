<?php

namespace App\Services;

use App\Models\DelegacionOperativa;

final class AlcanceInventario
{
    public function __construct(
        public readonly ?array $bodegas,
        public readonly ?array $regiones,
        public readonly ?array $custodios = null,
        public readonly ?DelegacionOperativa $delegacion = null,
        public readonly bool $historico = false,
    ) {}

    public function contiene(array $bodegas, array $regiones = [], array $custodios = []): bool
    {
        return ($this->bodegas === null || array_diff($bodegas, $this->bodegas) === [])
            && ($this->regiones === null || (!in_array(null, $regiones, true) && array_diff($regiones, $this->regiones) === []))
            && ($this->custodios === null || (!in_array(null, $custodios, true) && array_diff($custodios, $this->custodios) === []));
    }

    public function limitarAsignaciones($consulta): void
    {
        if ($this->bodegas !== null) {
            $consulta->whereIn('asignaciones_inventarios.bodega_id', $this->bodegas);
        }
        if ($this->regiones !== null) {
            $consulta->whereIn('asignaciones_inventarios.colaborador_codigo', fn ($q) =>
                $q->select('codigo')->from('colaboradores')->whereIn('region_id', $this->regiones));
        }
        if ($this->custodios !== null) {
            $consulta->whereIn('asignaciones_inventarios.user_id', $this->custodios);
        }
    }
}

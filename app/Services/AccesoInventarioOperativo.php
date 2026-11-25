<?php

namespace App\Services;

use App\Models\Bodega;
use App\Models\Colaborador;
use App\Models\Operacion;
use App\Models\User;
use App\Sistema\Permisos;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AccesoInventarioOperativo
{
    public function __construct(
        private Permisos $permisos,
        private DelegacionesOperativas $delegaciones,
        private BodegaAccessService $bodegas,
    ) {}

    public static function prefijo(User $usuario): string
    {
        return $usuario->rolOperativo() === 1 ? 'admin' : 'operador';
    }

    public function tiene(User $usuario, string $permiso): bool
    {
        return $this->alcances($usuario, $permiso)->isNotEmpty();
    }

    public function propias(User $usuario, bool $bloquear = false): array
    {
        $consulta = DB::table('bodega_usuario_operativa')->where('user_id', $usuario->id)
            ->where('opera_inventario', true)->orderBy('bodega_id');

        return ($bloquear ? $consulta->lockForUpdate() : $consulta)->pluck('bodega_id')->map(fn ($id) => (int) $id)->all();
    }

    public function dependencias(string $permiso): array
    {
        return match ($permiso) {
            'flujo.importar', 'asignaciones.vincular' => ['asignaciones.crear'],
            'asignaciones.confirmar_recepcion' => ['asignaciones.consultar'],
            'compras.archivos', 'compras.datos_exportar' => ['compras.consultar'],
            'compras.vincular' => ['compras.crear'],
            'compras.conciliar' => ['compras.consultar', 'compras.vincular'],
            'traslados.archivos', 'traslados.exportar', 'traslados.datos_exportar' => ['traslados.consultar'],
            'asignaciones.datos_exportar', 'asignaciones.archivos', 'asignaciones.evidencias', 'asignaciones.exportar' => ['asignaciones.consultar'],
            default => [],
        };
    }

    public function permanentes(User $usuario, string $permiso, bool $bloquear = false): Collection
    {
        if (!collect([$permiso, ...$this->dependencias($permiso)])->every(fn ($clave) => $this->permisos->permite($usuario, $clave))) {
            return collect();
        }

        $grupo = explode('.', $permiso)[0];
        $central = $this->bodegas->principal()?->id;
        $regiones = $grupo === 'asignaciones' || $permiso === 'flujo.importar' ? $this->delegaciones->regionesTitular($usuario, $bloquear) : null;
        $propias = $this->propias($usuario, $bloquear);
        $alcances = collect();
        $decidir = in_array($permiso, ['traslados.aprobar', 'traslados.rechazar'], true);
        $centralObligatoria = $grupo === 'compras' || $permiso === 'flujo.importar';
        if ($permiso === 'asignaciones.vincular') {
            $propias = array_values(array_intersect($propias, [(int) $central]));
            if ($usuario->rolOperativo() === 2 && (int) $usuario->bodega_id !== (int) $central && $propias === []) {
                return collect();
            }
        }

        if ($centralObligatoria) {
            $propias = array_values(array_intersect($propias, [(int) $central]));
        }
        if ($propias !== [] && $regiones !== []) {
            $alcances->push(new AlcanceInventario($propias, $regiones));
        }

        // Se conservan los ámbitos operativos con los que se crearon las custodias y facturas anteriores.
        $ambito = $usuario->rolOperativo();
        if ($ambito === 1 && !$decidir) {
            $lecturaHistorica = in_array($permiso, ['compras.consultar', 'compras.archivos', 'compras.datos_exportar'], true);
            if (!$centralObligatoria || $central !== null || $lecturaHistorica) {
                $alcances->push(new AlcanceInventario($centralObligatoria && !$lecturaHistorica ? [(int) $central] : null, null, historico: true));
            }
        }
        if ($ambito === 2 && $usuario->bodega_id && (!$centralObligatoria || (int) $usuario->bodega_id === (int) $central)) {
            $bodegaLegada = $grupo === 'asignaciones' && in_array($permiso, ['asignaciones.consultar', 'asignaciones.exportar',
                'asignaciones.datos_exportar', 'asignaciones.archivos', 'asignaciones.evidencias'], true) ? null : [(int) $usuario->bodega_id];
            $porAutor = match ($grupo) {
                'asignaciones' => !in_array($permiso, ['asignaciones.crear', 'asignaciones.entregar_nuevo'], true),
                'traslados' => !in_array($permiso, ['traslados.crear', 'traslados.aprobar', 'traslados.rechazar'], true),
                default => false,
            };
            $alcances->push(new AlcanceInventario($bodegaLegada, $regiones ?: null, $porAutor ? [$usuario->id] : null, historico: true));
        }
        if ($ambito === 3 && in_array($permiso, ['asignaciones.consultar', 'asignaciones.exportar', 'asignaciones.evidencias', 'asignaciones.archivos'], true)) {
            $ids = $usuario->almacenistasAsignados()->pluck('users.id')->all();
            if ($ids !== []) {
                $alcances->push(new AlcanceInventario(null, null, $ids, historico: true));
            }
        }

        return $alcances;
    }

    public function alcances(User $usuario, string $permiso, bool $bloquear = false): Collection
    {
        if ($bloquear) {
            $usuario = $this->cuentaVigente($usuario->id);
        }
        $alcances = $this->permanentes($usuario, $permiso, $bloquear);
        foreach ($this->delegaciones->vigentes($usuario, $permiso, $bloquear) as $fila) {
            if (array_diff($this->dependencias($permiso), $fila->permisos) !== []
                || collect($this->dependencias($permiso))->contains(fn ($clave) => ($this->permisos->reglasUsuario($usuario)[$clave] ?? null) === false)) {
                continue;
            }
            $cuentaTitular = $bloquear ? $this->cuentaVigente($fila->titular_id) : $fila->titular;
            foreach ($this->permanentes($cuentaTitular, $permiso, $bloquear) as $titular) {
                $bodegas = array_values(array_intersect($fila->bodegas->modelKeys(), $titular->bodegas ?? $fila->bodegas->modelKeys()));
                $regiones = $titular->regiones;
                if (DelegacionesOperativas::requiereRegionInventario($permiso)) {
                    $regiones = array_values(array_intersect($this->delegaciones->regiones($fila, $bloquear), $regiones ?? $fila->regiones->modelKeys()));
                }
                if ($bodegas !== [] && $regiones !== []) {
                    $custodios = $titular->custodios === null ? null : [...$titular->custodios, $usuario->id];
                    $alcances->push(new AlcanceInventario($bodegas, $regiones, $custodios, $fila));
                }
            }
        }

        return $alcances;
    }

    private function cuentaVigente(int $id): User
    {
        $cuenta = User::whereKey($id)->lockForUpdate()->firstOrFail();
        $cuenta->setRelation('reglasSistema', $cuenta->reglasSistema()->sharedLock()->get());
        $rol = $cuenta->role()->sharedLock()->first();
        if ($rol !== null) {
            $rol->setRelation('reglasSistema', $rol->reglasSistema()->sharedLock()->get());
        }
        $cuenta->setRelation('role', $rol);

        return $cuenta;
    }

    public function buscar(User $usuario, string $permiso, array $bodegas, array $regiones = [], array $custodios = [], bool $bloquear = false): ?AlcanceInventario
    {
        return $this->alcances($usuario, $permiso, $bloquear)->first(fn ($ambito) => $ambito->contiene($bodegas, $regiones, $custodios));
    }

    public function autorizar(User $usuario, string $permiso, array $bodegas, array $regiones = [], array $custodios = [], bool $bloquear = false): AlcanceInventario
    {
        return $this->buscar($usuario, $permiso, $bodegas, $regiones, $custodios, $bloquear) ?? abort(403);
    }

    public function bodegasPara(User $usuario, string $permiso, array $regiones = []): array
    {
        return $this->alcances($usuario, $permiso)->filter(fn ($alcance) => $alcance->contiene([], $regiones))
            ->flatMap(fn ($alcance) => $alcance->bodegas ?? Bodega::pluck('id')->all())->unique()->values()->all();
    }

    public function limitarAsignaciones($consulta, User $usuario, string $permiso, array $regiones = []): void
    {
        $alcances = $this->alcances($usuario, $permiso)->filter(fn ($ambito) => $ambito->contiene([], $regiones));
        $consulta->where(function ($q) use ($alcances) {
            $q->whereRaw('1 = 0');
            foreach ($alcances as $alcance) {
                $q->orWhere(function ($rama) use ($alcance) {
                    $rama->whereRaw('1 = 1');
                    $alcance->limitarAsignaciones($rama);
                });
            }
        });
    }

    public function buscarCustodias(User $usuario, string $permiso, Collection $filas, bool $bloquear = false): ?AlcanceInventario
    {
        if ($filas->isEmpty()) {
            return null;
        }
        $regiones = Colaborador::whereKey($filas->pluck('colaborador_codigo')->unique()->all())->pluck('region_id', 'codigo');

        return $this->buscar($usuario, $permiso, $filas->pluck('bodega_id')->unique()->all(),
            $filas->map(fn ($fila) => $regiones[$fila->colaborador_codigo] ?? null)->unique()->all(), $filas->pluck('user_id')->unique()->all(), $bloquear);
    }

    public function autorizarCustodias(User $usuario, string $permiso, Collection $filas, bool $bloquear = false): AlcanceInventario
    {
        abort_if($filas->isEmpty(), 404);

        return $this->buscarCustodias($usuario, $permiso, $filas, $bloquear) ?? abort(403);
    }

    public function limitarTraslados($consulta, Collection $alcances): void
    {
        $consulta->where(function ($q) use ($alcances) {
            $q->whereRaw('1 = 0');
            foreach ($alcances as $alcance) {
                $q->orWhere(function ($rama) use ($alcance) {
                    if ($alcance->bodegas === null) {
                        $rama->whereRaw('1 = 1');

                        return;
                    }
                    $rama->whereIn('bodega_destino_id', $alcance->bodegas);
                    if ($alcance->custodios === null) {
                        $rama->orWhereIn('bodega_origen_id', $alcance->bodegas);
                    } elseif ($alcance->historico) {
                        $rama->orWhereIn('creado_por', $alcance->custodios);
                    } else {
                        $rama->orWhere(fn ($q) => $q->whereIn('bodega_origen_id', $alcance->bodegas)->whereIn('creado_por', $alcance->custodios));
                    }
                });
            }
        });
    }

    public function trasladoVisible(User $usuario, Operacion $operacion, string $permiso = 'traslados.consultar'): ?AlcanceInventario
    {
        return $this->alcances($usuario, $permiso)->first(fn ($alcance) =>
            $alcance->contiene([(int) $operacion->bodega_destino_id])
            || ($alcance->custodios === null && $alcance->contiene([(int) $operacion->bodega_origen_id]))
            || ($alcance->custodios !== null && in_array((int) $operacion->creado_por, $alcance->custodios, true)
                && ($alcance->historico || $alcance->contiene([(int) $operacion->bodega_origen_id]))));
    }

    public function decidirTraslado(User $usuario, Operacion $operacion, string $permiso, bool $bloquear = false): ?AlcanceInventario
    {
        if ((int) $operacion->creado_por === (int) $usuario->id) {
            return null;
        }

        return $this->alcances($usuario, $permiso, $bloquear)->first(fn ($alcance) =>
            $alcance->contiene([(int) $operacion->bodega_destino_id])
            && ($alcance->delegacion === null || (int) $alcance->delegacion->titular_id !== (int) $operacion->creado_por));
    }

    public function solicitudTraslado(User $usuario, int $origen, int $destino, bool $bloquear = false): AlcanceInventario
    {
        return $this->alcances($usuario, 'traslados.crear', $bloquear)->first(fn ($alcance) =>
            $alcance->contiene($alcance->historico ? [$origen] : [$origen, $destino])) ?? abort(403);
    }

    public function registrar(AlcanceInventario $alcance, string $permiso, int|string $registro): void
    {
        $this->delegaciones->registrar($alcance->delegacion, $permiso, $registro);
    }
}

<?php

namespace App\Services;

use App\Models\DelegacionOperativa;
use App\Models\Region;
use App\Models\User;
use App\Sistema\Auditoria;
use App\Sistema\Permisos;
use App\Sistema\CatalogoPermisos;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DelegacionesOperativas
{
    public const REGIONALES = [
        'flujo.inspecciones', 'flujo.capturar', 'flujo.exportar_inspecciones',
        'flujo.consolidados', 'flujo.exportar_consolidados', 'flujo.revisar',
        'flujo.ajustar', 'flujo.agregar_colaboradores', 'flujo.aprobar',
    ];

    public const BODEGAS = ['bodegas.consultar', 'bodegas.exportar'];

    public static function permisosBodega(): array
    {
        return [...self::BODEGAS, ...array_keys(CatalogoPermisos::INVENTARIO_OPERATIVO)];
    }

    public static function requiereRegionInventario(string $permiso): bool
    {
        return str_starts_with($permiso, 'asignaciones.') || $permiso === 'flujo.importar';
    }

    public function __construct(private Permisos $permisos, private Auditoria $auditoria) {}

    public function vigentes(User $sustituto, string $permiso, bool $bloquear = false): Collection
    {
        if (!$sustituto->rolOperativo() || $sustituto->acceso_activo === false
            || !in_array($permiso, [...self::REGIONALES, ...self::permisosBodega()], true)
            || ($this->permisos->reglasUsuario($sustituto)[$permiso] ?? null) === false) {
            return collect();
        }

        $vigentes = DelegacionOperativa::with('titular.role', 'regiones', 'bodegas')->where('sustituto_id', $sustituto->id)
            ->whereNull('revocada_en')->where('vigente_desde', '<=', now())->where('vigente_hasta', '>', now())->orderBy('id');

        return ($bloquear ? $vigentes->lockForUpdate() : $vigentes)->get()->filter(fn ($fila) => in_array($permiso, $fila->permisos, true)
                && $this->permisos->permite($fila->titular, $permiso));
    }

    public function regionesTitular(User $usuario, bool $bloquear = false): array
    {
        $consulta = Region::where('activa', true)->where(fn ($q) => $q->where('coordinador_id', $usuario->id)
            ->orWhereIn('id', $usuario->regionesOperativas()->select('regiones.id'))
            ->orWhereIn('id', DB::table('region_coordinador_operativo')->where('user_id', $usuario->id)->select('region_id')))
            ->orderBy('id');

        return ($bloquear ? $consulta->sharedLock() : $consulta)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public function regiones(DelegacionOperativa $fila, bool $bloquear = false): array
    {
        return array_values(array_intersect($fila->regiones->modelKeys(), $this->regionesTitular($fila->titular, $bloquear)));
    }

    public function bodegasTitular(User $usuario): array
    {
        return DB::table('bodega_usuario_operativa')->where('user_id', $usuario->id)->pluck('bodega_id')
            ->push($usuario->bodega_id)->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    public function bodegas(User $usuario, string $permiso): array
    {
        return $this->vigentes($usuario, $permiso)
            ->filter(fn ($fila) => in_array('bodegas.consultar', $fila->permisos, true)
                && $this->permisos->permite($fila->titular, 'bodegas.consultar'))
            ->flatMap(fn ($fila) => array_intersect($fila->bodegas->modelKeys(), $this->bodegasTitular($fila->titular)))
            ->unique()->values()->all();
    }

    public function validar(User $titular, User $sustituto, array $datos): void
    {
        if ($titular->is($sustituto) || !$sustituto->rolOperativo() || $sustituto->acceso_activo === false) {
            throw ValidationException::withMessages(['titular_id' => 'Selecciona dos cuentas activas y distintas.']);
        }
        foreach ($datos['permisos'] as $permiso) {
            if (!$this->permisos->permite($titular, $permiso)) {
                throw ValidationException::withMessages(['permisos' => 'El titular debe tener cada permiso de forma permanente.']);
            }
        }
        foreach ([
            'flujo.capturar' => 'flujo.inspecciones',
            'flujo.exportar_inspecciones' => 'flujo.inspecciones',
            'flujo.exportar_consolidados' => 'flujo.consolidados',
            'flujo.revisar' => 'flujo.consolidados', 'flujo.ajustar' => 'flujo.consolidados',
            'flujo.agregar_colaboradores' => 'flujo.consolidados', 'flujo.aprobar' => 'flujo.consolidados',
            'bodegas.exportar' => 'bodegas.consultar',
        ] as $permiso => $consulta) {
            if (in_array($permiso, $datos['permisos'], true) && !in_array($consulta, $datos['permisos'], true)) {
                throw ValidationException::withMessages(['permisos' => 'Incluye el permiso de consulta del módulo para delegar sus acciones.']);
            }
        }
        $inventario = app(AccesoInventarioOperativo::class);
        $permisosInventario = array_intersect($datos['permisos'], array_keys(CatalogoPermisos::INVENTARIO_OPERATIVO));
        foreach ($permisosInventario as $permiso) {
            if (array_diff($inventario->dependencias($permiso), $datos['permisos']) !== []) {
                throw ValidationException::withMessages(['permisos' => 'Incluye las funciones requeridas por la operación de inventario.']);
            }
            if (!$inventario->permanentes($titular, $permiso)->contains(fn ($alcance) => $alcance->contiene($datos['bodegas'] ?? [],
                self::requiereRegionInventario($permiso) ? ($datos['regiones'] ?? []) : []))) {
                throw ValidationException::withMessages(['bodegas' => 'El titular debe poder realizar cada operación en todo el alcance seleccionado.']);
            }
        }
        $regiones = $datos['regiones'] ?? [];
        $bodegas = $datos['bodegas'] ?? [];
        $requiereRegion = (bool) array_intersect($datos['permisos'], self::REGIONALES)
            || collect($permisosInventario)->contains(self::requiereRegionInventario(...));
        if ($requiereRegion !== ($regiones !== [])
            || array_diff($regiones, $this->regionesTitular($titular))) {
            throw ValidationException::withMessages(['regiones' => 'Selecciona únicamente regiones activas asignadas al titular para los permisos regionales.']);
        }
        if ((bool) array_intersect($datos['permisos'], self::permisosBodega()) !== ($bodegas !== [])
            || array_diff($bodegas, $this->bodegasTitular($titular))) {
            throw ValidationException::withMessages(['bodegas' => 'Selecciona la bodega asignada al titular para los permisos de bodega.']);
        }

        $desde = Carbon::parse($datos['vigente_desde'])->toDateTimeString();
        $hasta = Carbon::parse($datos['vigente_hasta'])->toDateTimeString();
        $cadenas = DelegacionOperativa::whereNull('revocada_en')
            ->where('vigente_desde', '<', $hasta)->where('vigente_hasta', '>', $desde)
            ->where(fn ($q) => $q->where('sustituto_id', $titular->id)->orWhere('titular_id', $sustituto->id));
        if ($cadenas->exists()) {
            throw ValidationException::withMessages(['titular_id' => 'Existe una delegación encadenada o recíproca en ese horario.']);
        }
    }

    public function registrar(?DelegacionOperativa $delegacion, string $permiso, int|string|null $registro = null): void
    {
        if ($delegacion !== null) {
            $this->auditoria->registrar('seguridad', 'usar_delegacion', $registro, null, [
                'delegacion_id' => $delegacion->id, 'titular_id' => $delegacion->titular_id, 'permiso' => $permiso,
            ]);
        }
    }
}

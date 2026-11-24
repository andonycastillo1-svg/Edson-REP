<?php

namespace App\Sistema;

use App\Models\AsignacionInventario;
use App\Models\User;
use App\Services\BodegaAccessService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class Modulos
{
    // El catálogo fija las tablas, identificadores y columnas utilizables en las consultas.
    public const DEFINICIONES = [
        'usuarios' => ['Usuarios', 'users', 'id', ['id' => 'ID', 'name' => 'Nombre', 'email' => 'Correo']],
        'colaboradores' => ['Colaboradores', 'colaboradores', 'codigo', ['codigo' => 'Código', 'nombre' => 'Nombre', 'puesto' => 'Puesto', 'estado' => 'Estado']],
        'productos' => ['Productos', 'productos', 'codigo', ['codigo' => 'Código', 'nombre' => 'Nombre', 'categoria' => 'Categoría', 'descripcion' => 'Descripción']],
        'bodegas' => ['Bodegas', 'bodegas', 'id', ['id' => 'ID', 'nombre' => 'Nombre', 'tipo' => 'Tipo', 'ubicacion' => 'Ubicación']],
        'compras' => ['Compras', 'compras', 'id', ['id' => 'ID', 'no_factura' => 'Factura', 'fecha_compra' => 'Fecha', 'proyecto' => 'Proyecto', 'total_factura' => 'Total']],
        'inventario' => ['Inventario', 'inventarios', 'id', ['id' => 'ID', 'producto_codigo' => 'Producto', 'bodega_id' => 'Bodega', 'stock_tipo' => 'Condición', 'cantidad' => 'Cantidad']],
        'asignaciones' => ['Asignaciones', 'asignaciones_inventarios', 'id', ['id' => 'ID', 'colaborador_codigo' => 'Colaborador', 'producto_codigo' => 'Producto', 'fecha' => 'Fecha', 'estado' => 'Estado']],
        'traslados' => ['Traslados', 'operaciones', 'id', ['id' => 'ID', 'bodega_origen_id' => 'Origen', 'bodega_destino_id' => 'Destino', 'estado' => 'Estado', 'observacion' => 'Observación']],
        'vehiculos' => ['Vehículos', 'vehiculos', 'vin', ['vin' => 'VIN', 'placa' => 'Placa', 'marca' => 'Marca', 'modelo' => 'Modelo', 'estado' => 'Estado']],
        'refacciones' => ['Refacciones', 'vehiculo_producto_asignaciones', 'id', ['id' => 'ID', 'vehiculo_vin' => 'Vehículo', 'producto_codigo' => 'Producto', 'cantidad' => 'Cantidad', 'estado' => 'Estado', 'observaciones' => 'Observaciones']],
    ];

    public const FORMULARIOS_NATIVOS = [
        'bodegas' => ['ubicacion' => ['Ubicación', 150]],
        'vehiculos' => ['marca' => ['Marca', 50], 'modelo' => ['Modelo', 50]],
        'compras' => ['proyecto' => ['Proyecto', 150], 'solicitado_por' => ['Solicitado por', 150], 'autorizado_por' => ['Autorizado por', 150], 'a_utilizarse' => ['A utilizarse', 255]],
        'traslados' => ['observacion' => ['Observación', 2000]],
        'asignaciones' => ['observaciones' => ['Observaciones', 2000]],
        'refacciones' => ['observaciones' => ['Observaciones', 2000]],
    ];

    public static function habilitado(string $modulo): bool
    {
        return config('modulos.vehiculos') || !in_array($modulo, ['vehiculos', 'refacciones'], true);
    }

    public static function visibles(): array
    {
        return array_filter(self::DEFINICIONES, self::habilitado(...), ARRAY_FILTER_USE_KEY);
    }

    public static function esenciales(string $modulo): array
    {
        [, , $id] = self::definicion($modulo);

        return [$id, ...match ($modulo) {
            'usuarios' => ['name'],
            'colaboradores', 'vehiculos', 'asignaciones', 'traslados' => ['estado'],
            'bodegas' => ['nombre', 'tipo'],
            'compras' => ['no_factura', 'total_factura'],
            'inventario' => ['producto_codigo', 'bodega_id', 'stock_tipo', 'cantidad'],
            'refacciones' => ['vehiculo_vin', 'estado', 'cantidad'],
            default => [],
        }];
    }

    public function __construct(private BodegaAccessService $bodegas, private Permisos $permisos) {}

    public static function permisoLectura(string $modulo): string
    {
        return match ($modulo) {
            'productos' => 'compras.consultar',
            'inventario' => 'bodegas.consultar',
            default => $modulo.'.consultar',
        };
    }

    public static function definicion(string $modulo): array
    {
        abort_unless(isset(self::DEFINICIONES[$modulo]), 404);

        return self::DEFINICIONES[$modulo];
    }

    public static function valorNativo(string $modulo, object $fila, string $campo): mixed
    {
        if ($modulo === 'asignaciones' && $campo === 'estado' && $fila->estado === 'Activa' && $fila->sustituida_por_id !== null) {
            return AsignacionInventario::CUSTODIA_ANTERIOR;
        }

        return $fila->{$campo};
    }

    public function consultar(User $user, string $modulo): bool
    {
        return isset(self::DEFINICIONES[$modulo]) && $this->permisos->permite($user, self::permisoLectura($modulo));
    }

    public function registros(User $user, string $modulo): Builder
    {
        [$nombre, $tabla] = self::definicion($modulo);
        abort_unless($this->consultar($user, $modulo), 403);

        $q = DB::table($tabla);
        $ambito = $user->rolOperativo();

        return match ($modulo) {
            'usuarios' => $q->when($ambito !== 1, fn (Builder $q) => $q->where('created_by', $user->id)),
            'bodegas', 'inventario', 'refacciones' => $q->when(
                ($ids = $this->bodegas->visibleBodegaIds($user)) !== null,
                fn (Builder $q) => $q->whereIn($modulo === 'bodegas' ? 'id' : 'bodega_id', $ids)
            ),
            'compras', 'productos' => $q->when(!$this->bodegas->canViewCompras($user), fn (Builder $q) => $q->whereRaw('1 = 0')),
            'asignaciones' => $q
                ->when($ambito === 2, fn (Builder $q) => $q->where('user_id', $user->id))
                ->when($ambito === 3, fn (Builder $q) => $q->whereIn('user_id', $user->almacenistasAsignados()->select('users.id'))),
            'traslados' => $q->where('tipo', 'TRASLADO')->when($ambito !== 1, fn (Builder $q) => $q->where(
                fn (Builder $scope) => $scope->where('creado_por', $user->id)
                    ->when($user->isEncargado() && $user->bodega_id, fn (Builder $scope) => $scope->orWhere('bodega_destino_id', $user->bodega_id))
            )),
            default => $q,
        };
    }

    public function registro(User $user, string $modulo, string $id): object
    {
        [, , $clave] = self::definicion($modulo);
        abort_if($clave === 'id' && !ctype_digit($id), 404);

        return $this->registros($user, $modulo)->where($clave, $id)->first() ?? abort(404);
    }

    public function enlaceOperativo(User $user, string $modulo, object $registro): ?string
    {
        $prefijo = CatalogoPermisos::prefijo($user->rolOperativo());
        [$ruta, $parametros] = match ($modulo) {
            'usuarios' => ['usuarios.edit', [$registro->id]],
            'colaboradores' => ['colaboradores.edit', [$registro->codigo]],
            'bodegas' => ['bodegas.show', [$registro->id]],
            'inventario' => ['bodegas.show', ['bodega' => $registro->bodega_id, 'stock_tipo' => $registro->stock_tipo]],
            'compras' => ['compras.show', [$registro->id]],
            'productos' => ['compras.index', []],
            'asignaciones' => ['asignaciones.index', []],
            'traslados' => ['operaciones.traslados.show', [$registro->id]],
            'vehiculos' => ['vehiculos.show', [$registro->vin]],
            'refacciones' => ['vehiculos.productos.index', ['vehiculo_vin' => $registro->vehiculo_vin]],
        };
        $nombre = $prefijo.'.'.$ruta;
        if (!$this->permisos->ruta($user, $nombre)) {
            return null;
        }

        return route($nombre, $parametros);
    }

    public function puedeEditarCampos(User $user, string $modulo): bool
    {
        return $this->consultar($user, $modulo)
            && ($this->permisos->permite($user, $modulo.'.campos_editar') || $this->permisos->permite($user, $modulo.'.modificar'));
    }

    public function editar(User $user, string $modulo, object $registro): bool
    {
        if (!$this->puedeEditarCampos($user, $modulo)) {
            return false;
        }

        return match ($modulo) {
            'inventario', 'refacciones', 'asignaciones' => $user->rolOperativo() !== 3
                && $this->bodegas->canModifyStock($user, (int) $registro->bodega_id),
            'traslados' => $user->rolOperativo() === 1 || (int) $registro->creado_por === (int) $user->id,
            'compras', 'productos' => $this->bodegas->principal()?->id !== null
                && $this->bodegas->canReceiveStock($user, (int) $this->bodegas->principal()->id),
            'usuarios' => app(Seguridad::class)->puedeGestionar($user, User::findOrFail($registro->id)),
            default => true,
        };
    }
}

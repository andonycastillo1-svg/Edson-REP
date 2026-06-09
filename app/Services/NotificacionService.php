<?php

namespace App\Services;

use App\Models\AlertaReemplazo;
use App\Models\AsignacionInventario;
use App\Models\AsignacionVehiculo;
use App\Models\Bodega;
use App\Models\Inventario;
use App\Models\Operacion;
use App\Models\User;
use App\Models\VehiculoProductoAsignacion;
use App\Notifications\AlertaGeneralNotification;
use App\Notifications\AlertaRrhhNotification;
use App\Notifications\AsignacionPendienteNotification;
use App\Notifications\CambioEstadoAsignacionNotification;
use App\Notifications\CambioEstadoTrasladoNotification;
use App\Notifications\CobroDescuentoNotification;
use App\Notifications\MovimientoInventarioNotification;
use App\Notifications\NuevaAsignacionNotification;
use App\Notifications\NuevoTrasladoNotification;
use App\Notifications\RelacionUsuarioNotification;
use App\Notifications\StockBajoNotification;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\Schema;
use Throwable;

class NotificacionService
{
    public function notificarNuevaAsignacion(Model $asignacion, User $creador, ?string $url = null): void
    {
        if (!$this->disponible()) {
            return;
        }

        $asignacion->loadMissing($this->relacionesDisponibles($asignacion));
        $colaboradorNombre = $asignacion->getRelationValue('colaborador')?->nombre;
        $base = $this->payload(
            titulo: 'Nueva asignación',
            mensaje: 'Se creó ' . $this->descripcionAsignacion($asignacion) . ' por ' . $creador->name . '.',
            tipo: 'asignacion_creada',
            url: $url ?: route('dashboard'),
            entidad: $asignacion,
            creador: $creador,
            entidadId: (string) ($asignacion->getAttribute('grupo_asignacion') ?: $asignacion->getKey())
        );

        $this->notificarAdministradores(new NuevaAsignacionNotification($base), $creador->id);

        if ((int) $creador->role_id === 2) {
            $mensaje = 'Nueva asignación creada por ' . $creador->name;
            $mensaje .= $colaboradorNombre ? ' para ' . $colaboradorNombre . '.' : '.';
            $supervisores = $this->supervisoresDeAlmacenista($creador);
            $this->safeNotify(
                $supervisores,
                new NuevaAsignacionNotification(array_merge($base, [
                    'mensaje' => $mensaje,
                    'url' => route('supervisor.asignaciones.index'),
                ])),
                $creador->id
            );
        }
    }

    public function notificarAsignacionPendiente(AsignacionInventario $asignacion, User $creador, string $url): void
    {
        if (!$this->disponible() || $asignacion->estado_evidencia !== 'pendiente' || (int) $creador->role_id !== 2) {
            return;
        }

        $asignacion->loadMissing('colaborador');
        $data = $this->payload(
            titulo: 'Asignación pendiente de evidencia',
            mensaje: 'La asignación para ' . ($asignacion->colaborador?->nombre ?? $asignacion->colaborador_codigo)
                . ' requiere seguimiento de evidencia.',
            tipo: 'asignacion_pendiente',
            url: route('supervisor.asignaciones.index'),
            entidad: $asignacion,
            creador: $creador,
            entidadId: (string) ($asignacion->grupo_asignacion ?: $asignacion->id)
        );

        $this->safeNotify(
            $this->supervisoresDeAlmacenista($creador),
            new AsignacionPendienteNotification($data),
            $creador->id
        );
    }

    public function notificarCambioEstadoAsignacion(iterable $asignaciones, User $actor, string $estado, string $url): void
    {
        $asignaciones = collect($asignaciones)->filter()->unique('id')->values();

        if (!$this->disponible() || $asignaciones->isEmpty()) {
            return;
        }

        $primera = $asignaciones->first();
        $mensaje = 'Se registró ' . strtolower($estado) . ' en ' . $asignaciones->count() . ' asignación(es) de inventario.';
        $data = $this->payload('Cambio en asignación', $mensaje, 'asignacion_estado', route('dashboard'), $primera, $actor);
        $creadores = User::query()
            ->whereIn('id', $asignaciones->map(fn (Model $item) => $item->getAttribute('user_id') ?: $item->getAttribute('asignado_por_user_id'))->filter()->unique())
            ->get();

        $this->safeNotify(
            $this->administradores($actor->id),
            new CambioEstadoAsignacionNotification(array_merge($data, [
                'url' => $primera instanceof AsignacionVehiculo
                    ? route('admin.vehiculos.asignaciones.index')
                    : route('admin.asignaciones.index'),
            ])),
            $actor->id
        );

        foreach ($creadores as $creador) {
            if ((int) $creador->id !== (int) $actor->id && (int) $creador->role_id !== 1) {
                $urlCreador = (int) $creador->role_id === 2 && $primera instanceof AsignacionInventario
                    ? route('operador.asignaciones.index')
                    : route('dashboard');
                $this->safeNotify(
                    [$creador],
                    new CambioEstadoAsignacionNotification(array_merge($data, ['url' => $urlCreador])),
                    $actor->id
                );
            }

            if ((int) $creador->role_id === 2) {
                $this->safeNotify(
                    $this->supervisoresDeAlmacenista($creador),
                    new CambioEstadoAsignacionNotification(array_merge($data, [
                        'url' => route('supervisor.asignaciones.index'),
                    ])),
                    $actor->id
                );
            }
        }
    }

    public function notificarNuevoTraslado(Operacion $operacion, User $creador): void
    {
        if (!$this->disponible()) {
            return;
        }

        $operacion->loadMissing(['bodegaOrigen', 'bodegaDestino']);
        $descripcion = $this->descripcionTraslado($operacion);
        $base = $this->payload(
            'Nueva solicitud de traslado',
            'Nueva solicitud de traslado ' . $descripcion . '.',
            'traslado_creado',
            route('dashboard'),
            $operacion,
            $creador
        );

        $this->safeNotify(
            $this->administradores($creador->id),
            new NuevoTrasladoNotification(array_merge($base, [
                'url' => route('admin.operaciones.traslados.show', $operacion),
            ])),
            $creador->id
        );

        $almacenistas = $this->almacenistasDeBodegas([
            $operacion->bodega_origen_id,
            $operacion->bodega_destino_id,
        ]);

        $this->safeNotify(
            $almacenistas,
            new NuevoTrasladoNotification(array_merge($base, [
                'mensaje' => 'Nueva solicitud de traslado ' . $descripcion . ' que involucra tu bodega.',
                'url' => route('operador.operaciones.traslados.show', $operacion),
            ])),
            $creador->id
        );

        $supervisores = $almacenistas
            ->flatMap(fn (User $almacenista) => $this->supervisoresDeAlmacenista($almacenista));
        $this->safeNotify($supervisores, new NuevoTrasladoNotification($base), $creador->id);
    }

    public function notificarCambioEstadoTraslado(Operacion $operacion, User $actor): void
    {
        if (!$this->disponible()) {
            return;
        }

        $operacion->loadMissing(['bodegaOrigen', 'bodegaDestino', 'creador']);
        $estado = strtolower((string) $operacion->estado);
        $mensaje = 'El traslado ' . $this->descripcionTraslado($operacion) . ' fue ' . $estado . '.';
        $base = $this->payload('Traslado ' . $estado, $mensaje, 'traslado_estado', route('dashboard'), $operacion, $actor);

        $this->safeNotify(
            $this->administradores($actor->id),
            new CambioEstadoTrasladoNotification(array_merge($base, [
                'url' => route('admin.operaciones.traslados.show', $operacion),
            ])),
            $actor->id
        );

        $almacenistas = $this->almacenistasDeBodegas([
            $operacion->bodega_origen_id,
            $operacion->bodega_destino_id,
        ]);
        $this->safeNotify(
            $almacenistas,
            new CambioEstadoTrasladoNotification(array_merge($base, [
                'url' => route('operador.operaciones.traslados.show', $operacion),
            ])),
            $actor->id
        );

        $supervisores = $almacenistas
            ->flatMap(fn (User $almacenista) => $this->supervisoresDeAlmacenista($almacenista));
        $this->safeNotify($supervisores, new CambioEstadoTrasladoNotification($base), $actor->id);

        $creador = $operacion->creador;

        if ($creador && (int) $creador->role_id !== 1 && (int) $creador->id !== (int) $actor->id) {
            $routePrefix = (int) $creador->role_id === 2 ? 'operador' : 'admin';
            $this->safeNotify(
                [$creador],
                new CambioEstadoTrasladoNotification(array_merge($base, [
                    'mensaje' => 'Tu traslado ' . $this->descripcionTraslado($operacion) . ' fue ' . $estado . '.',
                    'url' => route($routePrefix . '.operaciones.traslados.show', $operacion),
                ])),
                $actor->id
            );
        }
    }

    public function notificarMovimientoInventario(Bodega $bodega, User $actor, string $mensaje, ?string $entidadId = null): void
    {
        if (!$this->disponible()) {
            return;
        }

        $data = $this->payload(
            'Movimiento importante de inventario',
            $mensaje,
            'movimiento_inventario',
            route('dashboard'),
            $bodega,
            $actor,
            $entidadId
        );

        $this->safeNotify(
            $this->administradores($actor->id),
            new MovimientoInventarioNotification(array_merge($data, [
                'url' => route('admin.bodegas.show', $bodega),
            ])),
            $actor->id
        );

        $this->safeNotify(
            $this->almacenistasDeBodegas([$bodega->id]),
            new MovimientoInventarioNotification(array_merge($data, [
                'url' => route('operador.bodegas.show', $bodega),
            ])),
            $actor->id
        );
    }

    public function notificarRelacionSupervisorAlmacenista(User $supervisor, User $almacenista, User $actor): void
    {
        if (!$this->disponible()) {
            return;
        }

        $base = $this->payload(
            'Relación de supervisión actualizada',
            '',
            'relacion_usuario',
            route('dashboard'),
            $supervisor,
            $actor
        );

        $this->safeNotify([$almacenista], new RelacionUsuarioNotification(array_merge($base, [
            'mensaje' => 'Se te asignó el supervisor ' . $supervisor->name . '.',
        ])), $actor->id);
        $this->safeNotify([$supervisor], new RelacionUsuarioNotification(array_merge($base, [
            'mensaje' => 'Fuiste asignado al almacenista ' . $almacenista->name . '.',
        ])), $actor->id);
    }

    public function notificarAlertaRrhh(AlertaReemplazo $alerta, ?User $creador = null): void
    {
        if (!$this->disponible()) {
            return;
        }

        if ((bool) $alerta->descuento_aplicable) {
            $this->notificarCobroDescuento($alerta, $creador);

            return;
        }

        $alerta->loadMissing(['colaborador', 'producto']);
        $colaborador = $alerta->colaborador?->nombre ?: $alerta->colaborador_codigo;
        $producto = $alerta->producto?->nombre ?: $alerta->producto_codigo;
        $data = $this->payload(
            'Alerta para revisión de RRHH',
            "Revisar alerta de {$producto} asociada a {$colaborador}.",
            'alerta_rrhh',
            route('rrhh.alertas.index'),
            $alerta,
            $creador
        );

        $this->safeNotify($this->usuariosPorRol(4), new AlertaRrhhNotification($data), $creador?->id);
        $this->safeNotify(
            $this->administradores($creador?->id),
            new AlertaRrhhNotification(array_merge($data, ['url' => route('dashboard')])),
            $creador?->id
        );
    }

    public function notificarCobroDescuento(AlertaReemplazo $alerta, ?User $creador = null): void
    {
        if (!$this->disponible()) {
            return;
        }

        $alerta->loadMissing(['colaborador', 'producto']);
        $colaborador = $alerta->colaborador?->nombre ?: $alerta->colaborador_codigo;
        $producto = $alerta->producto?->nombre ?: $alerta->producto_codigo;
        $data = $this->payload(
            'Cobro o descuento para revisión',
            "Revisar cobro o descuento de {$producto} asociado a {$colaborador}.",
            'cobro_descuento',
            route('rrhh.alertas.index'),
            $alerta,
            $creador
        );

        $this->safeNotify($this->usuariosPorRol(4), new CobroDescuentoNotification($data), $creador?->id);
        $this->safeNotify(
            $this->administradores($creador?->id),
            new CobroDescuentoNotification(array_merge($data, ['url' => route('dashboard')])),
            $creador?->id
        );
    }

    public function notificarResolucionAlertaRrhh(AlertaReemplazo $alerta, User $actor): void
    {
        if (!$this->disponible()) {
            return;
        }

        $data = $this->payload(
            'Alerta RRHH finalizada',
            'La alerta de RRHH #' . $alerta->id . ' fue marcada como finalizada por ' . $actor->name . '.',
            'alerta_general',
            route('dashboard'),
            $alerta,
            $actor
        );
        $destinatarios = $this->administradores($actor->id);

        if ($alerta->registrado_por_user_id) {
            $registrador = User::find($alerta->registrado_por_user_id);

            if ($registrador) {
                $destinatarios->push($registrador);
            }
        }

        $this->safeNotify($destinatarios, new AlertaGeneralNotification($data), $actor->id);
    }

    public function notificarStockBajo(Inventario $inventario, ?User $creador = null): void
    {
        if (!$this->disponible() || !Schema::hasColumn('productos', 'stock_minimo')) {
            return;
        }

        $inventario->loadMissing(['producto', 'bodega']);
        $stockMinimo = (int) ($inventario->producto?->stock_minimo ?? 0);

        if ($stockMinimo <= 0 || (int) $inventario->cantidad >= $stockMinimo) {
            return;
        }

        $data = $this->payload(
            'Stock bajo',
            'El producto ' . ($inventario->producto?->nombre ?? $inventario->producto_codigo)
                . ' tiene ' . $inventario->cantidad . ' unidades en ' . ($inventario->bodega?->nombre ?? 'la bodega') . '.',
            'stock_bajo',
            route('dashboard'),
            $inventario,
            $creador
        );
        $destinatarios = $this->administradores($creador?->id)
            ->concat($this->almacenistasDeBodegas([$inventario->bodega_id]));

        $this->safeNotify($destinatarios, new StockBajoNotification($data), $creador?->id);
    }

    public function notificarAdministradores(Notification $notification, ?int $exceptUserId = null): void
    {
        $this->safeNotify($this->administradores($exceptUserId), $notification, $exceptUserId);
    }

    public function notificarRrhh(Notification $notification, ?int $exceptUserId = null): void
    {
        $this->safeNotify($this->usuariosPorRol(4), $notification, $exceptUserId);
    }

    public function notificarSupervisoresDeAlmacenista(User $almacenista, Notification $notification): void
    {
        $this->safeNotify($this->supervisoresDeAlmacenista($almacenista), $notification, $almacenista->id);
    }

    public function safeAction(callable $callback): void
    {
        try {
            $callback($this);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function safeNotify(iterable $usuarios, Notification $notification, ?int $exceptUserId = null): void
    {
        if (!$this->disponible()) {
            return;
        }

        try {
            $destinatarios = collect($usuarios)
                ->filter(fn ($usuario) => $usuario instanceof User)
                ->when($exceptUserId, fn (Collection $users) => $users->reject(
                    fn (User $user) => (int) $user->id === (int) $exceptUserId
                ))
                ->unique(fn (User $user) => (int) $user->id)
                ->values();

            if ($destinatarios->isNotEmpty()) {
                NotificationFacade::send($destinatarios, $notification);
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function almacenistasDeBodegas(iterable $bodegaIds): EloquentCollection
    {
        return User::query()
            ->where('role_id', 2)
            ->whereIn('bodega_id', collect($bodegaIds)->filter()->unique())
            ->get();
    }

    private function administradores(?int $exceptUserId = null): EloquentCollection
    {
        return User::query()
            ->where('role_id', 1)
            ->when($exceptUserId, fn ($query) => $query->where('id', '!=', $exceptUserId))
            ->get();
    }

    private function usuariosPorRol(int $roleId): EloquentCollection
    {
        return User::query()->where('role_id', $roleId)->get();
    }

    private function supervisoresDeAlmacenista(User $almacenista): EloquentCollection
    {
        return $almacenista->supervisoresAsignados()
            ->where('users.role_id', 3)
            ->get();
    }

    private function payload(
        string $titulo,
        string $mensaje,
        string $tipo,
        string $url,
        Model $entidad,
        ?User $creador = null,
        ?string $entidadId = null
    ): array {
        return [
            'titulo' => $titulo,
            'mensaje' => $mensaje,
            'tipo' => $tipo,
            'url' => $url,
            'entidad_id' => $entidadId ?? (string) $entidad->getKey(),
            'entidad_tipo' => class_basename($entidad),
            'creado_por_id' => $creador ? (string) $creador->id : '',
            'creado_por_nombre' => $creador?->name ?? 'Sistema',
            'fecha' => now()->toDateTimeString(),
        ];
    }

    private function disponible(): bool
    {
        return Schema::hasTable('notifications');
    }

    private function relacionesDisponibles(Model $asignacion): array
    {
        return method_exists($asignacion, 'colaborador') ? ['colaborador'] : [];
    }

    private function descripcionAsignacion(Model $asignacion): string
    {
        return match (true) {
            $asignacion instanceof AsignacionInventario => 'una asignación de inventario',
            $asignacion instanceof AsignacionVehiculo => 'una asignación de vehículo',
            $asignacion instanceof VehiculoProductoAsignacion => 'una asignación de producto/refacción a vehículo',
            default => 'una nueva asignación',
        };
    }

    private function descripcionTraslado(Operacion $operacion): string
    {
        return 'de ' . ($operacion->bodegaOrigen?->nombre ?? 'bodega origen')
            . ' hacia ' . ($operacion->bodegaDestino?->nombre ?? 'bodega destino');
    }
}

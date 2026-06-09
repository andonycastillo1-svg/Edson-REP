<?php

namespace App\Services;

use App\Models\AlertaReemplazo;
use App\Models\AsignacionInventario;
use App\Models\AsignacionVehiculo;
use App\Models\Inventario;
use App\Models\Operacion;
use App\Models\User;
use App\Models\VehiculoProductoAsignacion;
use App\Notifications\AlertaRrhhNotification;
use App\Notifications\AsignacionPendienteNotification;
use App\Notifications\NuevaAsignacionNotification;
use App\Notifications\StockBajoNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\Schema;
use Throwable;

class NotificacionService
{
    public function safeAction(callable $callback): void
    {
        try {
            $callback($this);
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function notificarNuevaAsignacion(Model $asignacion, User $creador, ?string $url = null): void
    {
        if (!Schema::hasTable('notifications')) {
            return;
        }

        $asignacion->loadMissing($this->relacionesDisponibles($asignacion));

        $colaboradorNombre = $asignacion->getRelationValue('colaborador')?->nombre;
        $descripcion = $this->descripcionAsignacion($asignacion);
        $entidadId = (string) ($asignacion->getAttribute('grupo_asignacion') ?: $asignacion->getKey());
        $entidadTipo = class_basename($asignacion);
        $url = $url ?: route('dashboard');

        $base = [
            'titulo' => 'Nueva asignación',
            'tipo' => 'asignacion_creada',
            'url' => $url,
            'entidad_id' => $entidadId,
            'entidad_tipo' => $entidadTipo,
            'creado_por_id' => (string) $creador->id,
            'creado_por_nombre' => $creador->name,
            'fecha' => now()->toDateTimeString(),
        ];

        $mensajeAdmin = 'Se creó ' . $descripcion . ' por ' . $creador->name . '.';

        $this->notificarAdministradores(
            new NuevaAsignacionNotification($base + ['mensaje' => $mensajeAdmin]),
            $creador->id
        );

        if ((int) $creador->role_id === 2) {
            $mensajeSupervisor = 'Nueva asignación creada por ' . $creador->name;

            if ($colaboradorNombre) {
                $mensajeSupervisor .= ' para ' . $colaboradorNombre;
            }

            $mensajeSupervisor .= '.';

            $this->notificarSupervisoresDeAlmacenista(
                $creador,
                new NuevaAsignacionNotification($base + ['mensaje' => $mensajeSupervisor])
            );
        }
    }

    public function notificarAsignacionPendiente(AsignacionInventario $asignacion, User $creador, string $url): void
    {
        if (!Schema::hasTable('notifications') || $asignacion->estado_evidencia !== 'pendiente') {
            return;
        }

        $asignacion->loadMissing('colaborador');

        $data = [
            'titulo' => 'Asignación pendiente de evidencia',
            'mensaje' => 'La asignación para ' . ($asignacion->colaborador?->nombre ?? $asignacion->colaborador_codigo)
                . ' tiene pendiente completar la evidencia de entrega.',
            'tipo' => 'asignacion_pendiente',
            'url' => $url,
            'entidad_id' => (string) ($asignacion->grupo_asignacion ?: $asignacion->id),
            'entidad_tipo' => class_basename($asignacion),
            'creado_por_id' => (string) $creador->id,
            'creado_por_nombre' => $creador->name,
            'fecha' => now()->toDateTimeString(),
        ];

        if ((int) $creador->role_id === 2) {
            $creador->notify(new AsignacionPendienteNotification($data));

            $this->notificarSupervisoresDeAlmacenista(
                $creador,
                new AsignacionPendienteNotification($data)
            );
        }
    }

    public function notificarNuevoTraslado(Operacion $operacion, User $creador, ?string $url = null): void
    {
        if (!Schema::hasTable('notifications')) {
            return;
        }

        $operacion->loadMissing(['bodegaOrigen', 'bodegaDestino', 'creador']);

        $url = $url ?: $this->urlTraslado($operacion, $creador);

        $origen = $operacion->bodegaOrigen?->nombre ?? 'bodega origen';
        $destino = $operacion->bodegaDestino?->nombre ?? 'bodega destino';

        $data = [
            'titulo' => 'Nuevo traslado',
            'mensaje' => "Nueva solicitud de traslado de {$origen} hacia {$destino}.",
            'tipo' => 'traslado_creado',
            'url' => $url,
            'entidad_id' => (string) $operacion->id,
            'entidad_tipo' => class_basename($operacion),
            'creado_por_id' => (string) $creador->id,
            'creado_por_nombre' => $creador->name,
            'fecha' => now()->toDateTimeString(),
        ];

        $notification = new NuevaAsignacionNotification($data);

        $destinatarios = collect();

        $destinatarios = $destinatarios->merge($this->administradores($creador->id));
        $destinatarios = $destinatarios->merge($this->almacenistasDeBodega((int) $operacion->bodega_destino_id, $creador->id));

        if ((int) $creador->role_id === 2) {
            $destinatarios = $destinatarios->merge(
                $creador->supervisoresAsignados()
                    ->where('users.role_id', 3)
                    ->where('users.id', '!=', $creador->id)
                    ->get()
            );
        }

        $this->notificarUsuarios($destinatarios, $notification);
    }

    public function notificarCambioEstadoTraslado(Operacion $operacion, User $actor, ?string $url = null): void
    {
        if (!Schema::hasTable('notifications')) {
            return;
        }

        $operacion->loadMissing(['bodegaOrigen', 'bodegaDestino', 'creador']);

        $url = $url ?: $this->urlTraslado($operacion, $actor);

        $origen = $operacion->bodegaOrigen?->nombre ?? 'bodega origen';
        $destino = $operacion->bodegaDestino?->nombre ?? 'bodega destino';
        $estado = strtolower((string) $operacion->estado);

        $mensaje = match ($estado) {
            'aprobado', 'aprobada' => "Tu traslado de {$origen} hacia {$destino} fue aprobado.",
            'rechazado', 'rechazada' => "Tu traslado de {$origen} hacia {$destino} fue rechazado.",
            default => "El traslado de {$origen} hacia {$destino} cambió a estado {$operacion->estado}.",
        };

        $data = [
            'titulo' => 'Actualización de traslado',
            'mensaje' => $mensaje,
            'tipo' => 'traslado_estado',
            'url' => $url,
            'entidad_id' => (string) $operacion->id,
            'entidad_tipo' => class_basename($operacion),
            'creado_por_id' => (string) $actor->id,
            'creado_por_nombre' => $actor->name,
            'fecha' => now()->toDateTimeString(),
        ];

        $notification = new NuevaAsignacionNotification($data);

        $destinatarios = collect();

        if ($operacion->creador && (int) $operacion->creador->id !== (int) $actor->id) {
            $destinatarios->push($operacion->creador);
        }

        $destinatarios = $destinatarios->merge($this->administradores($actor->id));
        $destinatarios = $destinatarios->merge($this->almacenistasDeBodega((int) $operacion->bodega_origen_id, $actor->id));
        $destinatarios = $destinatarios->merge($this->almacenistasDeBodega((int) $operacion->bodega_destino_id, $actor->id));

        $this->notificarUsuarios($destinatarios, $notification);
    }

    public function notificarAlertaRrhh(AlertaReemplazo $alerta, ?User $creador = null): void
    {
        if (!Schema::hasTable('notifications')) {
            return;
        }

        $alerta->loadMissing(['colaborador', 'producto']);

        $colaborador = $alerta->colaborador?->nombre ?: $alerta->colaborador_codigo;
        $producto = $alerta->producto?->nombre ?: $alerta->producto_codigo;
        $creadorNombre = $creador?->name ?: 'Sistema';

        $data = [
            'titulo' => 'Alerta para revisión de RRHH',
            'mensaje' => "Revisar alerta de {$producto} asociada a {$colaborador}.",
            'tipo' => 'alerta_rrhh',
            'url' => route('rrhh.alertas.index'),
            'entidad_id' => (string) $alerta->id,
            'entidad_tipo' => class_basename($alerta),
            'creado_por_id' => $creador ? (string) $creador->id : '',
            'creado_por_nombre' => $creadorNombre,
            'fecha' => now()->toDateTimeString(),
        ];

        $rrhh = User::query()
            ->where('role_id', 4)
            ->when($creador, fn ($query) => $query->where('id', '!=', $creador->id))
            ->get();

        NotificationFacade::send($rrhh, new AlertaRrhhNotification($data));

        $adminData = $data;
        $adminData['url'] = route('dashboard');

        $this->notificarAdministradores(
            new AlertaRrhhNotification($adminData),
            $creador?->id
        );
    }

    public function notificarStockBajo(Inventario $inventario, ?User $creador = null): void
    {
        if (!Schema::hasTable('notifications') || !Schema::hasColumn('productos', 'stock_minimo')) {
            return;
        }

        $inventario->loadMissing(['producto', 'bodega']);

        $stockMinimo = (int) ($inventario->producto?->stock_minimo ?? 0);

        if ($stockMinimo <= 0 || (int) $inventario->cantidad >= $stockMinimo) {
            return;
        }

        $data = [
            'titulo' => 'Stock bajo',
            'mensaje' => 'El producto ' . ($inventario->producto?->nombre ?? $inventario->producto_codigo)
                . ' tiene ' . $inventario->cantidad . ' unidades en '
                . ($inventario->bodega?->nombre ?? 'la bodega') . '.',
            'tipo' => 'stock_bajo',
            'url' => route('admin.bodegas.show', $inventario->bodega_id),
            'entidad_id' => (string) $inventario->id,
            'entidad_tipo' => class_basename($inventario),
            'creado_por_id' => $creador ? (string) $creador->id : '',
            'creado_por_nombre' => $creador?->name ?? 'Sistema',
            'fecha' => now()->toDateTimeString(),
        ];

        $notification = new StockBajoNotification($data);

        $this->notificarAdministradores($notification, $creador?->id);

        $almacenistas = User::query()
            ->where('role_id', 2)
            ->where('bodega_id', $inventario->bodega_id)
            ->when($creador, fn ($query) => $query->where('id', '!=', $creador->id))
            ->get();

        NotificationFacade::send($almacenistas, new StockBajoNotification($data));
    }

    public function notificarAdministradores(Notification $notification, ?int $exceptUserId = null): void
    {
        $this->notificarUsuarios(
            $this->administradores($exceptUserId),
            $notification
        );
    }

    public function notificarSupervisoresDeAlmacenista(User $almacenista, Notification $notification): void
    {
        $supervisores = $almacenista->supervisoresAsignados()
            ->where('users.role_id', 3)
            ->where('users.id', '!=', $almacenista->id)
            ->get();

        $this->notificarUsuarios($supervisores, $notification);
    }

    private function administradores(?int $exceptUserId = null): Collection
    {
        return User::query()
            ->where('role_id', 1)
            ->when($exceptUserId, fn ($query) => $query->where('id', '!=', $exceptUserId))
            ->get();
    }

    private function almacenistasDeBodega(?int $bodegaId, ?int $exceptUserId = null): Collection
    {
        if (!$bodegaId) {
            return collect();
        }

        return User::query()
            ->where('role_id', 2)
            ->where('bodega_id', $bodegaId)
            ->when($exceptUserId, fn ($query) => $query->where('id', '!=', $exceptUserId))
            ->get();
    }

    private function notificarUsuarios($usuarios, Notification $notification): void
    {
        $usuarios = collect($usuarios)
            ->filter()
            ->unique('id')
            ->values();

        if ($usuarios->isEmpty()) {
            return;
        }

        NotificationFacade::send($usuarios, $notification);
    }

    private function urlTraslado(Operacion $operacion, User $user): string
    {
        $prefix = (int) $user->role_id === 2 ? 'operador' : 'admin';
        $route = $prefix . '.operaciones.traslados.show';

        return route($route, $operacion);
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
}
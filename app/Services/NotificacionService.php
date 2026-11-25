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
use App\Notifications\CambioEstadoAsignacionNotification;
use App\Notifications\MovimientoInventarioNotification;
use App\Notifications\RelacionUsuarioNotification;
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

        $grupoInventario = $asignacion instanceof AsignacionInventario && $asignacion->entrega !== null;
        if ($grupoInventario) {
            $base['url'] = route('admin.asignaciones.entrega', $entidadId);
        }
        $mensajeAdmin = 'Se creó ' . $descripcion . ' por ' . $creador->name . '.';

        $this->notificarAdministradores(
            new NuevaAsignacionNotification($base + ['mensaje' => $mensajeAdmin]),
            $creador->id
        );

        if ($creador->rolOperativo() === 2) {
            if ($grupoInventario) {
                $base['url'] = route('supervisor.asignaciones.entrega', $entidadId);
            }
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
        $destinatarios = $destinatarios->merge($this->responsablesDeTraslado($operacion, $creador->id));

        if ($creador->rolOperativo() === 2) {
            $destinatarios = $destinatarios->merge(
                $creador->supervisoresAsignados()
                    ->deAmbito(3)
                    ->where('users.id', '!=', $creador->id)
                    ->get()
            );
        }

        $this->notificarTraslado($operacion, $destinatarios, $notification);
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

        $this->notificarTraslado($operacion, $destinatarios, $notification);
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
            'titulo' => 'Incidencia para revisión de RR. HH.',
            'mensaje' => "Revisar incidencia de {$producto} asociada a {$colaborador}.",
            'tipo' => 'alerta_rrhh',
            'url' => route('rrhh.alertas.show', $alerta),
            'entidad_id' => (string) $alerta->id,
            'entidad_tipo' => class_basename($alerta),
            'creado_por_id' => $creador ? (string) $creador->id : '',
            'creado_por_nombre' => $creadorNombre,
            'fecha' => now()->toDateTimeString(),
        ];

        $rrhh = User::query()
            ->deAmbito(4)
            ->when($creador, fn ($query) => $query->where('id', '!=', $creador->id))
            ->get();

        $this->safeNotify($rrhh, new AlertaRrhhNotification($data));

        if ($alerta->motivo_revision !== null) {
            return;
        }

        $adminData = $data;
        $adminData['url'] = route('dashboard');

        $this->notificarAdministradores(
            new AlertaRrhhNotification($adminData),
            $creador?->id
        );
    }

    public function notificarMovimientoInventario(Bodega $bodega, User $actor, string $mensaje, ?string $entidadId = null): void
    {
        $data = $this->payload('Movimiento de inventario', $mensaje, $bodega, $actor, $entidadId);
        $this->safeNotify($this->administradores($actor->id), new MovimientoInventarioNotification($data + [
            'url' => route('admin.bodegas.show', $bodega),
        ]));
        $this->safeNotify($this->almacenistasDeBodega($bodega->id, $actor->id), new MovimientoInventarioNotification($data + [
            'url' => route('operador.bodegas.show', $bodega),
        ]));
    }

    public function notificarRegularizacionCustodia(?AsignacionInventario $anterior, User $actor): void
    {
        if ($anterior === null) {
            return;
        }

        $acceso = app(AccesoCustodia::class);
        $autor = $anterior->user;
        $autorPuedeAtender = $autor && $acceso->puedeResolver($autor, $anterior)
            && app(\App\Sistema\Permisos::class)->permite($autor, 'asignaciones.consultar');
        $responsables = $autorPuedeAtender ? collect([$autor]) : $this->administradores();
        $data = $this->payload('Regularización de custodia solicitada',
            'RR. HH. solicitó verificar la custodia del código '.$anterior->producto_codigo.'.', $anterior, $actor);
        foreach ($responsables as $responsable) {
            if (!$acceso->puedeResolver($responsable, $anterior)) {
                continue;
            }
            $prefijo = \App\Sistema\CatalogoPermisos::prefijo($responsable->rolOperativo());
            $ruta = $anterior->grupo_asignacion
                ? route($prefijo.'.asignaciones.entrega', $anterior->grupo_asignacion)
                : route($prefijo.'.asignaciones.index');
            $this->safeNotify([$responsable], new CambioEstadoAsignacionNotification($data + ['url' => $ruta]));
        }
    }

    public function notificarCambioEstadoAsignacion(iterable $asignaciones, User $actor, string $estado): void
    {
        $asignaciones = collect($asignaciones)->filter()->unique('id');
        $primera = $asignaciones->first();
        if (!$primera) {
            return;
        }

        $data = $this->payload('Cambio en asignación', 'Se registró ' . $estado . ' en ' . $asignaciones->count() . ' asignación(es).', $primera, $actor);
        $dataAdmin = $data + ['url' => $primera instanceof AsignacionVehiculo
            ? route('admin.vehiculos.asignaciones.index') : route('admin.asignaciones.index')];
        $this->safeNotify($this->administradores($actor->id), new CambioEstadoAsignacionNotification($dataAdmin));

        $creadores = User::whereIn('id', $asignaciones->map(fn (Model $item) => $item->user_id ?? $item->asignado_por_user_id)
            ->filter()->unique())->deAmbito(2)->get();
        $this->safeNotify($creadores, new CambioEstadoAsignacionNotification($data + [
            'url' => $primera instanceof AsignacionInventario ? route('operador.asignaciones.index') : route('dashboard'),
        ]), $actor->id);
        $supervisores = $creadores->flatMap(fn (User $creador) => $creador->supervisoresAsignados()->deAmbito(3)->get());
        $this->safeNotify($supervisores, new CambioEstadoAsignacionNotification($data + [
            'url' => route('supervisor.asignaciones.index'),
        ]), $actor->id);
    }

    public function notificarRelacionSupervisorAlmacenista(User $supervisor, User $almacenista, User $actor): void
    {
        $data = $this->payload('Relación de supervisión actualizada', '', $supervisor, $actor) + ['url' => route('dashboard')];
        $this->safeNotify([$almacenista], new RelacionUsuarioNotification(array_replace($data, [
            'mensaje' => 'Se te asignó el supervisor ' . $supervisor->name . '.',
        ])), $actor->id);
        $this->safeNotify([$supervisor], new RelacionUsuarioNotification(array_replace($data, [
            'mensaje' => 'Fuiste asignado al almacenista ' . $almacenista->name . '.',
        ])), $actor->id);
    }

    public function notificarResolucionAlertaRrhh(AlertaReemplazo $alerta, User $actor): void
    {
        $data = $this->payload('Alerta RRHH finalizada', 'Se finalizó la alerta de RRHH #' . $alerta->id . '.', $alerta, $actor)
            + ['url' => route('dashboard')];
        $destinatarios = $this->administradores($actor->id);
        if ($alerta->registrado_por_user_id) {
            $destinatarios->push(User::find($alerta->registrado_por_user_id));
        }
        $this->safeNotify($destinatarios, new AlertaGeneralNotification($data), $actor->id);
    }

    private function payload(string $titulo, string $mensaje, Model $entidad, User $actor, ?string $entidadId = null): array
    {
        return [
            'titulo' => $titulo,
            'mensaje' => $mensaje,
            'entidad_id' => $entidadId ?? (string) $entidad->getKey(),
            'entidad_tipo' => class_basename($entidad),
            'creado_por_id' => (string) $actor->id,
            'creado_por_nombre' => $actor->name,
            'fecha' => now()->toDateTimeString(),
        ];
    }

    public function notificarStockBajo(Inventario $inventario, ?User $creador = null): void
    {
        if (!Schema::hasTable('notifications')) {
            return;
        }

        $inventario->loadMissing(['producto', 'bodega']);

        $stockMinimo = (int) ($inventario->producto?->stock_minimo ?? app(\App\Sistema\Ajustes::class)->general('stock_minimo', 0));

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
            ->deAmbito(2)
            ->where('bodega_id', $inventario->bodega_id)
            ->when($creador, fn ($query) => $query->where('id', '!=', $creador->id))
            ->get();

        $this->safeNotify($almacenistas, new StockBajoNotification($data));
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
            ->deAmbito(3)
            ->where('users.id', '!=', $almacenista->id)
            ->get();

        $this->notificarUsuarios($supervisores, $notification);
    }

    private function administradores(?int $exceptUserId = null): Collection
    {
        return User::query()
            ->deAmbito(1)
            ->when($exceptUserId, fn ($query) => $query->where('id', '!=', $exceptUserId))
            ->get();
    }

    private function responsablesDeTraslado(Operacion $operacion, int $excepto): Collection
    {
        $destino = $operacion->bodega_destino_id;
        $acceso = app(AccesoInventarioOperativo::class);

        return User::whereKeyNot($excepto)->where('acceso_activo', true)->where(fn ($q) => $q
            ->where('bodega_id', $destino)
            ->orWhereIn('id', \Illuminate\Support\Facades\DB::table('bodega_usuario_operativa')->where('bodega_id', $destino)
                ->where('opera_inventario', true)->select('user_id'))
            ->orWhereIn('id', \App\Models\DelegacionOperativa::query()->whereNull('revocada_en')->where('vigente_desde', '<=', now())
                ->where('vigente_hasta', '>', now())->whereHas('bodegas', fn ($q) => $q->whereKey($destino))->select('sustituto_id')))
            ->get()->filter(fn (User $cuenta) => $acceso->decidirTraslado($cuenta, $operacion, 'traslados.aprobar')
                || $acceso->decidirTraslado($cuenta, $operacion, 'traslados.rechazar'));
    }

    private function notificarTraslado(Operacion $operacion, Collection $destinatarios, Notification $notificacion): void
    {
        $acceso = app(AccesoInventarioOperativo::class);
        $this->notificarUsuarios($destinatarios->unique('id')->filter(fn (User $cuenta) =>
            $acceso->trasladoVisible($cuenta, $operacion) !== null), $notificacion);
    }

    private function almacenistasDeBodega(?int $bodegaId, ?int $exceptUserId = null): Collection
    {
        if (!$bodegaId) {
            return collect();
        }

        return User::query()
            ->deAmbito(2)
            ->where('bodega_id', $bodegaId)
            ->when($exceptUserId, fn ($query) => $query->where('id', '!=', $exceptUserId))
            ->get();
    }

    private function notificarUsuarios(iterable $usuarios, Notification $notification): void
    {
        $this->safeNotify($usuarios, $notification);
    }

    public function safeNotify(iterable $usuarios, Notification $notification, ?int $exceptUserId = null): void
    {
        $this->safeAction(function () use ($usuarios, $notification, $exceptUserId) {
            if (!Schema::hasTable('notifications')) {
                return;
            }

            $destinatarios = collect($usuarios)
                ->filter(fn ($usuario) => $usuario instanceof User && (int) $usuario->id !== $exceptUserId
                    && $usuario->acceso_activo !== false && $usuario->role?->activo !== false)
                ->unique('id')->values();
            if ($destinatarios->isEmpty()) {
                return;
            }
            $datos = method_exists($notification, 'toDatabase') ? $notification->toDatabase($destinatarios->first()) : [];
            $evento = $datos['tipo'] ?? '';
            if (isset(\App\Sistema\Ajustes::EVENTOS[$evento])) {
                $regla = app(\App\Sistema\Ajustes::class)->leer('notificaciones', [])[$evento] ?? [];
                if (($regla['activo'] ?? true) === false) {
                    return;
                }
                $roles = $regla['roles'] ?? [];
                $usuarios = $regla['usuarios'] ?? [];
                if ($roles !== [] || $usuarios !== []) {
                    $destinatarios = $destinatarios->filter(fn (User $user) => in_array((int) $user->role_id, $roles, true)
                        || in_array((int) $user->id, $usuarios, true));
                }
            }
            $ruta = $this->rutaDeNotificacion($datos['url'] ?? null);
            if ($ruta !== null && preg_match('/^(admin|operador|supervisor|rrhh)\./', $ruta)) {
                $permisos = app(\App\Sistema\Permisos::class);
                $destinatarios = $destinatarios->filter(fn (User $user) => $permisos->ruta($user, $ruta));
            }
            if ($destinatarios->isNotEmpty()) {
                NotificationFacade::send($destinatarios, $notification);
            }
        });
    }

    private function rutaDeNotificacion(?string $url): ?string
    {
        if (!$url) {
            return null;
        }

        try {
            return app('router')->getRoutes()->match(\Illuminate\Http\Request::create($url))->getName();
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException) {
            return null;
        }
    }

    private function urlTraslado(Operacion $operacion, User $user): string
    {
        $prefix = AccesoInventarioOperativo::prefijo($user);
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

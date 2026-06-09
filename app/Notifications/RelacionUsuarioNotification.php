<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RelacionUsuarioNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly array $data)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return array_merge([
            'titulo' => 'Notificación',
            'mensaje' => '',
            'tipo' => 'relacion_usuario',
            'url' => '',
            'entidad_id' => '',
            'entidad_tipo' => '',
            'creado_por_id' => '',
            'creado_por_nombre' => '',
            'fecha' => now()->toDateTimeString(),
        ], $this->data, [
            'tipo' => 'relacion_usuario',
        ]);
    }
}

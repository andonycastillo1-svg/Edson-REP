<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class EventoOperativoNotification extends Notification
{
    public function __construct(private readonly array $datos) {}

    public function via(object $destinatario): array
    {
        return ['database'];
    }

    public function toDatabase(object $destinatario): array
    {
        return $this->datos;
    }
}

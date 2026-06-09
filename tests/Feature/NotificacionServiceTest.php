<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\AlertaGeneralNotification;
use App\Services\NotificacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificacionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_safe_notify_deduplica_destinatarios_y_excluye_al_actor(): void
    {
        Notification::fake();

        $actor = User::factory()->create();
        $destinatario = User::factory()->create();
        $notification = new AlertaGeneralNotification([
            'titulo' => 'Prueba',
            'mensaje' => 'Mensaje',
        ]);

        app(NotificacionService::class)->safeNotify(
            [$actor, $destinatario, $destinatario, null],
            $notification,
            $actor->id
        );

        Notification::assertSentToTimes($destinatario, AlertaGeneralNotification::class, 1);
        Notification::assertNotSentTo($actor, AlertaGeneralNotification::class);
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificacionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_solo_ve_sus_propias_notificaciones(): void
    {
        $usuario = User::factory()->create();
        $otroUsuario = User::factory()->create();

        $propia = $this->crearNotificacion($usuario, 'Notificación propia');
        $ajena = $this->crearNotificacion($otroUsuario, 'Notificación ajena');

        $response = $this->actingAs($usuario)->get(route('notificaciones.index'));

        $response->assertOk();
        $response->assertSee('Notificación propia');
        $response->assertDontSee('Notificación ajena');
        $this->assertNotSame($propia->id, $ajena->id);
    }

    public function test_usuario_no_puede_marcar_como_leida_una_notificacion_ajena(): void
    {
        $usuario = User::factory()->create();
        $otroUsuario = User::factory()->create();
        $ajena = $this->crearNotificacion($otroUsuario, 'Notificación ajena');

        $this->actingAs($usuario)
            ->patch(route('notificaciones.leer', $ajena->id))
            ->assertNotFound();

        $this->assertNull($ajena->fresh()->read_at);
    }

    public function test_usuario_no_puede_eliminar_una_notificacion_ajena(): void
    {
        $usuario = User::factory()->create();
        $otroUsuario = User::factory()->create();
        $ajena = $this->crearNotificacion($otroUsuario, 'Notificación ajena');

        $this->actingAs($usuario)
            ->delete(route('notificaciones.eliminar', $ajena->id))
            ->assertNotFound();

        $this->assertDatabaseHas('notifications', ['id' => $ajena->id]);
    }

    public function test_usuario_puede_marcar_y_eliminar_sus_notificaciones(): void
    {
        $usuario = User::factory()->create();
        $notificacion = $this->crearNotificacion($usuario, 'Notificación propia');

        $this->actingAs($usuario)
            ->patch(route('notificaciones.leer', $notificacion->id))
            ->assertRedirect();

        $this->assertNotNull($notificacion->fresh()->read_at);

        $this->actingAs($usuario)
            ->delete(route('notificaciones.eliminar', $notificacion->id))
            ->assertRedirect();

        $this->assertDatabaseMissing('notifications', ['id' => $notificacion->id]);
    }

    private function crearNotificacion(User $usuario, string $titulo)
    {
        return $usuario->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'tests',
            'data' => [
                'titulo' => $titulo,
                'mensaje' => 'Mensaje de prueba',
                'tipo' => 'alerta_general',
                'url' => route('dashboard'),
            ],
        ]);
    }
}

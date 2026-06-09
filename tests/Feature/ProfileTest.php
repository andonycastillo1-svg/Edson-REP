<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_routes_redirect_authenticated_users_to_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/profile')->assertRedirect('/dashboard');
        $this->actingAs($user)->get('/profile/edit')->assertRedirect('/dashboard');
    }

    public function test_profile_information_cannot_be_updated(): void
    {
        $user = User::factory()->create([
            'name' => 'Nombre original',
            'email' => 'original@example.com',
        ]);

        $this->actingAs($user)->patch('/profile', [
            'name' => 'Nombre cambiado',
            'email' => 'cambiado@example.com',
        ])->assertMethodNotAllowed();

        $user->refresh();
        $this->assertSame('Nombre original', $user->name);
        $this->assertSame('original@example.com', $user->email);
    }

    public function test_user_cannot_delete_their_account_from_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->delete('/profile', ['password' => 'password'])
            ->assertMethodNotAllowed();

        $this->assertNotNull($user->fresh());
    }

    public function test_user_cannot_change_password_through_personal_password_route(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/password', [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertNotFound();
    }
}

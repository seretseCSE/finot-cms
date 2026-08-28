<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChangeInitialPasswordTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function change_password_page_is_shown_when_temp_password_is_unchanged(): void
    {
        $user = User::factory()->superadmin()->create([
            'password' => 'Admin1234',
            'temp_password_changed' => false,
            'password_history' => [],
        ]);

        $this->actingAs($user)
            ->get(route('change-initial-password'))
            ->assertOk()
            ->assertSee('Change your password');
    }

    #[Test]
    public function user_with_temp_password_cannot_open_the_dashboard(): void
    {
        $user = User::factory()->superadmin()->create([
            'password' => 'Admin1234',
            'temp_password_changed' => false,
            'password_history' => [],
        ]);

        $this->actingAs($user)
            ->get('/admin')
            ->assertRedirect(route('change-initial-password'));
    }

    #[Test]
    public function admin_change_password_route_redirects_to_the_web_form(): void
    {
        $user = User::factory()->superadmin()->create([
            'password' => 'Admin1234',
            'temp_password_changed' => false,
            'password_history' => [],
        ]);

        $this->actingAs($user)
            ->get('/admin/change-password')
            ->assertRedirect(route('change-initial-password'));
    }

    #[Test]
    public function user_can_change_temporary_password_and_is_sent_to_the_dashboard(): void
    {
        $user = User::factory()->superadmin()->create([
            'password' => 'Admin1234',
            'temp_password_changed' => false,
            'password_history' => [],
        ]);

        $this->actingAs($user)
            ->post(route('change-initial-password.submit'), [
                'current_password' => 'Admin1234',
                'password' => 'NewPass123',
                'password_confirmation' => 'NewPass123',
            ])
            ->assertRedirect('/admin');

        $user->refresh();

        $this->assertTrue($user->temp_password_changed);
        $this->assertTrue(Hash::check('NewPass123', $user->password));
        $this->assertAuthenticatedAs($user);
    }

    #[Test]
    public function reusing_the_current_password_is_rejected(): void
    {
        $user = User::factory()->superadmin()->create([
            'password' => 'Admin1234',
            'temp_password_changed' => false,
            'password_history' => [],
        ]);

        $this->actingAs($user)
            ->from(route('change-initial-password'))
            ->post(route('change-initial-password.submit'), [
                'current_password' => 'Admin1234',
                'password' => 'Admin1234',
                'password_confirmation' => 'Admin1234',
            ])
            ->assertRedirect(route('change-initial-password'))
            ->assertSessionHasErrors('password');

        $this->assertFalse($user->fresh()->temp_password_changed);
        $this->assertAuthenticatedAs($user);
    }

    #[Test]
    public function user_stays_authenticated_on_the_dashboard_after_password_change(): void
    {
        $user = User::factory()->superadmin()->create([
            'password' => 'Admin1234',
            'temp_password_changed' => false,
            'password_history' => [],
        ]);

        $this->actingAs($user)
            ->post(route('change-initial-password.submit'), [
                'current_password' => 'Admin1234',
                'password' => 'NewPass123',
                'password_confirmation' => 'NewPass123',
            ]);

        $this->get('/admin')->assertOk();
    }
}

<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use App\Filament\Pages\Auth\Login;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_phone_number()
    {
        $user = User::factory()->create([
            'phone' => '+251911000001',
            'password' => 'Admin1234',
        ]);

        Livewire::test(Login::class)
            ->set('data.phone', '+251911000001')
            ->set('data.password', 'Admin1234')
            ->call('authenticate')
            ->assertRedirect('/admin');

        $this->assertAuthenticatedAs($user);
    }

    public function test_user_cannot_login_with_wrong_phone()
    {
        Livewire::test(Login::class)
            ->set('data.phone', '+251911000999')
            ->set('data.password', 'Admin1234')
            ->call('authenticate')
            ->assertHasErrors('data.phone');

        $this->assertGuest();
    }

    public function test_user_cannot_login_with_wrong_password()
    {
        User::factory()->create([
            'phone' => '+251911000001',
            'password' => 'Admin1234',
        ]);

        Livewire::test(Login::class)
            ->set('data.phone', '+251911000001')
            ->set('data.password', 'wrongpassword')
            ->call('authenticate')
            ->assertHasErrors();

        $this->assertGuest();
    }

    public function test_login_form_shows_phone_field()
    {
        $response = $this->get('/admin/login');

        $response->assertStatus(200);
        $response->assertSee('Phone Number');
        $response->assertSee('ስልክ ቁጥር');
    }

    public function test_user_can_logout()
    {
        $user = User::factory()->create();
        
        $this->actingAs($user)
            ->post('/admin/logout')
            ->assertRedirect('/admin/login');
            
        $this->assertGuest();
    }

    public function test_redirected_to_login_if_unauthenticated()
    {
        $response = $this->get('/admin');
        
        $response->assertRedirect('/login');
        $this->assertGuest();
    }
}

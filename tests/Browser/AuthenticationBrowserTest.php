<?php

namespace Tests\Browser;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class AuthenticationBrowserTest extends DuskTestCase
{
    use DatabaseMigrations;

    /**
     * Test login form shows only phone field.
     */
    public function test_login_form_shows_only_phone_field(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                ->assertSee('Phone')
                ->assertDontSee('Email')
                ->assertSee('Password');
        });
    }

    /**
     * Test login with valid credentials.
     */
    public function test_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'phone' => '+251911234567',
            'password' => bcrypt('password'),
            'is_active' => true,
            'temp_password_changed' => true,
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->visit('/login')
                ->type('phone', $user->phone)
                ->type('password', 'password')
                ->press('Login')
                ->assertPathIs('/admin');
        });
    }

    /**
     * Test password change screen appears on first login.
     */
    public function test_password_change_on_first_login(): void
    {
        $user = User::factory()->create([
            'phone' => '+251911234567',
            'password' => bcrypt('TempPass123'),
            'is_active' => true,
            'temp_password_changed' => false,
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/admin')
                ->assertSee('Change Password')
                ->assertSee('Current Password')
                ->assertSee('New Password')
                ->assertSee('Confirm Password');
        });
    }

    /**
     * Test session management page shows active devices.
     */
    public function test_session_management_shows_active_devices(): void
    {
        $user = User::factory()->create([
            'phone' => '+251911234567',
            'password' => bcrypt('password'),
            'is_active' => true,
            'temp_password_changed' => true,
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/admin/manage-active-sessions')
                ->assertSee('Active Sessions')
                ->assertSee('Device')
                ->assertSee('IP Address')
                ->assertSee('Last Activity');
        });
    }

    /**
     * Test session can be revoked from management page.
     */
    public function test_session_can_be_revoked(): void
    {
        $user = User::factory()->create([
            'phone' => '+251911234567',
            'password' => bcrypt('password'),
            'is_active' => true,
            'temp_password_changed' => true,
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/admin/manage-active-sessions')
                ->press('Revoke')
                ->assertSee('Session revoked successfully');
        });
    }

    /**
     * Test logout works correctly.
     */
    public function test_logout_works(): void
    {
        $user = User::factory()->create([
            'phone' => '+251911234567',
            'password' => bcrypt('password'),
            'is_active' => true,
            'temp_password_changed' => true,
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/admin')
                ->click('#logout-button')
                ->assertPathIs('/login')
                ->assertGuest();
        });
    }

    /**
     * Test login shows error with wrong password.
     */
    public function test_login_error_with_wrong_password(): void
    {
        $user = User::factory()->create([
            'phone' => '+251911234567',
            'password' => bcrypt('password'),
            'is_active' => true,
            'temp_password_changed' => true,
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->visit('/login')
                ->type('phone', $user->phone)
                ->type('password', 'wrongpassword')
                ->press('Login')
                ->assertSee('Invalid credentials');
        });
    }

    /**
     * Test locked account shows lockout message.
     */
    public function test_locked_account_shows_message(): void
    {
        $user = User::factory()->create([
            'phone' => '+251911234567',
            'password' => bcrypt('password'),
            'is_active' => true,
            'is_locked' => true,
            'locked_until' => now()->addHour(),
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->visit('/login')
                ->type('phone', $user->phone)
                ->type('password', 'password')
                ->press('Login')
                ->assertSee('Account is locked');
        });
    }

    /**
     * Test phone field validation.
     */
    public function test_phone_field_validation(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                ->press('Login')
                ->assertSee('The phone field is required');
        });
    }

    /**
     * Test password field validation.
     */
    public function test_password_field_validation(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                ->type('phone', '+251911234567')
                ->press('Login')
                ->assertSee('The password field is required');
        });
    }

    /**
     * Test remember me checkbox works.
     */
    public function test_remember_me_works(): void
    {
        $user = User::factory()->create([
            'phone' => '+251911234567',
            'password' => bcrypt('password'),
            'is_active' => true,
            'temp_password_changed' => true,
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->visit('/login')
                ->type('phone', $user->phone)
                ->type('password', 'password')
                ->check('remember')
                ->press('Login')
                ->assertCookieHasValue('remember_web', true);
        });
    }
}

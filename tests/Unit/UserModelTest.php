<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that a user can be created with default values.
     */
    public function test_user_can_be_created_with_default_values(): void
    {
        $user = User::factory()->create();

        $this->assertInstanceOf(User::class, $user);
        $this->assertTrue($user->is_active);
        $this->assertFalse($user->is_locked);
        $this->assertTrue($user->temp_password_changed);
        $this->assertEquals(0, $user->failed_login_attempts);
        $this->assertEquals('en', $user->language_preference);
    }

    /**
     * Test user display name attribute.
     */
    public function test_user_display_name_attribute(): void
    {
        $user = User::factory()->create(['name' => 'John Doe']);

        $this->assertEquals('John Doe', $user->display_name);
    }

    /**
     * Test user isActive method returns correct value.
     */
    public function test_is_active_method_returns_correct_value(): void
    {
        $activeUser = User::factory()->create(['is_active' => true]);
        $inactiveUser = User::factory()->create(['is_active' => false]);

        $this->assertTrue($activeUser->isActive());
        $this->assertFalse($inactiveUser->isActive());
    }

    /**
     * Test user department scope getter.
     */
    public function test_department_scope_getter(): void
    {
        $user = User::factory()->create();

        $this->assertNull($user->getDepartmentScope());
    }

    /**
     * Test user needs password change method.
     */
    public function test_needs_password_change_method(): void
    {
        $userNeedingChange = User::factory()->needsPasswordChange()->create();
        $userNotNeedingChange = User::factory()->create();

        $this->assertTrue($userNeedingChange->needsPasswordChange());
        $this->assertFalse($userNotNeedingChange->needsPasswordChange());
    }

    /**
     * Test user can update password.
     */
    public function test_user_can_update_password(): void
    {
        $user = User::factory()->create(['password' => 'oldpassword']);

        $newPassword = 'NewPassword123';
        $user->updatePassword($newPassword);

        $this->assertTrue($user->temp_password_changed);
        $this->assertNotEmpty($user->password_history);
    }

    /**
     * Test user password history is maintained.
     */
    public function test_user_password_history_is_maintained(): void
    {
        $user = User::factory()->create();

        $password1 = 'Password123';
        $password2 = 'Password456';
        $password3 = 'Password789';

        $user->updatePassword($password1);
        $user->updatePassword($password2);
        $user->updatePassword($password3);

        $history = $user->getPasswordHistory(3);

        $this->assertCount(3, $history);
    }

    /**
     * Test user cannot reuse password in history.
     */
    public function test_user_cannot_reuse_password_in_history(): void
    {
        $user = User::factory()->create();

        $user->updatePassword('Password123');

        $this->assertTrue($user->isPasswordInHistory('Password123'));
    }

    /**
     * Test user account is locked method.
     */
    public function test_is_account_locked_method(): void
    {
        $lockedUser = User::factory()->locked()->create();
        $unlockedUser = User::factory()->create();

        $this->assertTrue($lockedUser->isAccountLocked());
        $this->assertFalse($unlockedUser->isAccountLocked());
    }

    /**
     * Test user is manually locked method.
     */
    public function test_is_manually_locked_method(): void
    {
        $manuallyLockedUser = User::factory()->create(['is_locked' => true]);
        $autoLockedUser = User::factory()->create([
            'is_locked' => false,
            'locked_until' => now()->addHour(),
        ]);
        $unlockedUser = User::factory()->create();

        $this->assertTrue($manuallyLockedUser->isManuallyLocked());
        $this->assertFalse($autoLockedUser->isManuallyLocked());
        $this->assertFalse($unlockedUser->isManuallyLocked());
    }

    /**
     * Test user is automatically locked method.
     */
    public function test_is_automatically_locked_method(): void
    {
        $autoLockedUser = User::factory()->create([
            'is_locked' => false,
            'locked_until' => now()->addHour(),
        ]);
        $expiredLockUser = User::factory()->create([
            'is_locked' => false,
            'locked_until' => now()->subHour(),
        ]);
        $unlockedUser = User::factory()->create();

        $this->assertTrue($autoLockedUser->isAutomaticallyLocked());
        $this->assertFalse($expiredLockUser->isAutomaticallyLocked());
        $this->assertFalse($unlockedUser->isAutomaticallyLocked());
    }

    /**
     * Test user can manually lock account.
     */
    public function test_user_can_manually_lock_account(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $user->manuallyLock('Test lock reason', $admin->id);

        $this->assertTrue($user->is_locked);
    }

    /**
     * Test user can manually unlock account.
     */
    public function test_user_can_manually_unlock_account(): void
    {
        $user = User::factory()->locked()->create();
        $admin = User::factory()->admin()->create();

        $user->manuallyUnlock('Test unlock reason', $admin->id);

        $this->assertFalse($user->is_locked);
        $this->assertNull($user->locked_until);
        $this->assertEquals(0, $user->failed_login_attempts);
    }

    /**
     * Test user can reset failed login attempts.
     */
    public function test_user_can_reset_failed_login_attempts(): void
    {
        $user = User::factory()->create(['failed_login_attempts' => 5]);

        $user->resetFailedAttempts();

        $this->assertEquals(0, $user->failed_login_attempts);
        $this->assertFalse($user->is_locked);
        $this->assertNull($user->locked_until);
    }

    /**
     * Test user can increment failed login attempts.
     */
    public function test_user_can_increment_failed_login_attempts(): void
    {
        $user = User::factory()->create(['failed_login_attempts' => 4]);

        $user->incrementFailedAttempts();

        $this->assertEquals(5, $user->failed_login_attempts);
    }

    /**
     * Test user lockout message generation.
     */
    public function test_user_gets_lockout_message(): void
    {
        $lockedUser = User::factory()->create([
            'is_locked' => false,
            'locked_until' => now()->addMinutes(5),
        ]);

        $message = $lockedUser->getLockoutMessage();

        $this->assertStringContainsString('locked', $message);
        $this->assertStringContainsString('minutes', $message);
    }

    /**
     * Test user remaining lockout minutes calculation.
     */
    public function test_user_remaining_lockout_minutes(): void
    {
        $lockedUser = User::factory()->create([
            'is_locked' => true,
            'locked_until' => now()->addMinutes(10),
        ]);

        $this->assertGreaterThan(0, $lockedUser->getRemainingLockoutMinutes());
    }

    /**
     * Test user lock account method.
     */
    public function test_user_can_lock_account(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $result = $user->lockAccount('Test reason', '24h', $admin->id);

        $this->assertTrue($result);
        $this->assertTrue($user->is_locked);
    }

    /**
     * Test user unlock account method.
     */
    public function test_user_can_unlock_account(): void
    {
        $user = User::factory()->locked()->create();

        $result = $user->unlockAccount();

        $this->assertTrue($result);
        $this->assertFalse($user->is_locked);
    }

    /**
     * Test user preferred locale method.
     */
    public function test_user_preferred_locale(): void
    {
        $userEn = User::factory()->create(['language_preference' => 'en']);
        $userAm = User::factory()->create(['language_preference' => 'am']);

        $this->assertEquals('en', $userEn->getPreferredLocale());
        $this->assertEquals('am', $userAm->getPreferredLocale());
    }

    /**
     * Test user username method returns phone.
     */
    public function test_user_username_returns_phone(): void
    {
        $user = User::factory()->create(['phone' => '+251911234567']);

        $this->assertEquals('+251911234567', $user->username());
    }

    /**
     * Test user find for phone method.
     */
    public function test_user_find_for_phone(): void
    {
        $user = User::factory()->create(['phone' => '+251911234567']);

        $foundUser = User::findForPhone('+251911234567');

        $this->assertEquals($user->id, $foundUser->id);
    }

    /**
     * Test user can find by phone returns null for non-existent.
     */
    public function test_user_find_for_phone_returns_null_for_non_existent(): void
    {
        $foundUser = User::findForPhone('+251911999999');

        $this->assertNull($foundUser);
    }
}

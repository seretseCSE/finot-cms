<?php

namespace Tests\Unit\Services\Auth;

use App\Models\User;
use App\Services\Auth\PasswordHistoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PasswordHistoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private PasswordHistoryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PasswordHistoryService();
    }

    #[Test]
    public function update_password_adds_old_password_to_history(): void
    {
        $oldPasswordHash = Hash::make('old_password');
        $user = User::factory()->create([
            'password' => $oldPasswordHash,
            'password_history' => [],
        ]);

        $this->service->updatePassword($user, Hash::make('new_password'));
        $user->refresh();

        $this->assertCount(1, $user->password_history);
        $this->assertEquals($oldPasswordHash, $user->password_history[0]);
    }

    #[Test]
    public function password_history_is_trimmed_to_max_count(): void
    {
        config(['finot.password_history_count' => 3]);

        $user = User::factory()->create([
            'password' => Hash::make('current'),
            'password_history' => [
                Hash::make('password_1'),
                Hash::make('password_2'),
                Hash::make('password_3'),
            ],
        ]);

        $this->service->updatePassword($user, Hash::make('new_password'));
        $user->refresh();

        $this->assertCount(3, $user->password_history);
    }

    #[Test]
    public function is_password_in_history_returns_true_for_recent_password(): void
    {
        $password = 'my_secret_password';
        $passwordHash = Hash::make($password);

        $user = User::factory()->create([
            'password' => Hash::make('current'),
            'password_history' => [$passwordHash],
        ]);

        $this->assertTrue($this->service->isPasswordInHistory($user, $password));
    }

    #[Test]
    public function is_password_in_history_returns_false_for_new_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('current'),
            'password_history' => [
                Hash::make('password_1'),
                Hash::make('password_2'),
            ],
        ]);

        $this->assertFalse($this->service->isPasswordInHistory($user, 'brand_new_password'));
    }

    #[Test]
    public function is_password_in_history_returns_false_when_history_is_empty(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('current'),
            'password_history' => [],
        ]);

        $this->assertFalse($this->service->isPasswordInHistory($user, 'any_password'));
    }

    #[Test]
    public function password_outside_history_count_is_accepted(): void
    {
        config(['finot.password_history_count' => 2]);

        $oldPassword = 'old_password';
        $oldHash = Hash::make($oldPassword);

        $user = User::factory()->create([
            'password' => Hash::make('current'),
            'password_history' => [
                Hash::make('recent_1'),
                Hash::make('recent_2'),
                $oldHash,
            ],
        ]);

        // Only the last 2 passwords are checked, so the 3rd (old) should be accepted
        $this->assertFalse($this->service->isPasswordInHistory($user, $oldPassword, 2));
    }

    #[Test]
    public function password_within_history_count_is_rejected(): void
    {
        config(['finot.password_history_count' => 3]);

        $recentPassword = 'recent_password';
        $recentHash = Hash::make($recentPassword);

        $user = User::factory()->create([
            'password' => Hash::make('current'),
            'password_history' => [
                $recentHash,
                Hash::make('older_1'),
                Hash::make('older_2'),
            ],
        ]);

        $this->assertTrue($this->service->isPasswordInHistory($user, $recentPassword));
    }

    #[Test]
    public function add_to_password_history_prepends_and_trims(): void
    {
        config(['finot.password_history_count' => 2]);

        $user = User::factory()->create([
            'password' => Hash::make('current'),
            'password_history' => [Hash::make('existing')],
        ]);

        $newHash = Hash::make('new');
        $this->service->addToPasswordHistory($user, $newHash);
        $user->refresh();

        $this->assertCount(2, $user->password_history);
        $this->assertEquals($newHash, $user->password_history[0]);
    }

    #[Test]
    public function get_password_history_returns_correct_slice(): void
    {
        config(['finot.password_history_count' => 2]);

        $hash1 = Hash::make('password_1');
        $hash2 = Hash::make('password_2');
        $hash3 = Hash::make('password_3');

        $user = User::factory()->create([
            'password' => Hash::make('current'),
            'password_history' => [$hash1, $hash2, $hash3],
        ]);

        $history = $this->service->getPasswordHistory($user);

        $this->assertCount(2, $history);
        $this->assertEquals($hash1, $history[0]);
        $this->assertEquals($hash2, $history[1]);
    }

    #[Test]
    public function get_password_history_returns_empty_array_when_none_exists(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('current'),
            'password_history' => null,
        ]);

        $this->assertEquals([], $this->service->getPasswordHistory($user));
    }

    #[Test]
    public function update_password_sets_temp_password_changed_to_true(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old'),
            'temp_password_changed' => false,
        ]);

        $this->service->updatePassword($user, Hash::make('new'));
        $user->refresh();

        $this->assertTrue($user->temp_password_changed);
    }
}

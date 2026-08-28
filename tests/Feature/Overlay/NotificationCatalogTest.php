<?php

namespace Tests\Feature\Overlay;

use App\Models\InAppNotification;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\Notifications\Notifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotificationCatalogTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function sms_whitelist_is_empty_so_sms_is_never_allowed(): void
    {
        $this->assertSame([], PlatformSetting::getValue('notifications.sms_whitelist', null));
        $this->assertFalse(Notifier::smsAllowed('messages.emergency'));
        $this->assertFalse(Notifier::smsAllowed('academics.marklist_submitted'));
    }

    #[Test]
    public function notifier_writes_to_integer_catalog_not_uuid_table(): void
    {
        $user = User::factory()->admin()->create();
        app(Notifier::class)->toUser($user, 'imports.committed', [
            'imported' => 2,
            'skipped' => 0,
            'failed' => 0,
        ]);

        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $user->id,
            'event' => 'imports.committed',
        ]);
        $this->assertSame(1, InAppNotification::query()->count());
    }

    #[Test]
    public function bell_endpoint_returns_unread_payload(): void
    {
        $user = User::factory()->admin()->create();
        app(Notifier::class)->toUser($user, 'bookings.requested', ['purpose' => 'Choir']);

        $this->actingAs($user)
            ->getJson(route('notifications.in-app'))
            ->assertOk()
            ->assertJsonPath('unread', 1);
    }
}

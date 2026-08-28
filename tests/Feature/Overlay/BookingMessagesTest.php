<?php

namespace Tests\Feature\Overlay;

use App\Enums\BookingStatus;
use App\Enums\BulkMessageStatus;
use App\Enums\FacilityType;
use App\Jobs\FanOutBulkMessageJob;
use App\Models\BulkMessage;
use App\Models\Facility;
use App\Models\InAppNotification;
use App\Models\Member;
use App\Models\MessageCategory;
use App\Models\User;
use App\Services\Facilities\BookingService;
use App\Services\Messages\RecipientResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class BookingMessagesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function overlapping_bookings_are_rejected_until_admin_confirms(): void
    {
        $head = User::factory()->educationHead()->create();
        $admin = User::factory()->admin()->create();
        $facility = Facility::query()->create([
            'name' => 'Hall A',
            'type' => FacilityType::Hall,
            'is_active' => true,
        ]);

        $service = app(BookingService::class);
        $first = $service->request($head, [
            'facility_id' => $facility->id,
            'purpose' => 'Choir',
            'start_at' => now()->addDay()->setTime(10, 0),
            'end_at' => now()->addDay()->setTime(12, 0),
        ]);
        $this->assertSame(BookingStatus::Pending, $first->status);

        try {
            $service->request($head, [
                'facility_id' => $facility->id,
                'purpose' => 'Class',
                'start_at' => now()->addDay()->setTime(11, 0),
                'end_at' => now()->addDay()->setTime(13, 0),
            ]);
            $this->fail('Overlap should 422');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $confirmed = $service->confirm($first, $admin);
        $this->assertSame(BookingStatus::Confirmed, $confirmed->status);
    }

    #[Test]
    public function education_head_cannot_broadcast_globally_and_in_app_fanout_works(): void
    {
        $dept = $this->getOrCreateDepartment('Education');
        $head = User::factory()->educationHead()->create(['department_id' => $dept->id]);
        $this->assertFalse($head->can('messages.broadcast_global'));
        $this->assertTrue($head->can('messages.broadcast'));

        $member = Member::factory()->create(['department_id' => $dept->id]);
        $student = User::factory()->student()->create([
            'member_id' => $member->id,
            'department_id' => $dept->id,
        ]);

        try {
            app(RecipientResolver::class)->resolve($head, ['global' => true]);
            $this->fail('Global broadcast should be forbidden');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('audience', $e->errors());
        }

        $category = MessageCategory::query()->where('key', 'announcement')->first();
        $message = BulkMessage::query()->create([
            'sender_id' => $head->id,
            'department_id' => $dept->id,
            'category_id' => $category->id,
            'body' => 'Class postponed',
            'channels' => ['in_app'],
            'status' => BulkMessageStatus::Queued,
            'quiet_hours_bypassed' => true,
            'audience' => ['department_id' => $dept->id, 'global' => false],
        ]);

        (new FanOutBulkMessageJob($message->id))->handle(app(RecipientResolver::class), app(\App\Services\Notifications\Notifier::class));

        $this->assertTrue(
            InAppNotification::query()->where('user_id', $student->id)->where('event', 'messages.broadcast')->exists()
        );
    }
}

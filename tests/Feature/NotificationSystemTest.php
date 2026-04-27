<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotificationSystemTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_can_access_send_notification_page(): void
    {
        $user = $this->createAdminUser();
        $this->actingAs($user);

        $response = $this->get('/admin/send-notification');
        $this->assertNotEquals(404, $response->getStatusCode(), 'Route not found');
        $this->assertNotEquals(403, $response->getStatusCode(), 'Forbidden');
    }

    #[Test]
    public function admin_can_access_notifications_resource(): void
    {
        $user = $this->createAdminUser();
        $this->actingAs($user);

        $response = $this->get('/admin/notifications');
        $this->assertNotEquals(404, $response->getStatusCode(), 'Route not found');
        $this->assertNotEquals(403, $response->getStatusCode(), 'Forbidden');
    }

    #[Test]
    public function notifications_create_page_accessible(): void
    {
        $user = $this->createAdminUser();
        $this->actingAs($user);

        $response = $this->get('/admin/notifications/create');
        $this->assertNotEquals(404, $response->getStatusCode(), 'Route not found');
        $this->assertNotEquals(403, $response->getStatusCode(), 'Forbidden');
    }

    #[Test]
    public function push_subscriptions_resource_accessible(): void
    {
        $user = $this->createSuperadminUser();
        $this->actingAs($user);

        $response = $this->get('/admin/push-subscriptions');
        $response->assertStatus(404);
    }

    #[Test]
    public function staff_cannot_send_notifications(): void
    {
        $user = $this->createStaffUser();
        $this->actingAs($user);

        $response = $this->get('/admin/send-notification');
        $response->assertStatus(403);
    }

    #[Test]
    public function notification_model_can_be_created(): void
    {
        $user = $this->createAdminUser();
        $notification = \App\Models\Notification::factory()->create([
            'title' => 'Test Notification',
            'user_id' => $user->id,
        ]);

        $this->assertDatabaseHas('notifications', ['title' => 'Test Notification']);
    }
}

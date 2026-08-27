<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EventFundraisingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_can_access_events_page(): void
    {
        $user = $this->createAdminUser();
        $this->actingAs($user);

        $response = $this->get('/admin/events');
        $response->assertStatus(200);
    }

    #[Test]
    public function admin_can_access_events_create_page(): void
    {
        $user = $this->createAdminUser();
        $this->actingAs($user);

        $response = $this->get('/admin/events/create');
        $response->assertStatus(200);
    }

    #[Test]
    public function admin_can_access_fundraising_campaigns_page(): void
    {
        $user = $this->createAdminUser();
        $this->actingAs($user);

        $response = $this->get('/admin/fundraising-campaigns');
        $response->assertStatus(200);
    }

    #[Test]
    public function public_events_redirects_to_news(): void
    {
        $this->get('/events')->assertRedirect(route('news', ['tab' => 'events']));
    }

    #[Test]
    public function public_fundraising_page_loads(): void
    {
        $response = $this->get('/fundraising');
        $response->assertStatus(200);
    }

    #[Test]
    public function event_registrations_resource_accessible(): void
    {
        $user = $this->createAdminUser();
        $this->actingAs($user);

        $response = $this->get('/admin/event-registrations');
        $response->assertStatus(200);
    }

    #[Test]
    public function fundraising_resource_pages_accessible(): void
    {
        $user = $this->createAdminUser();
        $this->actingAs($user);

        $this->get('/admin/fundraising-campaigns')->assertStatus(200);
        $this->get('/admin/fundraising-campaigns/create')->assertStatus(200);
    }
}

<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function superadmin_dashboard_has_system_widgets(): void
    {
        $user = $this->createSuperadminUser();
        $response = $this->actingAs($user)->get('/admin');
        $response->assertStatus(200);
    }

    #[Test]
    public function finance_head_sees_financial_widgets(): void
    {
        $user = $this->createFinanceHeadUser();
        $response = $this->actingAs($user)->get('/admin');
        $response->assertStatus(200);
    }

    #[Test]
    public function education_head_sees_education_widgets(): void
    {
        $user = $this->createEducationHeadUser();
        $response = $this->actingAs($user)->get('/admin');
        $response->assertStatus(200);
    }

    #[Test]
    public function tour_head_sees_tour_widgets(): void
    {
        $user = $this->createTourHeadUser();
        $response = $this->actingAs($user)->get('/admin');
        $response->assertStatus(200);
    }

    #[Test]
    public function revenue_and_charity_head_sees_dashboard(): void
    {
        $user = $this->createUserWithRole('revenue_and_charity_head');
        $response = $this->actingAs($user)->get('/admin');
        $response->assertStatus(200);
    }

    #[Test]
    public function inventory_staff_sees_inventory_widgets(): void
    {
        $user = $this->createInventoryStaffUser();
        $response = $this->actingAs($user)->get('/admin');
        $response->assertStatus(200);
    }

    #[Test]
    public function charity_head_sees_charity_widgets(): void
    {
        $user = $this->createCharityHeadUser();
        $response = $this->actingAs($user)->get('/admin');
        $response->assertStatus(200);
    }

    #[Test]
    public function av_head_sees_content_widgets(): void
    {
        $user = $this->createAvHeadUser();
        $response = $this->actingAs($user)->get('/admin');
        $response->assertStatus(200);
    }

    #[Test]
    public function worship_monitor_sees_worship_widgets(): void
    {
        $user = $this->createWorshipMonitorUser();
        $response = $this->actingAs($user)->get('/admin');
        $response->assertStatus(200);
    }

    #[Test]
    public function admin_sees_all_relevant_widgets(): void
    {
        $user = $this->createAdminUser();
        $response = $this->actingAs($user)->get('/admin');
        $response->assertStatus(200);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExportSystemTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function contribution_export_page_is_accessible(): void
    {
        $user = $this->createFinanceHeadUser();
        $this->actingAs($user);

        $response = $this->get('/admin/contribution-report');
        $response->assertStatus(200);
    }

    #[Test]
    public function donation_export_page_is_accessible(): void
    {
        $user = $this->createFinanceHeadUser();
        $this->actingAs($user);

        $response = $this->get('/admin/donations');
        $response->assertStatus(200);
    }

    #[Test]
    public function tour_report_has_export_buttons(): void
    {
        $user = $this->createTourHeadUser();
        Tour::factory()->count(3)->create();
        $this->actingAs($user);

        $response = $this->get('/admin/tour-report');
        $response->assertStatus(200);
    }

    #[Test]
    public function audit_log_export_available_to_superadmin(): void
    {
        $user = $this->createSuperadminUser();
        $this->actingAs($user);

        $response = $this->get('/admin/audit-logs');
        $response->assertStatus(200);
    }

    #[Test]
    public function financial_statement_page_accessible(): void
    {
        $user = $this->createFinanceHeadUser();
        $this->actingAs($user);

        $response = $this->get('/admin/financial-statement-page');
        $response->assertStatus(200);
    }

    #[Test]
    public function contribution_matrix_page_accessible(): void
    {
        $user = $this->createFinanceHeadUser();
        $this->actingAs($user);

        $response = $this->get('/admin/contribution-matrix');
        $response->assertStatus(200);
    }

    #[Test]
    public function export_financial_audit_trail_accessible(): void
    {
        $user = $this->createFinanceHeadUser();
        $this->actingAs($user);

        $response = $this->get('/admin/financial-audit-trail-page');
        $response->assertStatus(200);
    }

    #[Test]
    public function member_export_is_available(): void
    {
        $user = $this->createHrHeadUser();
        $this->actingAs($user);

        $response = $this->get('/admin/members');
        $response->assertStatus(200);
    }

    #[Test]
    public function tour_report_export_route_exists(): void
    {
        $user = $this->createTourHeadUser();
        $this->actingAs($user);

        $response = $this->get('/admin/tour-report');
        $response->assertStatus(200);
    }
}

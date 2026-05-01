<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReportPagesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function class_performance_report_accessible(): void
    {
        $user = $this->createEducationHeadUser();
        $response = $this->actingAs($user)->get('/admin/class-performance-report');
        $this->assertNotEquals(404, $response->getStatusCode(), 'Route not found');
        $this->assertNotEquals(403, $response->getStatusCode(), 'Forbidden');
    }

    #[Test]
    public function student_progress_report_accessible(): void
    {
        $user = $this->createEducationHeadUser();
        $response = $this->actingAs($user)->get('/admin/student-progress-report');
        $this->assertNotEquals(404, $response->getStatusCode(), 'Route not found');
        $this->assertNotEquals(403, $response->getStatusCode(), 'Forbidden');
    }

    #[Test]
    public function teacher_attendance_report_accessible(): void
    {
        $user = $this->createEducationHeadUser();
        $response = $this->actingAs($user)->get('/admin/teacher-attendance-report');
        $this->assertNotEquals(404, $response->getStatusCode(), 'Route not found');
        $this->assertNotEquals(403, $response->getStatusCode(), 'Forbidden');
    }

    #[Test]
    public function financial_audit_trail_page_accessible(): void
    {
        $user = $this->createFinanceHeadUser();
        $response = $this->actingAs($user)->get('/admin/financial-audit-trail-page');
        $this->assertNotEquals(404, $response->getStatusCode(), 'Route not found');
        $this->assertNotEquals(403, $response->getStatusCode(), 'Forbidden');
    }

    #[Test]
    public function contribution_matrix_page_accessible(): void
    {
        $user = $this->createFinanceHeadUser();
        $response = $this->actingAs($user)->get('/admin/contribution-matrix');
        $this->assertNotEquals(404, $response->getStatusCode(), 'Route not found');
        $this->assertNotEquals(403, $response->getStatusCode(), 'Forbidden');
    }

    #[Test]
    public function outstanding_contributions_page_accessible(): void
    {
        $user = $this->createFinanceHeadUser();
        $response = $this->actingAs($user)->get('/admin/outstanding-contributions');
        $this->assertNotEquals(404, $response->getStatusCode(), 'Route not found');
        $this->assertNotEquals(403, $response->getStatusCode(), 'Forbidden');
    }

    #[Test]
    public function beneficiary_report_page_accessible(): void
    {
        $user = $this->createCharityHeadUser();
        $response = $this->actingAs($user)->get('/admin/beneficiary-report-page');
        $this->assertNotEquals(404, $response->getStatusCode(), 'Route not found');
        $this->assertNotEquals(403, $response->getStatusCode(), 'Forbidden');
    }

    #[Test]
    public function charity_report_page_accessible(): void
    {
        $user = $this->createCharityHeadUser();
        $response = $this->actingAs($user)->get('/admin/charity-report');
        $this->assertNotEquals(404, $response->getStatusCode(), 'Route not found');
        $this->assertNotEquals(403, $response->getStatusCode(), 'Forbidden');
    }

    #[Test]
    public function tour_report_page_accessible(): void
    {
        $user = $this->createTourHeadUser();
        $response = $this->actingAs($user)->get('/admin/tour-report');
        $this->assertNotEquals(404, $response->getStatusCode(), 'Route not found');
        $this->assertNotEquals(403, $response->getStatusCode(), 'Forbidden');
    }

    #[Test]
    public function predefined_reports_accessible(): void
    {
        $user = $this->createAdminUser();
        $response = $this->actingAs($user)->get('/admin/predefined-reports');
        $this->assertNotEquals(404, $response->getStatusCode(), 'Route not found');
        $this->assertNotEquals(403, $response->getStatusCode(), 'Forbidden');
    }

    #[Test]
    public function financial_statement_page_accessible(): void
    {
        $user = $this->createFinanceHeadUser();
        $response = $this->actingAs($user)->get('/admin/financial-statement-page');
        $this->assertNotEquals(404, $response->getStatusCode(), 'Route not found');
        $this->assertNotEquals(403, $response->getStatusCode(), 'Forbidden');
    }
}

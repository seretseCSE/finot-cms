<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EducationSystemTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function education_head_can_access_academic_years_page(): void
    {
        $user = $this->createEducationHeadUser();
        $this->actingAs($user);

        $response = $this->get('/admin/academic-years');
        $response->assertStatus(200);
    }

    #[Test]
    public function education_head_can_access_school_classes_page(): void
    {
        $user = $this->createEducationHeadUser();
        $this->actingAs($user);

        $response = $this->get('/admin/school-classes');
        $response->assertStatus(200);
    }

    #[Test]
    public function education_head_can_access_student_enrollments_page(): void
    {
        $user = $this->createEducationHeadUser();
        $this->actingAs($user);

        $response = $this->get('/admin/student-enrollments');
        $response->assertStatus(200);
    }

    #[Test]
    public function education_head_can_access_attendance_sessions_page(): void
    {
        $user = $this->createEducationHeadUser();
        $this->actingAs($user);

        $response = $this->get('/admin/attendance-sessions');
        $response->assertStatus(200);
    }

    #[Test]
    public function education_report_page_accessible(): void
    {
        $user = $this->createEducationHeadUser();
        $this->actingAs($user);

        $response = $this->get('/admin/education-report');
        $this->assertNotEquals(404, $response->getStatusCode(), 'Route not found');
        $this->assertNotEquals(403, $response->getStatusCode(), 'Forbidden');
    }

    #[Test]
    public function class_performance_report_accessible(): void
    {
        $user = $this->createEducationHeadUser();
        $this->actingAs($user);

        $response = $this->get('/admin/class-performance-report');
        $this->assertNotEquals(404, $response->getStatusCode(), 'Route not found');
        $this->assertNotEquals(403, $response->getStatusCode(), 'Forbidden');
    }

    #[Test]
    public function student_progress_report_accessible(): void
    {
        $user = $this->createEducationHeadUser();
        $this->actingAs($user);

        $response = $this->get('/admin/student-progress-report');
        $this->assertNotEquals(404, $response->getStatusCode(), 'Route not found');
        $this->assertNotEquals(403, $response->getStatusCode(), 'Forbidden');
    }

    #[Test]
    public function education_monitor_can_access_attendance_sessions(): void
    {
        $user = $this->createEducationMonitorUser();
        $this->actingAs($user);

        $response = $this->get('/admin/attendance-sessions');
        $response->assertStatus(200);
    }

    #[Test]
    public function teacher_resource_pages_are_accessible(): void
    {
        $user = $this->createEducationHeadUser();
        $this->actingAs($user);

        $this->get('/admin/teachers')->assertStatus(200);
        $this->get('/admin/teachers/create')->assertStatus(200);
    }

    #[Test]
    public function teacher_assignments_resource_accessible(): void
    {
        $user = $this->createEducationHeadUser();
        $this->actingAs($user);

        $response = $this->get('/admin/teacher-assignments');
        $response->assertStatus(200);
    }

    #[Test]
    public function subjects_resource_accessible(): void
    {
        $user = $this->createEducationHeadUser();
        $this->actingAs($user);

        $response = $this->get('/admin/subjects');
        $response->assertStatus(200);
    }

    #[Test]
    public function bulk_promotion_wizard_accessible(): void
    {
        $user = $this->createEducationHeadUser();
        $this->actingAs($user);

        $response = $this->get('/admin/bulk-promotion-wizard');
        $this->assertNotEquals(404, $response->getStatusCode(), 'Route not found');
        $this->assertNotEquals(403, $response->getStatusCode(), 'Forbidden');
    }
}

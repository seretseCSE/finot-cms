<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TeacherSystemTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function education_head_can_access_teachers_page(): void
    {
        $user = $this->createEducationHeadUser();
        $this->actingAs($user);

        $response = $this->get('/admin/teachers');
        $response->assertStatus(200);
    }

    #[Test]
    public function education_head_can_access_teachers_create_page(): void
    {
        $user = $this->createEducationHeadUser();
        $this->actingAs($user);

        $response = $this->get('/admin/teachers/create');
        $response->assertStatus(200);
    }

    #[Test]
    public function education_head_can_access_teacher_assignments_page(): void
    {
        $user = $this->createEducationHeadUser();
        $this->actingAs($user);

        $response = $this->get('/admin/teacher-assignments');
        $response->assertStatus(200);
    }

    #[Test]
    public function teacher_attendance_resource_accessible(): void
    {
        $user = $this->createEducationHeadUser();
        $this->actingAs($user);

        $response = $this->get('/admin/teacher-attendances');
        $response->assertStatus(200);
    }

    #[Test]
    public function teacher_model_can_be_created(): void
    {
        $member = Member::factory()->create();
        $teacher = Teacher::factory()->create([
            'member_id' => $member->id,
            'full_name' => 'Test Teacher',
        ]);

        $this->assertDatabaseHas('teachers', ['full_name' => 'Test Teacher']);
    }

    #[Test]
    public function teacher_edit_page_accessible(): void
    {
        $user = $this->createEducationHeadUser();
        $teacher = Teacher::factory()->create();
        $this->actingAs($user);

        $response = $this->get("/admin/teachers/{$teacher->id}/edit");
        $response->assertStatus(200);
    }

    #[Test]
    public function education_monitor_cannot_access_teacher_create(): void
    {
        $user = $this->createEducationMonitorUser();
        $this->actingAs($user);

        $response = $this->get('/admin/teachers/create');
        $response->assertStatus(403);
    }

    #[Test]
    public function teacher_assignments_create_page_accessible(): void
    {
        $user = $this->createEducationHeadUser();
        $this->actingAs($user);

        $response = $this->get('/admin/teacher-assignments/create');
        $response->assertStatus(200);
    }
}

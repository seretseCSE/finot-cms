<?php

namespace Tests\Feature;

use App\Models\AttendanceSession;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherAttendancePageTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->user->assignRole('education_head');
    }

    public function test_page_renders_for_authorized_user(): void
    {
        $response = $this->actingAs($this->user)->get('/admin/teacher-attendance-page');

        $response->assertStatus(200);
        $response->assertSee('Teacher Attendance');
        $response->assertSee('Select a session');
    }

    public function test_page_is_forbidden_without_permission(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get('/admin/teacher-attendance-page');

        $response->assertStatus(403);
    }
}

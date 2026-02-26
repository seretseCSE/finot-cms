<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Member;
use App\Models\Contribution;
use Database\Seeders\TestRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleBasedAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TestRoleSeeder::class);
    }

    public function test_superadmin_can_access_all_resources()
    {
        $superadmin = User::factory()->create();
        $superadmin->assignRole('superadmin');

        $response = $this->actingAs($superadmin)->get('/admin/users');
        $response->assertStatus(200);

        $response = $this->actingAs($superadmin)->get('/admin/system-settings');
        $response->assertStatus(200);
    }

    public function test_finance_head_can_only_access_finance_resources()
    {
        $financeHead = User::factory()->create();
        $financeHead->assignRole('finance_head');

        // Should have access to finance resources
        $response = $this->actingAs($financeHead)->get('/admin/contributions');
        $response->assertStatus(200);

        // Should not have access to HR resources
        $response = $this->actingAs($financeHead)->get('/admin/members');
        $response->assertStatus(403);
    }

    public function test_education_head_can_only_access_education_resources()
    {
        $educationHead = User::factory()->create();
        $educationHead->assignRole('education_head');

        // Should have access to education resources
        $response = $this->actingAs($educationHead)->get('/admin/classes');
        $response->assertStatus(200);

        $response = $this->actingAs($educationHead)->get('/admin/education-reports');
        $response->assertStatus(200);

        // Should not have access to finance resources
        $response = $this->actingAs($educationHead)->get('/admin/contributions');
        $response->assertStatus(403);
    }

    public function test_department_scope_filtering()
    {
        // Create users with different roles
        $hrHead = User::factory()->create();
        $hrHead->assignRole('hr_head');

        $financeHead = User::factory()->create();
        $financeHead->assignRole('finance_head');

        // Create members in different departments
        $hrMember = Member::factory()->create(['department_id' => 1]); // HR department
        $financeMember = Member::factory()->create(['department_id' => 2]); // Finance department

        // HR head should only see HR members
        $response = $this->actingAs($hrHead)->get('/admin/members');
        $response->assertSee($hrMember->full_name);
        $response->assertDontSee($financeMember->full_name);

        // Finance head should only see finance-related data
        $response = $this->actingAs($financeHead)->get('/admin/contributions');
        $response->assertStatus(200);
    }

    public function test_staff_has_limited_access()
    {
        $staff = User::factory()->create();
        $staff->assignRole('staff');

        // Staff should have basic access
        $response = $this->actingAs($staff)->get('/admin/dashboard');
        $response->assertStatus(200);

        // But not admin functions
        $response = $this->actingAs($staff)->get('/admin/users');
        $response->assertStatus(403);
    }
}

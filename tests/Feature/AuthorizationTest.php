<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function superadmin_can_access_all_resources(): void
    {
        $user = $this->createSuperadminUser();
        $this->actingAs($user);

        $this->get('/admin/members')->assertStatus(200);
        $this->get('/admin/users')->assertStatus(200);
        $this->get('/admin/contributions')->assertStatus(200);
        $this->get('/admin/tours')->assertStatus(200);
        $this->get('/admin/super-admin-dashboard')->assertStatus(200);
    }

    #[Test]
    public function admin_can_access_most_resources(): void
    {
        $user = $this->createAdminUser();
        $this->actingAs($user);

        $this->get('/admin/members')->assertStatus(200);
        $this->get('/admin/users')->assertStatus(200);
        $this->get('/admin/contributions')->assertStatus(200);
    }

    #[Test]
    public function admin_cannot_access_superadmin_only_pages(): void
    {
        $user = $this->createAdminUser();
        $this->actingAs($user);

        $this->get('/admin/super-admin-dashboard')->assertStatus(403);
    }

    #[Test]
    public function hr_head_can_access_member_management(): void
    {
        $user = $this->createHrHeadUser();
        $this->actingAs($user);

        $this->get('/admin/members')->assertStatus(200);
        $this->get('/admin/member-groups')->assertStatus(200);
    }

    #[Test]
    public function hr_head_cannot_access_financial_resources(): void
    {
        $user = $this->createHrHeadUser();
        $this->actingAs($user);

        $this->get('/admin/contributions')->assertStatus(403);
        $this->get('/admin/donations')->assertStatus(403);
    }

    #[Test]
    public function finance_head_can_access_financial_resources(): void
    {
        $user = $this->createFinanceHeadUser();
        $this->actingAs($user);

        $this->get('/admin/contributions')->assertStatus(200);
        $this->get('/admin/donations')->assertStatus(200);
        $this->get('/admin/financial-transactions')->assertStatus(200);
    }

    #[Test]
    public function finance_head_cannot_access_member_management(): void
    {
        $user = $this->createFinanceHeadUser();
        $this->actingAs($user);

        $this->get('/admin/members')->assertStatus(403);
    }

    #[Test]
    public function tour_head_can_access_tour_resources(): void
    {
        $user = $this->createTourHeadUser();
        $this->actingAs($user);

        $this->get('/admin/tours')->assertStatus(200);
        $this->get('/admin/tour-passengers')->assertStatus(200);
        $this->get('/admin/tour-attendances')->assertStatus(200);
    }

    #[Test]
    public function tour_head_cannot_access_education_resources(): void
    {
        $user = $this->createTourHeadUser();
        $this->actingAs($user);

        $this->get('/admin/academic-years')->assertStatus(403);
        $this->get('/admin/student-enrollments')->assertStatus(403);
    }

    #[Test]
    public function education_head_can_access_education_resources(): void
    {
        $user = $this->createEducationHeadUser();
        $this->actingAs($user);

        $this->get('/admin/academic-years')->assertStatus(200);
        $this->get('/admin/school-classes')->assertStatus(200);
        $this->get('/admin/student-enrollments')->assertStatus(200);
    }

    #[Test]
    public function staff_has_limited_access(): void
    {
        $user = $this->createStaffUser();
        $this->actingAs($user);

        $this->get('/admin/members')->assertStatus(403);
        $this->get('/admin/contributions')->assertStatus(403);
    }

    #[Test]
    public function department_secretary_can_access_limited_resources(): void
    {
        $user = $this->createDepartmentSecretaryUser();
        $this->actingAs($user);

        $this->get('/admin/documents')->assertStatus(200);
    }

    #[Test]
    public function inventory_staff_can_access_inventory_resources(): void
    {
        $user = $this->createInventoryStaffUser();
        $this->actingAs($user);

        $this->get('/admin/inventories')->assertStatus(200);
        $this->get('/admin/stock-movements')->assertStatus(200);
    }

    #[Test]
    public function charity_head_can_access_charity_resources(): void
    {
        $user = $this->createCharityHeadUser();
        $this->actingAs($user);

        $this->get('/admin/beneficiaries')->assertStatus(200);
        $this->get('/admin/aid-distributions')->assertStatus(200);
    }

    #[Test]
    public function av_head_can_access_content_resources(): void
    {
        $user = $this->createAvHeadUser();
        $this->actingAs($user);

        $this->get('/admin/media')->assertStatus(200);
        $this->get('/admin/blog-posts')->assertStatus(200);
        $this->get('/admin/announcements')->assertStatus(200);
    }

    #[Test]
    public function worship_monitor_can_access_worship_resources(): void
    {
        $user = $this->createWorshipMonitorUser();
        $this->actingAs($user);

        $this->get('/admin/songs')->assertStatus(200);
        $this->get('/admin/rehearsals')->assertStatus(200);
    }
}

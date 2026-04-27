<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\MemberGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MemberManagementTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_can_access_members_page(): void
    {
        $user = $this->createAdminUser();
        $this->actingAs($user);

        $response = $this->get('/admin/members');
        $response->assertStatus(200);
    }

    #[Test]
    public function admin_can_access_members_create_page(): void
    {
        $user = $this->createAdminUser();
        $this->actingAs($user);

        $response = $this->get('/admin/members/create');
        $response->assertStatus(200);
    }

    #[Test]
    public function admin_can_access_member_edit_page(): void
    {
        $user = $this->createAdminUser();
        $member = Member::factory()->create();
        $this->actingAs($user);

        $response = $this->get("/admin/members/{$member->id}/edit");
        $response->assertStatus(200);
    }

    #[Test]
    public function hr_head_can_view_members(): void
    {
        $user = $this->createHrHeadUser();
        $this->actingAs($user);

        $response = $this->get('/admin/members');
        $response->assertStatus(200);
    }

    #[Test]
    public function member_has_unique_phone_validation_at_database_level(): void
    {
        Member::factory()->create(['phone' => '+251911000001']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        Member::factory()->create(['phone' => '+251911000001']);
    }

    #[Test]
    public function member_group_resource_pages_are_accessible(): void
    {
        $user = $this->createAdminUser();
        $this->actingAs($user);

        $this->get('/admin/member-groups')->assertStatus(200);
        $this->get('/admin/member-groups/create')->assertStatus(200);
    }

    #[Test]
    public function member_timeline_page_exists(): void
    {
        $user = $this->createAdminUser();
        $member = Member::factory()->create();
        $this->actingAs($user);

        $response = $this->get("/admin/members/{$member->id}/timeline");
        $response->assertStatus(200);
    }

    #[Test]
    public function member_search_filters_work(): void
    {
        $user = $this->createAdminUser();
        Member::factory()->create(['first_name' => 'Searchable', 'status' => 'Active']);
        $this->actingAs($user);

        $response = $this->get('/admin/members?tableFilters[status][value]=Active');
        $response->assertStatus(200);
    }

    #[Test]
    public function member_soft_delete_works(): void
    {
        $member = Member::factory()->create();
        $member->delete();
        $this->assertSoftDeleted('members', ['id' => $member->id]);
    }

    #[Test]
    public function member_groups_can_be_listed(): void
    {
        $user = $this->createAdminUser();
        MemberGroup::factory()->count(3)->create();
        $this->actingAs($user);

        $response = $this->get('/admin/member-groups');
        $response->assertStatus(200);
    }

    #[Test]
    public function member_view_page_accessible(): void
    {
        $user = $this->createAdminUser();
        $member = Member::factory()->create();
        $this->actingAs($user);

        $response = $this->get("/admin/members/{$member->id}");
        $response->assertStatus(200);
    }
}

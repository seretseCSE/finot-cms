<?php

namespace Tests\Feature\Filament;

use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PageRenderTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function edit_member_page_mounts_without_throwing_for_authorized_user(): void
    {
        $user = $this->createHrHeadUser();
        $member = Member::factory()->create();

        $this->actingAs($user)
            ->get("/admin/members/{$member->id}/edit")
            ->assertOk();
    }

    #[Test]
    public function create_tour_page_mounts_without_throwing_for_authorized_user(): void
    {
        $user = $this->createTourHeadUser();

        $this->actingAs($user)
            ->get('/admin/tours/create')
            ->assertOk();
    }

    #[Test]
    public function list_contributions_page_mounts_without_throwing_for_authorized_user(): void
    {
        $user = $this->createFinanceHeadUser();

        $this->actingAs($user)
            ->get('/admin/contributions')
            ->assertOk();
    }
}

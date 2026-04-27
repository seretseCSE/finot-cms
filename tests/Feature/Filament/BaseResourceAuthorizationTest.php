<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\DocumentResource;
use App\Models\Department;
use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BaseResourceAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    // ─────────────────────────────────────────────────────────────────
    // 1. Role-based resource access via BaseResource::canViewAny()
    // ─────────────────────────────────────────────────────────────────

    #[Test]
    public function tour_head_can_access_tour_resource(): void
    {
        $user = $this->createTourHeadUser();
        $this->actingAs($user);

        $this->get('/admin/tours')->assertStatus(200);
    }

    #[Test]
    public function tour_head_cannot_access_member_resource(): void
    {
        $user = $this->createTourHeadUser();
        $this->actingAs($user);

        $this->get('/admin/members')->assertStatus(403);
    }

    #[Test]
    public function hr_head_can_access_member_resource(): void
    {
        $user = $this->createHrHeadUser();
        $this->actingAs($user);

        $this->get('/admin/members')->assertStatus(200);
    }

    #[Test]
    public function hr_head_cannot_access_financial_transaction_resource(): void
    {
        $user = $this->createHrHeadUser();
        $this->actingAs($user);

        $this->get('/admin/financial-transactions')->assertStatus(403);
    }

    #[Test]
    public function superadmin_can_access_all_resources(): void
    {
        $user = $this->createSuperadminUser();
        $this->actingAs($user);

        $this->get('/admin/tours')->assertStatus(200);
        $this->get('/admin/members')->assertStatus(200);
        $this->get('/admin/financial-transactions')->assertStatus(200);
        $this->get('/admin/inventories')->assertStatus(200);
        $this->get('/admin/documents')->assertStatus(200);
    }

    // ─────────────────────────────────────────────────────────────────
    // 2. Department-scoped visibility (DocumentResource)
    // ─────────────────────────────────────────────────────────────────

    #[Test]
    public function department_scoped_user_only_sees_own_department_documents(): void
    {
        $deptA = Department::factory()->create(['name_en' => 'Department A', 'code' => 'DEPA']);
        $deptB = Department::factory()->create(['name_en' => 'Department B', 'code' => 'DEPB']);

        $user = $this->createDepartmentSecretaryUser('Department A');
        $user->update(['department_id' => $deptA->id]);

        // Public document in Department B — should be visible
        $publicDoc = Document::factory()->create([
            'title' => 'Public Doc',
            'visibility' => 'Public',
            'department_id' => $deptB->id,
        ]);

        // Department Only document in Department A — should be visible
        $ownDeptDoc = Document::factory()->create([
            'title' => 'Own Dept Doc',
            'visibility' => 'Department Only',
            'department_id' => $deptA->id,
        ]);

        // Department Only document in Department B — should NOT be visible
        $otherDeptDoc = Document::factory()->create([
            'title' => 'Other Dept Doc',
            'visibility' => 'Department Only',
            'department_id' => $deptB->id,
        ]);

        $this->actingAs($user);

        $query = DocumentResource::getEloquentQuery();
        $visibleIds = $query->pluck('id')->toArray();

        $this->assertContains($publicDoc->id, $visibleIds, 'Public documents should be visible');
        $this->assertContains($ownDeptDoc->id, $visibleIds, 'Own department documents should be visible');
        $this->assertNotContains($otherDeptDoc->id, $visibleIds, 'Other department documents should be hidden');
    }

    #[Test]
    public function superadmin_sees_all_department_documents(): void
    {
        $deptA = Department::factory()->create(['name_en' => 'Dept A', 'code' => 'DA']);
        $deptB = Department::factory()->create(['name_en' => 'Dept B', 'code' => 'DB']);

        $docA = Document::factory()->create([
            'visibility' => 'Department Only',
            'department_id' => $deptA->id,
        ]);
        $docB = Document::factory()->create([
            'visibility' => 'Department Only',
            'department_id' => $deptB->id,
        ]);

        $superadmin = $this->createSuperadminUser();
        $this->actingAs($superadmin);

        $query = DocumentResource::getEloquentQuery();
        $visibleIds = $query->pluck('id')->toArray();

        $this->assertContains($docA->id, $visibleIds);
        $this->assertContains($docB->id, $visibleIds);
    }

    // ─────────────────────────────────────────────────────────────────
    // 3. Regression guard: BaseResource central auth, not inline role checks
    // ─────────────────────────────────────────────────────────────────

    #[Test]
    public function base_resource_uses_policy_when_available(): void
    {
        $user = $this->createTourHeadUser();
        $this->actingAs($user);

        // TourResource has TourPolicy registered. The policy grants viewAny
        // to tour_head via permission check, not a hardcoded role check.
        $this->get('/admin/tours')->assertStatus(200);
    }

    #[Test]
    public function base_resource_uses_permission_fallback_when_no_policy(): void
    {
        $financeHead = $this->createFinanceHeadUser();
        $this->actingAs($financeHead);

        // FinancialTransactionResource has no registered policy, so
        // BaseResource falls back to permission check.
        $this->get('/admin/financial-transactions')->assertStatus(200);
    }

    #[Test]
    public function unauthorized_user_cannot_access_any_resource(): void
    {
        // Create a user with NO roles assigned
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $this->get('/admin/tours')->assertStatus(403);
        $this->get('/admin/members')->assertStatus(403);
        $this->get('/admin/financial-transactions')->assertStatus(403);
    }
}

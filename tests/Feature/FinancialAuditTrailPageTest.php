<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\TestRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialAuditTrailPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TestRoleSeeder::class);
    }

    /**
     * Test that finance_head can access the financial audit trail page.
     */
    public function test_finance_head_can_access_financial_audit_trail_page(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'temp_password_changed' => true,
        ]);
        $user->assignRole('finance_head');

        $response = $this->actingAs($user)
            ->get('/admin/financial-audit-trail-page');

        $response->assertStatus(200);
        $response->assertSee('Financial Audit Trail');
    }

    /**
     * Test that unauthorized users cannot access the page.
     */
    public function test_unauthorized_users_cannot_access_financial_audit_trail_page(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'temp_password_changed' => true,
        ]);
        $user->assignRole('staff'); // Staff doesn't have access

        $response = $this->actingAs($user)
            ->get('/admin/financial-audit-trail-page');

        $response->assertStatus(403);
    }
}

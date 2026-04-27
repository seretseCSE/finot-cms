<?php

namespace Tests\Feature;

use App\Models\Donation;
use App\Models\User;
use Database\Seeders\TestRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DonationReportPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TestRoleSeeder::class);
    }

    /**
     * @test
     */
    public function donation_report_page_renders_for_finance_head(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'temp_password_changed' => true,
        ]);

        $user->assignRole('finance_head');

        Donation::factory()->create([
            'recorded_by' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->get('/admin/donation-report-page');

        $response->assertStatus(200);
        $response->assertSee('Donation Report');
    }
}

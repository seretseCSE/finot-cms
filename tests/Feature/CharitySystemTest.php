<?php

namespace Tests\Feature;

use App\Models\AidDistribution;
use App\Models\Beneficiary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharitySystemTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function charity_head_can_access_beneficiaries_page(): void
    {
        $user = $this->createCharityHeadUser();
        $this->actingAs($user);

        $response = $this->get('/admin/beneficiaries');
        $response->assertStatus(200);
    }

    #[Test]
    public function charity_head_can_access_beneficiaries_create_page(): void
    {
        $user = $this->createCharityHeadUser();
        $this->actingAs($user);

        $response = $this->get('/admin/beneficiaries/create');
        $response->assertStatus(200);
    }

    #[Test]
    public function charity_head_can_access_aid_distributions_page(): void
    {
        $user = $this->createCharityHeadUser();
        $this->actingAs($user);

        $response = $this->get('/admin/aid-distributions');
        $response->assertStatus(200);
    }

    #[Test]
    public function charity_head_can_access_aid_distributions_create_page(): void
    {
        $user = $this->createCharityHeadUser();
        $this->actingAs($user);

        $response = $this->get('/admin/aid-distributions/create');
        $response->assertStatus(200);
    }

    #[Test]
    public function beneficiary_report_page_accessible(): void
    {
        $user = $this->createCharityHeadUser();
        $this->actingAs($user);

        $response = $this->get('/admin/beneficiary-report-page');
        $response->assertStatus(200);
    }

    #[Test]
    public function beneficiary_model_calculates_total_aid(): void
    {
        $beneficiary = Beneficiary::factory()->create();
        AidDistribution::factory()->count(3)->create([
            'beneficiary_id' => $beneficiary->id,
            'amount' => 100.00,
        ]);

        $this->assertEquals(300.00, $beneficiary->fresh()->total_aid_received);
    }

    #[Test]
    public function aid_distribution_can_be_locked_and_unlocked(): void
    {
        $charityHead = $this->createCharityHeadUser();
        $distribution = AidDistribution::factory()->create();

        $this->assertFalse($distribution->fresh()->is_locked);

        $distribution->lock($charityHead);
        $this->assertTrue($distribution->fresh()->is_locked);

        $distribution->unlock($charityHead);
        $this->assertFalse($distribution->fresh()->is_locked);
    }
}

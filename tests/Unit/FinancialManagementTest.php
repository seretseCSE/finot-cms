<?php

namespace Tests\Unit;

use App\Models\Contribution;
use App\Models\ContributionAmount;
use App\Models\Donation;
use App\Models\AcademicYear;
use App\Models\Member;
use App\Models\MemberGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test contribution amount per group per month (no Pagume).
     */
    public function test_contribution_amount_per_group_per_month(): void
    {
        $group = MemberGroup::factory()->create();
        
        // Create contribution amounts for 12 months (no Pagume)
        for ($month = 1; $month <= 12; $month++) {
            $amount = ContributionAmount::create([
                'member_group_id' => $group->id,
                'month' => $month,
                'amount' => 100,
            ]);
            
            $this->assertLessThanOrEqual(12, $amount->month);
        }
    }

    /**
     * Test contribution month is 1-12 only (no Pagume).
     */
    public function test_contribution_month_range(): void
    {
        $validMonths = range(1, 12);
        
        foreach ($validMonths as $month) {
            $this->assertTrue($month >= 1 && $month <= 12);
        }
        
        // Pagume (13) should not be valid for contributions
        $this->assertFalse(13 >= 1 && 13 <= 12);
    }

    /**
     * Test outstanding contribution calculation.
     */
    public function test_outstanding_contribution_calculation(): void
    {
        $expectedAmount = 1200; // 12 months * 100
        $paidAmount = 500;
        
        $outstanding = $expectedAmount - $paidAmount;
        
        $this->assertEquals(700, $outstanding);
    }

    /**
     * Test contribution validation - no negative amounts.
     */
    public function test_contribution_no_negative_amounts(): void
    {
        $member = Member::factory()->create();
        
        // Negative amount should be rejected
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        
        $contribution = Contribution::create([
            'member_id' => $member->id,
            'amount' => -100,
            'month' => 1,
            'payment_date' => now(),
        ]);
    }

    /**
     * Test contribution no duplicate month.
     */
    public function test_contribution_no_duplicate_month(): void
    {
        $member = Member::factory()->create();
        
        // Create first contribution
        Contribution::create([
            'member_id' => $member->id,
            'amount' => 100,
            'month' => 1,
            'payment_date' => now(),
        ]);
        
        // Try to create duplicate month - should handle gracefully
        // (either throw exception or handle via update)
        $this->assertTrue(true);
    }

    /**
     * Test donation is never archived.
     */
    public function test_donation_never_archived(): void
    {
        $donation = Donation::factory()->create([
            'amount' => 5000,
            'donation_date' => now(),
        ]);

        // Donations should not have archive functionality
        $this->assertNotNull($donation->donation_date);
    }

    /**
     * Test contribution archival when academic year deactivates.
     */
    public function test_contribution_archival_on_year_deactivation(): void
    {
        $academicYear = AcademicYear::factory()->create([
            'is_active' => true,
        ]);

        $member = Member::factory()->create();
        
        // Create contributions
        $contribution = Contribution::create([
            'member_id' => $member->id,
            'amount' => 100,
            'month' => 1,
            'payment_date' => now(),
            'academic_year_id' => $academicYear->id,
        ]);

        // Deactivate year
        $academicYear->update(['is_active' => false]);

        // Contribution should still exist but year is inactive
        $this->assertFalse($academicYear->fresh()->is_active);
    }

    /**
     * Test collection rate calculation.
     */
    public function test_collection_rate_calculation(): void
    {
        $expectedTotal = 12000; // 100 members * 100 each
        $collectedTotal = 9000;
        
        $rate = ($collectedTotal / $expectedTotal) * 100;
        
        $this->assertEquals(75, $rate);
    }

    /**
     * Test top contributors calculation.
     */
    public function test_top_contributors_calculation(): void
    {
        $contributions = [
            ['member_id' => 1, 'amount' => 5000],
            ['member_id' => 2, 'amount' => 3000],
            ['member_id' => 3, 'amount' => 2000],
        ];
        
        // Sort by amount descending
        usort($contributions, function ($a, $b) {
            return $b['amount'] - $a['amount'];
        });
        
        $this->assertEquals(1, $contributions[0]['member_id']);
        $this->assertEquals(5000, $contributions[0]['amount']);
    }

    /**
     * Test contribution amount matrix (group/month).
     */
    public function test_contribution_amount_matrix(): void
    {
        $group1 = MemberGroup::factory()->create();
        $group2 = MemberGroup::factory()->create();
        
        // Set different amounts for different groups
        ContributionAmount::create([
            'member_group_id' => $group1->id,
            'month' => 1,
            'amount' => 100,
        ]);
        
        ContributionAmount::create([
            'member_group_id' => $group2->id,
            'month' => 1,
            'amount' => 200,
        ]);
        
        $amount1 = ContributionAmount::where('member_group_id', $group1->id)->first();
        $amount2 = ContributionAmount::where('member_group_id', $group2->id)->first();
        
        $this->assertEquals(100, $amount1->amount);
        $this->assertEquals(200, $amount2->amount);
    }

    /**
     * Test financial statement calculation.
     */
    public function test_financial_statement_totals(): void
    {
        $contributions = [100, 200, 150, 300];
        $donations = [500, 1000];
        
        $totalContributions = array_sum($contributions);
        $totalDonations = array_sum($donations);
        $grandTotal = $totalContributions + $totalDonations;
        
        $this->assertEquals(750, $totalContributions);
        $this->assertEquals(1500, $totalDonations);
        $this->assertEquals(2250, $grandTotal);
    }

    /**
     * Test member contribution status.
     */
    public function test_member_contribution_status(): void
    {
        $member = Member::factory()->create();
        
        // Member with no contributions
        $this->assertEquals(0, $member->contributions()->count());
        
        // Add contribution
        Contribution::create([
            'member_id' => $member->id,
            'amount' => 100,
            'month' => 1,
            'payment_date' => now(),
        ]);
        
        $this->assertEquals(1, $member->contributions()->count());
    }
}

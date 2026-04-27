<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ContributionAmount;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\MemberGroupAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ContributionValidationTest extends TestCase
{
    use RefreshDatabase;

    private function createScenario(float $expectedAmount): array
    {
        $group = MemberGroup::factory()->create();
        $academicYear = AcademicYear::factory()->create(['status' => 'Active']);
        $member = Member::factory()->create();
        $user = User::factory()->create();

        MemberGroupAssignment::create([
            'member_id' => $member->id,
            'group_id' => $group->id,
            'effective_from' => now()->subMonth(),
            'effective_to' => null,
            'assigned_by' => $user->id,
        ]);

        ContributionAmount::factory()->create([
            'group_id' => $group->id,
            'academic_year_id' => $academicYear->id,
            'month_name' => 'Meskerem',
            'amount' => $expectedAmount,
            'effective_from' => now()->subMonth()->format('Y-m-d'),
            'effective_to' => null,
        ]);

        return [$member, $academicYear, $group];
    }

    #[Test]
    public function contribution_accepts_exact_expected_amount(): void
    {
        [$member, $academicYear] = $this->createScenario(100.00);

        $this->setRequestData($member->id, $academicYear->id, 'Meskerem');

        $validator = validator([
            'member_id' => $member->id,
            'academic_year_id' => $academicYear->id,
            'month_name' => 'Meskerem',
            'amount' => '100.00',
        ], [
            'amount' => $this->getAmountValidationRule(),
        ]);

        $this->assertFalse($validator->fails());
    }

    #[Test]
    public function contribution_rejects_amount_below_expected(): void
    {
        [$member, $academicYear] = $this->createScenario(100.00);

        $this->setRequestData($member->id, $academicYear->id, 'Meskerem');

        $validator = validator([
            'member_id' => $member->id,
            'academic_year_id' => $academicYear->id,
            'month_name' => 'Meskerem',
            'amount' => '50.00',
        ], [
            'amount' => $this->getAmountValidationRule(),
        ]);

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString('must be exactly Birr 100', $validator->errors()->first('amount'));
    }

    #[Test]
    public function contribution_rejects_amount_above_expected(): void
    {
        [$member, $academicYear] = $this->createScenario(100.00);

        $this->setRequestData($member->id, $academicYear->id, 'Meskerem');

        $validator = validator([
            'member_id' => $member->id,
            'academic_year_id' => $academicYear->id,
            'month_name' => 'Meskerem',
            'amount' => '150.00',
        ], [
            'amount' => $this->getAmountValidationRule(),
        ]);

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString('must be exactly Birr 100', $validator->errors()->first('amount'));
    }

    #[Test]
    public function contribution_allows_any_amount_when_no_contribution_amount_defined(): void
    {
        $group = MemberGroup::factory()->create();
        $academicYear = AcademicYear::factory()->create(['status' => 'Active']);
        $member = Member::factory()->create();
        $user = User::factory()->create();

        MemberGroupAssignment::create([
            'member_id' => $member->id,
            'group_id' => $group->id,
            'effective_from' => now()->subMonth(),
            'effective_to' => null,
            'assigned_by' => $user->id,
        ]);

        // No ContributionAmount record created
        $this->setRequestData($member->id, $academicYear->id, 'Meskerem');

        $validator = validator([
            'member_id' => $member->id,
            'academic_year_id' => $academicYear->id,
            'month_name' => 'Meskerem',
            'amount' => '999.99',
        ], [
            'amount' => $this->getAmountValidationRule(),
        ]);

        $this->assertFalse($validator->fails());
    }

    private function setRequestData(int $memberId, int $academicYearId, string $monthName): void
    {
        request()->merge([
            'member_id' => $memberId,
            'academic_year_id' => $academicYearId,
            'month_name' => $monthName,
        ]);
    }

    /**
     * Build the validation closure used in ContributionResource form.
     */
    private function getAmountValidationRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            $memberId = request('member_id');
            $academicYearId = request('academic_year_id');
            $monthName = request('month_name');

            if (! $memberId || ! $academicYearId || ! $monthName) {
                return;
            }

            $member = \App\Models\Member::find($memberId);
            $currentGroupId = $member?->currentGroupAssignment?->group_id;

            if (! $currentGroupId) {
                return;
            }

            $expectedAmount = \App\Models\ContributionAmount::where('group_id', $currentGroupId)
                ->where('academic_year_id', $academicYearId)
                ->where('month_name', $monthName)
                ->where(function ($query) {
                    $query->whereNull('effective_to')
                        ->orWhere('effective_to', '>=', now()->format('Y-m-d'));
                })
                ->first()?->amount;

            if ($expectedAmount && (float) $value !== (float) $expectedAmount) {
                $fail("The amount must be exactly Birr {$expectedAmount} for this member's group and month ({$monthName}).");
            }
        };
    }
}

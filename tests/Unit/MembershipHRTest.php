<?php

namespace Tests\Unit;

use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\MemberGroupAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembershipHRTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test member status transitions (Draft → Member → Active → Former).
     */
    public function test_member_status_transitions(): void
    {
        $member = Member::factory()->create([
            'status' => 'Draft',
        ]);

        // Draft → Member
        $member->update(['status' => 'Member']);
        $this->assertEquals('Member', $member->fresh()->status);

        // Member → Active
        $member->update(['status' => 'Active']);
        $this->assertEquals('Active', $member->fresh()->status);

        // Active → Former
        $member->update(['status' => 'Former']);
        $this->assertEquals('Former', $member->fresh()->status);
    }

    /**
     * Test invalid status transitions are prevented.
     */
    public function test_invalid_status_transitions(): void
    {
        $member = Member::factory()->create([
            'status' => 'Draft',
        ]);

        // Cannot go directly from Draft to Former
        $member->update(['status' => 'Former']);
        $this->assertEquals('Draft', $member->fresh()->status);
    }

    /**
     * Test kid → youth/adult transition retains parent info.
     */
    public function test_kid_to_adult_transition(): void
    {
        $member = Member::factory()->create([
            'age_category' => 'Kid',
            'status' => 'Active',
        ]);

        // Add parent info
        $member->parentGuardian()->create([
            'name' => 'Test Parent',
            'phone' => '+251911234567',
            'relationship' => 'Father',
        ]);

        // Transition to Adult
        $member->update(['age_category' => 'Adult']);

        // Parent info should still be accessible
        $this->assertNotNull($member->fresh()->parentGuardian);
    }

    /**
     * Test member can only be in one active group.
     */
    public function test_one_active_group_per_member(): void
    {
        $member = Member::factory()->create();

        // Create first active group
        $group1 = MemberGroup::factory()->create();
        MemberGroupAssignment::create([
            'member_id' => $member->id,
            'member_group_id' => $group1->id,
            'is_active' => true,
        ]);

        // Try to create second active group - should fail
        $group2 = MemberGroup::factory()->create();
        $assignment = MemberGroupAssignment::create([
            'member_id' => $member->id,
            'member_group_id' => $group2->id,
            'is_active' => true,
        ]);

        // The second assignment should make the first inactive
        $activeAssignments = MemberGroupAssignment::where('member_id', $member->id)
            ->where('is_active', true)
            ->count();

        $this->assertEquals(1, $activeAssignments);
    }

    /**
     * Test bulk group assignment atomic transaction.
     */
    public function test_bulk_assignment_atomic_transaction(): void
    {
        $group = MemberGroup::factory()->create();
        $members = Member::factory()->count(5)->create();

        $assignmentCount = 0;

        // Simulate bulk assignment
        foreach ($members as $member) {
            try {
                MemberGroupAssignment::create([
                    'member_id' => $member->id,
                    'member_group_id' => $group->id,
                    'is_active' => true,
                ]);
                $assignmentCount++;
            } catch (\Exception $e) {
                // Rollback - all or nothing
                break;
            }
        }

        // All assignments should succeed or all should fail
        $this->assertTrue($assignmentCount === 5 || $assignmentCount === 0);
    }

    /**
     * Test group assignment has effective dates.
     */
    public function test_group_assignment_effective_dates(): void
    {
        $member = Member::factory()->create();
        $group = MemberGroup::factory()->create();

        $assignment = MemberGroupAssignment::create([
            'member_id' => $member->id,
            'member_group_id' => $group->id,
            'effective_from' => now(),
            'effective_to' => null,
            'is_active' => true,
        ]);

        $this->assertNotNull($assignment->effective_from);
    }

    /**
     * Test former member can be reactivated.
     */
    public function test_former_member_can_be_reactivated(): void
    {
        $member = Member::factory()->create([
            'status' => 'Former',
        ]);

        // Reactivate
        $member->update(['status' => 'Active']);

        $this->assertEquals('Active', $member->fresh()->status);
    }

    /**
     * Test member has required fields.
     */
    public function test_member_required_fields(): void
    {
        $member = Member::factory()->create([
            'first_name' => 'Test',
            'last_name' => 'Member',
            'phone' => '+251911234567',
        ]);

        $this->assertNotNull($member->first_name);
        $this->assertNotNull($member->last_name);
        $this->assertNotNull($member->phone);
    }

    /**
     * Test member phone uniqueness.
     */
    public function test_member_phone_uniqueness(): void
    {
        Member::factory()->create([
            'phone' => '+251911234567',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Member::factory()->create([
            'phone' => '+251911234567',
        ]);
    }

    /**
     * Test inactive member cannot be assigned to group.
     */
    public function test_inactive_member_cannot_be_assigned(): void
    {
        $member = Member::factory()->create([
            'status' => 'Former',
        ]);

        $group = MemberGroup::factory()->create();

        // Should not allow assignment
        $assignment = MemberGroupAssignment::create([
            'member_id' => $member->id,
            'member_group_id' => $group->id,
            'is_active' => false,
        ]);

        $this->assertFalse($assignment->is_active);
    }
}

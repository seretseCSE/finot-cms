<?php

namespace App\Actions\Members;

use App\Models\Member;
use App\Models\MemberParentGuardian;
use App\Models\ParentModel;
use Illuminate\Support\Facades\Log;

class SyncParentGuardiansAction
{
    /**
     * Sync parent/guardian relationships for a member.
     *
     * @param  Member  $member
     * @param  array<int, array<string, mixed>>  $guardians
     * @param  bool  $replaceExisting  Whether to delete existing relationships before syncing
     */
    public function execute(Member $member, array $guardians, bool $replaceExisting = false): void
    {
        Log::info('SyncParentGuardiansAction executed', [
            'member_id' => $member->id,
            'guardian_count' => count($guardians),
            'replace_existing' => $replaceExisting,
        ]);

        if ($replaceExisting) {
            MemberParentGuardian::where('member_id', $member->id)->delete();
        }

        foreach ($guardians as $parentData) {
            if (empty($parentData['parent_name'])) {
                continue;
            }

            $this->processGuardian($member, $parentData);
        }
    }

    /**
     * Process a single guardian record.
     *
     * @param  Member  $member
     * @param  array<string, mixed>  $data
     */
    protected function processGuardian(Member $member, array $data): void
    {
        $parentName = $data['parent_name'];
        $relationship = $data['relationship'] ?? 'Guardian';
        $phone = $data['parent_phone'] ?? '';
        $parentId = $data['parent_id'] ?? null;

        if (! empty($parentId)) {
            $this->linkToExistingParent($member, $parentId, $parentName, $relationship, $phone);

            return;
        }

        $existingParent = ParentModel::byPhone($phone)->first();

        if ($existingParent) {
            $this->linkToExistingParent($member, $existingParent->id, $parentName, $relationship, $phone);

            return;
        }

        $this->createAndLinkNewParent($member, $parentName, $relationship, $phone);
    }

    /**
     * Link a member to an existing parent record.
     */
    protected function linkToExistingParent(Member $member, int $parentId, string $parentName, string $relationship, string $phone): void
    {
        MemberParentGuardian::create([
            'member_id' => $member->id,
            'parent_id' => $parentId,
            'parent_name' => $parentName,
            'relationship' => $relationship,
            'phone' => $phone,
            'is_external' => false,
        ]);

        $parent = ParentModel::find($parentId);
        if ($parent) {
            $parent->updateMemberCount();
            app(\App\Services\Identity\ProvisionParentUser::class)->sync($parent);
        }
    }

    /**
     * Create a new parent record and link the member to it.
     */
    protected function createAndLinkNewParent(Member $member, string $parentName, string $relationship, string $phone): void
    {
        $parent = ParentModel::create([
            'full_name' => $parentName,
            'phone' => $phone,
            'relationship_type' => $relationship,
            'is_active' => true,
        ]);

        MemberParentGuardian::create([
            'member_id' => $member->id,
            'parent_id' => $parent->id,
            'parent_name' => $parentName,
            'relationship' => $relationship,
            'phone' => $phone,
            'is_external' => false,
        ]);

        $parent->updateMemberCount();
        app(\App\Services\Identity\ProvisionParentUser::class)->sync($parent);
    }
}

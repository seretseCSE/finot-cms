<?php

namespace App\Observers;

use App\Models\MemberParentGuardian;
use App\Models\ParentModel;
use App\Models\StudentEnrollment;
use App\Services\Identity\ProvisionParentUser;
use App\Services\Identity\ProvisionStudentUser;

class StudentEnrollmentObserver
{
    public function saved(StudentEnrollment $enrollment): void
    {
        $member = $enrollment->member;
        if ($member) {
            app(ProvisionStudentUser::class)->sync($member);

            $parentIds = MemberParentGuardian::query()
                ->where('member_id', $member->id)
                ->whereNotNull('parent_id')
                ->pluck('parent_id');

            ParentModel::query()->whereIn('id', $parentIds)->each(function (ParentModel $parent) {
                app(ProvisionParentUser::class)->sync($parent);
            });
        }
    }
}

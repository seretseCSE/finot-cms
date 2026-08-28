<?php

namespace App\Observers;

use App\Models\StudentEnrollment;
use App\Services\Identity\ProvisionStudentUser;

class StudentEnrollmentObserver
{
    public function saved(StudentEnrollment $enrollment): void
    {
        $member = $enrollment->member;
        if ($member) {
            app(ProvisionStudentUser::class)->sync($member);
        }
    }
}

<?php

namespace App\Observers;

use App\Models\Member;
use App\Models\Teacher;

class MemberObserver
{
    public function updated(Member $member): void
    {
        if (! $member->wasChanged(['phone', 'first_name', 'father_name', 'grandfather_name'])) {
            return;
        }

        $teacher = Teacher::query()->where('member_id', $member->getKey())->first();

        if (! $teacher) {
            return;
        }

        $teacher->update([
            'full_name' => $member->full_name,
            'phone' => $member->phone,
        ]);
    }
}

<?php

namespace App\Services\Learning;

use App\Models\MemberParentGuardian;
use App\Models\StudentEnrollment;
use App\Models\User;
use Illuminate\Support\Collection;

class LearningAccess
{
    /**
     * Class IDs the user may see class content for.
     *
     * @return list<int>
     */
    public function classIdsForUser(?User $user): array
    {
        if (! $user) {
            return [];
        }

        if ($user->member_id) {
            return StudentEnrollment::query()
                ->active()
                ->where('member_id', $user->member_id)
                ->pluck('class_id')
                ->filter()
                ->unique()
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        }

        if ($user->parent_id) {
            $memberIds = MemberParentGuardian::query()
                ->where('parent_id', $user->parent_id)
                ->pluck('member_id');

            return StudentEnrollment::query()
                ->active()
                ->whereIn('member_id', $memberIds)
                ->pluck('class_id')
                ->filter()
                ->unique()
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        }

        return [];
    }

    /**
     * Linked children for a parent user.
     *
     * @return Collection<int, \App\Models\Member>
     */
    public function childrenForParent(?User $user): Collection
    {
        if (! $user?->parent_id) {
            return collect();
        }

        return MemberParentGuardian::query()
            ->with('member')
            ->where('parent_id', $user->parent_id)
            ->get()
            ->pluck('member')
            ->filter()
            ->unique('id')
            ->values();
    }
}

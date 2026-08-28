<?php

namespace App\Services\Messages;

use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class RecipientResolver
{
    /**
     * @param  array{global?: bool, department_id?: int|null, class_ids?: array, group_ids?: array, member_ids?: array}  $audience
     * @return Collection<int, Member>
     */
    public function resolve(User $sender, array $audience): Collection
    {
        $isGlobal = (bool) ($audience['global'] ?? false);

        if ($isGlobal && ! $sender->can('messages.broadcast_global') && ! $sender->hasRole('superadmin')) {
            throw ValidationException::withMessages(['audience' => 'Global broadcast is not allowed.']);
        }

        $query = Member::query()->where('status', 'Active');

        if (! $isGlobal) {
            $departmentId = $audience['department_id'] ?? $sender->department_id;
            if ($departmentId) {
                $query->where('department_id', $departmentId);
            }

            $classIds = array_filter($audience['class_ids'] ?? []);
            if ($classIds) {
                $query->whereHas('studentEnrollments', function ($q) use ($classIds) {
                    $q->whereIn('class_id', $classIds)->where('status', 'Enrolled')->whereNull('removed_at');
                });
            }

            $groupIds = array_filter($audience['group_ids'] ?? []);
            if ($groupIds) {
                $query->whereHas('groupAssignments', function ($q) use ($groupIds) {
                    $q->whereIn('group_id', $groupIds)->where(function ($inner) {
                        $inner->whereNull('effective_to')->orWhere('effective_to', '>=', now());
                    });
                });
            }

            $memberIds = array_filter($audience['member_ids'] ?? []);
            if ($memberIds) {
                $query->whereIn('id', $memberIds);
            }
        }

        return $query->get();
    }
}

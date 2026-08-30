<?php

namespace App\Services\Messages;

use App\Models\Member;
use App\Models\User;
use App\Support\RoleGate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class RecipientResolver
{
    /**
     * @param  array{global?: bool, department_id?: int|null, class_ids?: array, group_ids?: array, member_ids?: array, search?: string, member_types?: array}  $audience
     * @return Collection<int, Member>
     */
    public function resolve(User $sender, array $audience): Collection
    {
        $isGlobal = (bool) ($audience['global'] ?? false);

        if ($isGlobal && ! $this->senderCanBroadcastGlobal($sender)) {
            throw ValidationException::withMessages(['audience' => 'Global broadcast is not allowed.']);
        }

        $query = Member::query()->where('status', 'Active');

        if (! $isGlobal) {
            $departmentId = $audience['department_id'] ?? $sender->department_id;
            if ($departmentId) {
                $query->where('department_id', $departmentId);
            } elseif (! $this->senderCanBroadcastGlobal($sender)) {
                throw ValidationException::withMessages([
                    'audience' => 'Assign a department before sending to members, or use a global broadcast.',
                ]);
            }
        } elseif (! empty($audience['department_id'])) {
            $query->where('department_id', $audience['department_id']);
        }

        $this->applyFilters($query, $audience);

        return $query->get();
    }

    /**
     * @param  array<string, mixed>  $audience
     */
    protected function applyFilters(Builder $query, array $audience): void
    {
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

        $memberTypes = array_filter($audience['member_types'] ?? []);
        if ($memberTypes) {
            $query->whereIn('member_type', $memberTypes);
        }

        $search = trim((string) ($audience['search'] ?? ''));
        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function (Builder $q) use ($like) {
                $q->where('first_name', 'like', $like)
                    ->orWhere('father_name', 'like', $like)
                    ->orWhere('grandfather_name', 'like', $like)
                    ->orWhere('member_code', 'like', $like);
            });
        }
    }

    protected function senderCanBroadcastGlobal(User $sender): bool
    {
        return $sender->can('messages.broadcast_global')
            || $sender->hasAnyRole(RoleGate::GLOBAL_BROADCAST_ROLES);
    }
}

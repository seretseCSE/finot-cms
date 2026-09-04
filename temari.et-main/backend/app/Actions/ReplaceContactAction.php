<?php

namespace App\Actions;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Membership;
use App\Models\School;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;

/**
 * Replaces the person holding a school/branch contact role (principal,
 * school_admin, director): deactivates the current active holder(s) of that
 * role in that scope, then provisions the new contact (create/reuse user,
 * membership, employee, and SMS a set-password link) via
 * {@see ProvisionContactUserAction}.
 */
class ReplaceContactAction
{
    public function __construct(private readonly ProvisionContactUserAction $provisionContact)
    {
    }

    public function execute(
        string $name,
        string $phone,
        Role $role,
        User $actor,
        School $school,
        ?Branch $branch = null,
    ): User {
        return DB::transaction(function () use ($name, $phone, $role, $actor, $school, $branch): User {
            $current = Membership::query()
                ->where('school_id', $school->id)
                ->where('role', $role->value)
                ->where('is_active', true)
                ->when($branch !== null, fn ($q) => $q->where('branch_id', $branch->id))
                ->when($branch === null, fn ($q) => $q->whereNull('branch_id'))
                ->get();

            foreach ($current as $membership) {
                $membership->update(['is_active' => false]);

                if ($membership->branch_id !== null && $membership->user_id !== null) {
                    Employee::syncBranchAccess($membership->user_id, $membership->branch_id, false);
                }

                ActivityLogger::log(
                    $actor,
                    'contact.replaced',
                    $membership,
                    ['user_id' => $membership->user_id, 'role' => $role->value],
                    $school->id,
                    $membership->branch_id,
                );
            }

            return $this->provisionContact->execute(
                name: $name,
                phone: $phone,
                role: $role,
                school: $school,
                branch: $branch,
            );
        });
    }
}

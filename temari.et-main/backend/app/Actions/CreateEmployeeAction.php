<?php

namespace App\Actions;

use App\Models\Branch;
use App\Models\Employee;
use App\Services\EmployeeAccountProvisioner;
use App\Support\PhoneNumber;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates a branch staff member: stores the HR profile with its positions/
 * qualifications/compensation lines and — when the branch's account policy
 * covers the person's job titles (EmployeeAccountProvisioner) — provisions
 * (or reuses) their user account, derives branch memberships from the
 * role-mapped job titles (SyncPositionMembershipsAction), and texts a
 * password-setup link for new accounts. Employees outside the policy get an
 * HR file with no login.
 */
class CreateEmployeeAction
{
    public function __construct(
        private readonly EmployeeAccountProvisioner $provisioner,
        private readonly SyncPositionMembershipsAction $syncMemberships,
    ) {}

    private const CHILD_KEYS = ['positions', 'qualifications', 'allowances', 'deductions', 'teacher_subjects', 'create_user_account'];

    /**
     * @param  array<string, mixed>  $data  Validated payload including `positions` + profile fields.
     */
    public function execute(Branch $branch, array $data): Employee
    {
        return DB::transaction(function () use ($branch, $data): Employee {
            // One HR file per branch per person (ADR-011): the same phone at
            // ANOTHER branch is the same person hired twice (fine); at THIS
            // branch it's a duplicate entry.
            $phone = PhoneNumber::normalize((string) $data['phone']) ?? trim((string) $data['phone']);

            if (Employee::where('branch_id', $branch->id)->where('phone', $phone)->exists()) {
                throw ValidationException::withMessages([
                    'phone' => ['An employee with this phone number already exists at this branch.'],
                ]);
            }

            $activeTitles = collect($data['positions'])
                ->filter(fn (array $p): bool => empty($p['ended_on']))
                ->pluck('job_title')
                ->all();

            $user = $this->provisioner->shouldProvision($branch, $activeTitles, $this->requested($data))
                ? $this->provisioner->resolveUser((string) $data['phone'], $data)
                : null;

            $employee = Employee::create([
                ...Arr::except($data, self::CHILD_KEYS),
                'user_id' => $user?->id,
                'school_id' => $branch->school_id,
                'branch_id' => $branch->id,
            ]);

            $employee->syncPositions($data['positions']);
            $employee->syncQualifications($data['qualifications'] ?? []);
            $employee->syncAllowances($data['allowances'] ?? []);
            $employee->syncDeductions($data['deductions'] ?? []);
            $employee->syncTeacherSubjects($data['teacher_subjects'] ?? []);

            $this->syncMemberships->execute($employee);

            return $employee->load('user');
        });
    }

    private function requested(array $data): ?bool
    {
        return array_key_exists('create_user_account', $data)
            ? (bool) $data['create_user_account']
            : null;
    }
}

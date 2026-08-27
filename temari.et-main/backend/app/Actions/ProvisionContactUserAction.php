<?php

namespace App\Actions;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Membership;
use App\Models\School;
use App\Models\User;
use App\Services\PasswordSetupService;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;

/**
 * Provisions (or reuses) a user account for a school contact — principal,
 * school_admin, director — then attaches the membership (the sole record of the
 * role, per ADR-010) and employment record, and texts them a link to set their
 * password if the account is new.
 */
class ProvisionContactUserAction
{
    public function __construct(private readonly PasswordSetupService $passwordSetup) {}

    public function execute(
        string $name,
        string $phone,
        Role $role,
        ?School $school = null,
        ?Branch $branch = null,
    ): User {
        return DB::transaction(function () use ($name, $phone, $role, $school, $branch): User {
            $phone = $this->normalizePhone($phone);
            [$firstName, $fatherName, $grandfatherName] = $this->splitName($name);

            $user = User::withTrashed()->firstOrNew(['phone' => $phone]);
            $isNewAccount = ! $user->exists || $user->password === null;

            if (! $user->exists) {
                $user->fill([
                    'name' => $name,
                    'preferred_language' => 'en',
                ])->save();
            }

            Membership::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'school_id' => $school?->id,
                    'branch_id' => $branch?->id,
                    'role' => $role->value,
                ],
                [
                    'scope' => $role->scope()->value,
                    'is_active' => true,
                    'joined_at' => now(),
                ],
            );

            if ($school !== null) {
                $employee = Employee::firstOrCreate(
                    [
                        'user_id' => $user->id,
                        'school_id' => $school->id,
                        'branch_id' => $branch?->id,
                    ],
                    [
                        'first_name' => $firstName,
                        'father_name' => $fatherName,
                        'grandfather_name' => $grandfatherName,
                        'phone' => $phone,
                        'is_active' => true,
                    ],
                );

                // The job itself lives on a position row (multi-job title HR).
                $employee->positions()->firstOrCreate(
                    ['job_title' => $role->value, 'ended_on' => null],
                    ['is_primary' => ! $employee->activePositions()->where('is_primary', true)->exists(), 'hired_on' => now()->toDateString()],
                );
            }

            if ($isNewAccount) {
                $this->passwordSetup->sendLink($user);
            }

            return $user;
        });
    }

    private function normalizePhone(string $phone): string
    {
        return PhoneNumber::normalize($phone) ?? trim($phone);
    }

    /**
     * @return array{0: string, 1: ?string, 2: ?string}
     */
    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];

        return [
            $parts[0] ?? $name,
            $parts[1] ?? null,
            isset($parts[2]) ? implode(' ', array_slice($parts, 2)) : null,
        ];
    }
}

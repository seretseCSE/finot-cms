<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\User;
use App\Support\JobTitles;
use App\Support\PhoneNumber;

/**
 * Decides WHETHER a staff member gets a portal account and provisions it.
 *
 * Policy (ADR-010 aligned): the school setting `employee_account_job_titles`
 * (branch-overridable) lists the job titles that come with a login. Within
 * that list the hiring form may still opt a person out — EXCEPT the four
 * role-mapped titles (teacher/director/registrar/finance_officer), whose
 * branch memberships cannot exist without a user, so they always provision.
 *
 * Accounts are keyed by phone: an existing user (e.g. a parent hired as a
 * teacher) is REUSED, never duplicated; only brand-new accounts get the
 * password-setup SMS.
 */
class EmployeeAccountProvisioner
{
    public function __construct(private readonly PasswordSetupService $passwordSetup)
    {
    }

    /**
     * @param  list<string>  $activeTitles  job titles of the current (not ended) positions
     */
    public function accountRequired(array $activeTitles): bool
    {
        return array_intersect($activeTitles, JobTitles::roleMapped()) !== [];
    }

    /**
     * @param  list<string>  $activeTitles
     */
    public function accountEligible(Branch $branch, array $activeTitles): bool
    {
        return array_intersect($activeTitles, $branch->effectiveEmployeeAccountJobTitles()) !== [];
    }

    /**
     * Whether this hire/update should provision an account: role-mapped titles
     * always do; otherwise the branch policy must allow it AND the form must
     * not have opted out (default is opted in).
     *
     * @param  list<string>  $activeTitles
     */
    public function shouldProvision(Branch $branch, array $activeTitles, ?bool $requested): bool
    {
        return $this->accountRequired($activeTitles)
            || ($this->accountEligible($branch, $activeTitles) && ($requested ?? true));
    }

    /**
     * Find-or-create the user for a phone and return it; texts the password
     * setup link when the account is new (or still has no password).
     *
     * @param  array{first_name?: ?string, father_name?: ?string, grandfather_name?: ?string}  $names
     */
    public function resolveUser(string $phone, array $names): User
    {
        $normalized = PhoneNumber::normalize($phone) ?? trim($phone);

        $user = User::withTrashed()->firstOrNew(['phone' => $normalized]);
        $isNewAccount = ! $user->exists || $user->password === null;

        if (! $user->exists) {
            $fullName = trim(implode(' ', array_filter([
                $names['first_name'] ?? null,
                $names['father_name'] ?? null,
                $names['grandfather_name'] ?? null,
            ])));

            $user->fill([
                'name' => $fullName,
                'preferred_language' => 'en',
            ])->save();
        }

        if ($isNewAccount) {
            $this->passwordSetup->sendLink($user);
        }

        return $user;
    }

    /**
     * Late provisioning: give an existing account-less employee their login
     * (positions gained a role-mapped title, or the office granted access).
     */
    public function provisionFor(Employee $employee): User
    {
        $user = $this->resolveUser((string) $employee->phone, [
            'first_name' => $employee->first_name,
            'father_name' => $employee->father_name,
            'grandfather_name' => $employee->grandfather_name,
        ]);

        $employee->forceFill(['user_id' => $user->id])->save();

        // A photo already on the HR file becomes the account's first avatar.
        ProfilePhotoSync::seed($employee);

        return $user;
    }
}

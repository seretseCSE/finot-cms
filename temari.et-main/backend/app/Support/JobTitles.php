<?php

namespace App\Support;

use App\Enums\Role;

/**
 * The fixed catalog of staff job titles (job titles). A job title is an HR
 * concept; the four that correspond to kernel roles keep the matching branch
 * MEMBERSHIP in sync (see SyncPositionMembershipsAction) — every other
 * job title grants no permissions by itself.
 *
 * Mirrors the frontend list in lib/data — keep the two in sync.
 */
class JobTitles
{
    public const TEACHER = 'teacher';

    public const ALL = [
        'principal',
        'school_admin',
        'director',
        'vice_director',
        'teacher',
        'registrar',
        'finance_officer',
        'department_head',
        'unit_leader',
        'hr_officer',
        'accountant',
        'cashier',
        'secretary',
        'librarian',
        'counselor',
        'lab_assistant',
        'ict_officer',
        'nurse',
        'storekeeper',
        'security_guard',
        'janitor',
        'driver',
        'cook',
        'other',
    ];

    /**
     * Job title → kernel role. Only these five job titles carry authority;
     * adding/ending such a position must add/end the matching membership.
     */
    public const ROLE_MAP = [
        'director' => Role::Director,
        'teacher' => Role::Teacher,
        'registrar' => Role::Registrar,
        'finance_officer' => Role::FinanceOfficer,
        'storekeeper' => Role::Storekeeper,
    ];

    /**
     * Job titles excluded from the DEFAULT portal-account policy: field/support
     * staff who rarely carry smartphones. Schools may re-include them via the
     * `employee_account_job_titles` setting.
     */
    public const NO_ACCOUNT_BY_DEFAULT = ['security_guard', 'janitor', 'driver', 'cook'];

    public static function roleFor(string $jobTitle): ?Role
    {
        return self::ROLE_MAP[$jobTitle] ?? null;
    }

    /**
     * Job titles that MUST have a user account: they map to a kernel role, and
     * a membership cannot exist without a user. Always part of the effective
     * account policy, no matter what the school configures.
     *
     * @return list<string>
     */
    public static function roleMapped(): array
    {
        return array_keys(self::ROLE_MAP);
    }

    /**
     * Platform default for the account policy: everyone except the
     * NO_ACCOUNT_BY_DEFAULT support titles.
     *
     * @return list<string>
     */
    public static function defaultAccountTitles(): array
    {
        return array_values(array_diff(self::ALL, self::NO_ACCOUNT_BY_DEFAULT));
    }

    /**
     * Sanitize a configured account-policy list: drop unknown titles, force
     * the role-mapped four back in (their memberships need a user).
     *
     * @param  array<int, mixed>  $titles
     * @return list<string>
     */
    public static function sanitizeAccountTitles(array $titles): array
    {
        return array_values(array_unique([
            ...array_intersect(array_map(strval(...), $titles), self::ALL),
            ...self::roleMapped(),
        ]));
    }
}

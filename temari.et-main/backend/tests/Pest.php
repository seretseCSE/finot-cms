<?php

use App\Actions\SaveAcademicYearAction;
use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\Membership;
use App\Models\School;
use App\Models\User;
use App\Support\FinanceControls;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/*
|--------------------------------------------------------------------------
| Shared domain helpers
|--------------------------------------------------------------------------
*/

/** Create a school + one branch, returning the branch. */
function makeBranch(string $code = 'AA-0001'): Branch
{
    $school = School::create(['name' => 'Unity Academy']);

    return $school->branches()->create(['name' => 'Main', 'code' => $code]);
}

/** Grant a PLATFORM role via membership (memberships are the only role record). */
function grantPlatformRole(User $user, Role $role): void
{
    Membership::create([
        'user_id' => $user->id,
        'role' => $role->value,
        'scope' => $role->scope()->value,
        'is_active' => true,
    ]);
}

/** Whether the user holds an active membership with the given role (any scope). */
function hasMembershipRole(User $user, Role $role): bool
{
    return $user->memberships()->where('role', $role->value)->where('is_active', true)->exists();
}

/**
 * Create a director scoped to a single branch.
 *
 * Directors hold finance authority ONLY when the school's
 * `director_finance_access` setting is on (off by default in production —
 * Ethiopian directors are academic heads). Tests written before that gate
 * treat the director as the branch's do-everything actor, so this helper
 * enables the setting; pass $financeAccess: false to exercise the default.
 */
function directorOf(Branch $branch, bool $financeAccess = true): User
{
    if ($financeAccess) {
        $school = School::find($branch->school_id);
        $school->update([
            'settings' => array_merge($school->settings ?? [], ['director_finance_access' => true]),
        ]);
        Cache::forget("school:{$branch->school_id}:director_finance_access");
        FinanceControls::flush();
    }

    $director = User::factory()->create();
    Membership::create([
        'user_id' => $director->id,
        'school_id' => $branch->school_id,
        'branch_id' => $branch->id,
        'role' => Role::Director->value,
        'scope' => Role::Director->scope()->value,
        'is_active' => true,
    ]);

    return $director;
}

/** Create a principal managing an entire school (branch_id null). */
function schoolPrincipal(Branch $branch): User
{
    $principal = User::factory()->create();
    Membership::create([
        'user_id' => $principal->id,
        'school_id' => $branch->school_id,
        'role' => Role::Principal->value,
        'scope' => Role::Principal->scope()->value,
        'is_active' => true,
    ]);

    return $principal;
}

/** Create a platform super admin. */
function platformAdmin(): User
{
    $admin = User::factory()->create();
    grantPlatformRole($admin, Role::SuperAdmin);

    return $admin;
}

/** Create a teacher (or other branch role) with an active membership in a branch. */
function memberOf(Branch $branch, Role $role = Role::Teacher): User
{
    $user = User::factory()->create();
    Membership::create([
        'user_id' => $user->id,
        'school_id' => $branch->school_id,
        'branch_id' => $branch->id,
        'role' => $role->value,
        'scope' => $role->scope()->value,
        'is_active' => true,
    ]);

    return $user;
}

/**
 * Active-context headers for a branch-scoped request.
 *
 * @return array<string, string>
 */
function branchContext(Branch $branch): array
{
    return ['X-School-Id' => (string) $branch->school_id, 'X-Branch-Id' => (string) $branch->id];
}

/**
 * A minimal, valid guardian row for POST /students — every student must be
 * registered with at least one guardian on file (StoreStudentRequest).
 *
 * @return array<string, mixed>
 */
function guardianPayload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Almaz',
        'phone' => '0911223344',
        'relationship' => 'mother',
    ], $overrides);
}

/**
 * Active-context header for a school-scoped request (principal / school_admin).
 * The kernel is deny-by-default: without a validated context, school roles
 * grant nothing — mirroring real clients, which always send their context.
 *
 * @return array<string, string>
 */
function schoolContext(Branch|School $subject): array
{
    $schoolId = $subject instanceof Branch ? $subject->school_id : $subject->id;

    return ['X-School-Id' => (string) $schoolId];
}

/**
 * A live 2018 E.C. academic year at the branch (Meskerem 1 2018 = 2025-09-11,
 * ends in Sene). Shared by the finance + document suites — lives here, not in
 * one test file, so the parallel runner's file split can never strand it.
 */
function activeYear(Branch $branch): AcademicYear
{
    return (new SaveAcademicYearAction())->execute($branch, [
        'name' => '2018 E.C.', 'status' => 'active',
        'starts_on' => '2025-09-11', 'ends_on' => '2026-07-08',
    ]);
}

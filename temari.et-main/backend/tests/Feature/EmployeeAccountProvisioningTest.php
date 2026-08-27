<?php

use App\Enums\Role;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\School;
use App\Models\User;
use App\Services\Sms\SmsClient;
use App\Support\JobTitles;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->sms = Mockery::spy(SmsClient::class);
    app()->instance(SmsClient::class, $this->sms);
});

function hireEmployee(Branch $branch, string $jobTitle, array $extra = [])
{
    return test()->withHeaders(branchContext($branch))
        ->postJson('/api/v1/employees', [
            'first_name' => 'Abebe',
            'father_name' => 'Kebede',
            'phone' => '0911777666',
            'positions' => [
                ['job_title' => $jobTitle, 'employment_type' => 'full_time', 'is_primary' => true, 'hired_on' => '2024-09-01'],
            ],
            ...$extra,
        ]);
}

it('creates NO account for a job title outside the default policy', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));

    $response = hireEmployee($branch, 'janitor')->assertCreated();

    expect($response->json('data.user_id'))->toBeNull();
    expect(User::firstWhere('phone', '0911777666'))->toBeNull();
    $this->sms->shouldNotHaveReceived('send');
});

it('creates an account by default for an eligible job title', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));

    $response = hireEmployee($branch, 'librarian')->assertCreated();

    expect($response->json('data.user_id'))->not->toBeNull();
    expect(User::firstWhere('phone', '0911777666'))->not->toBeNull();
    $this->sms->shouldHaveReceived('send')->once();
});

it('honours an explicit opt-out for an eligible (non role-mapped) title', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));

    $response = hireEmployee($branch, 'librarian', ['create_user_account' => false])->assertCreated();

    expect($response->json('data.user_id'))->toBeNull();
    $this->sms->shouldNotHaveReceived('send');
});

it('always provisions role-mapped titles, even when opted out', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));

    $response = hireEmployee($branch, 'teacher', ['create_user_account' => false])->assertCreated();

    $user = User::firstWhere('phone', '0911777666');
    expect($response->json('data.user_id'))->toBe($user->id);
    expect(hasMembershipRole($user, Role::Teacher))->toBeTrue();
});

it('ignores a requested account when the school policy excludes the title', function () {
    $branch = makeBranch();
    School::find($branch->school_id)->update([
        'settings' => ['employee_account_job_titles' => ['teacher']],
    ]);
    Sanctum::actingAs(directorOf($branch));

    $response = hireEmployee($branch, 'librarian', ['create_user_account' => true])->assertCreated();

    expect($response->json('data.user_id'))->toBeNull();
});

it('lets a branch override re-include a title the school excluded', function () {
    $branch = makeBranch();
    School::find($branch->school_id)->update([
        'settings' => ['employee_account_job_titles' => ['teacher']],
    ]);
    $branch->update(['settings' => ['employee_account_job_titles' => ['teacher', 'janitor']]]);
    Sanctum::actingAs(directorOf($branch));

    $response = hireEmployee($branch, 'janitor')->assertCreated();

    expect($response->json('data.user_id'))->not->toBeNull();
});

it('auto-provisions on update when positions gain a role-mapped title', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));

    $employee = Employee::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => 'Chaltu', 'phone' => '0911222333',
    ]);
    $job = $employee->positions()->create(['job_title' => 'janitor', 'is_primary' => true, 'hired_on' => '2024-09-01']);

    $this->withHeaders(branchContext($branch))
        ->putJson("/api/v1/employees/{$employee->id}", [
            'first_name' => 'Chaltu',
            'positions' => [
                ['id' => $job->id, 'job_title' => 'janitor', 'is_primary' => false, 'hired_on' => '2024-09-01'],
                ['job_title' => 'registrar', 'is_primary' => true, 'hired_on' => '2024-09-01'],
            ],
        ])
        ->assertOk();

    $user = User::firstWhere('phone', '0911222333');
    expect($user)->not->toBeNull();
    expect($employee->refresh()->user_id)->toBe($user->id);
    expect(hasMembershipRole($user, Role::Registrar))->toBeTrue();
    $this->sms->shouldHaveReceived('send')->once();
});

it('grants an account later via create_user_account on update', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));

    $employee = Employee::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => 'Sara', 'phone' => '0911444555',
    ]);
    $job = $employee->positions()->create(['job_title' => 'librarian', 'is_primary' => true, 'hired_on' => '2024-09-01']);

    // Plain profile update: still no account.
    $this->withHeaders(branchContext($branch))
        ->putJson("/api/v1/employees/{$employee->id}", ['first_name' => 'Sara'])
        ->assertOk();
    expect($employee->refresh()->user_id)->toBeNull();

    $this->withHeaders(branchContext($branch))
        ->putJson("/api/v1/employees/{$employee->id}", [
            'first_name' => 'Sara',
            'create_user_account' => true,
            'positions' => [['id' => $job->id, 'job_title' => 'librarian', 'is_primary' => true, 'hired_on' => '2024-09-01']],
        ])
        ->assertOk();

    expect($employee->refresh()->user_id)->not->toBeNull();
    $this->sms->shouldHaveReceived('send')->once();
});

it('reuses an existing user (a parent hired as staff) without a new setup SMS', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));

    $existing = User::factory()->create(['phone' => '0911777666']);

    $response = hireEmployee($branch, 'teacher')->assertCreated();

    expect($response->json('data.user_id'))->toBe($existing->id);
    expect(User::where('phone', '0911777666')->count())->toBe(1);
    // Factory users already have a password — no setup link needed.
    $this->sms->shouldNotHaveReceived('send');
});

it('exposes the effective policy via the account-policy endpoint', function () {
    $branch = makeBranch();
    School::find($branch->school_id)->update([
        'settings' => ['employee_account_job_titles' => ['librarian']],
    ]);
    Sanctum::actingAs(directorOf($branch));

    $response = $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/employees/account-policy')
        ->assertOk();

    // Role-mapped titles are forced back in server-side.
    expect($response->json('data.account_job_titles'))
        ->toContain('librarian', 'teacher', 'director', 'registrar', 'finance_officer')
        ->not->toContain('janitor');
    expect($response->json('data.required_job_titles'))->toBe(JobTitles::roleMapped());
});

it('sanitizes the school setting: role-mapped titles cannot be excluded', function () {
    $branch = makeBranch();
    Sanctum::actingAs(schoolPrincipal($branch));

    $response = $this->withHeaders(['X-School-Id' => (string) $branch->school_id])
        ->patchJson("/api/v1/schools/{$branch->school_id}/settings", [
            'employee_account_job_titles' => ['nurse'],
        ])
        ->assertOk();

    expect($response->json('data.employee_account_job_titles'))
        ->toContain('nurse', 'teacher', 'director', 'registrar', 'finance_officer');
});

it('saves and clears the branch override', function () {
    $branch = makeBranch();
    Sanctum::actingAs(schoolPrincipal($branch));

    $headers = ['X-School-Id' => (string) $branch->school_id];

    $this->withHeaders($headers)
        ->patchJson("/api/v1/branches/{$branch->id}/settings", [
            'employee_account_job_titles' => ['nurse'],
        ])
        ->assertOk()
        ->assertJsonPath('data.overrides.employee_account_job_titles', ['nurse']);

    // Clearing the override falls back to the school default.
    $response = $this->withHeaders($headers)
        ->patchJson("/api/v1/branches/{$branch->id}/settings", [
            'employee_account_job_titles' => null,
        ])
        ->assertOk();

    expect($response->json('data.overrides.employee_account_job_titles'))->toBeNull();
    expect($response->json('data.effective.employee_account_job_titles'))
        ->toBe(JobTitles::defaultAccountTitles());
});

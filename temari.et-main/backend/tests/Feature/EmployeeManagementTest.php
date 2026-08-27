<?php

use App\Enums\Role;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Membership;
use App\Models\User;
use App\Services\Sms\SmsClient;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->sms = Mockery::spy(SmsClient::class);
    app()->instance(SmsClient::class, $this->sms);
});

it('lets a director add a teacher, provisioning a user, membership and setup SMS', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));

    $response = $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/employees', [
            'first_name' => 'Selam',
            'father_name' => 'Tesfaye',
            'phone' => '0911555444',
            'positions' => [
                ['job_title' => 'teacher', 'employment_type' => 'full_time', 'is_primary' => true, 'hired_on' => '2024-09-01'],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('data.full_name', 'Selam Tesfaye')
        ->assertJsonPath('data.positions.0.job_title', 'teacher')
        ->assertJsonPath('data.positions.0.employment_type', 'full_time');

    $user = User::firstWhere('phone', '0911555444');
    expect(hasMembershipRole($user, Role::Teacher))->toBeTrue();

    $membership = Membership::firstWhere('user_id', $user->id);
    expect($membership->branch_id)->toBe($branch->id);
    expect(Employee::find($response->json('data.id'))->branch_id)->toBe($branch->id);

    $this->sms->shouldHaveReceived('send')->once();
});

it('only lists employees from the active branch', function () {
    $branchA = makeBranch('AA-0001');
    $branchB = makeBranch('AA-0002');

    Employee::create(['user_id' => User::factory()->create()->id, 'school_id' => $branchA->school_id, 'branch_id' => $branchA->id, 'first_name' => 'A']);
    Employee::create(['user_id' => User::factory()->create()->id, 'school_id' => $branchB->school_id, 'branch_id' => $branchB->id, 'first_name' => 'B']);

    Sanctum::actingAs(directorOf($branchA));

    $response = $this->withHeaders(branchContext($branchA))
        ->getJson('/api/v1/employees')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.branch_id'))->toBe($branchA->id);
});

it('updates an employee profile with multiple positions and per-position salaries', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));
    $employee = Employee::create([
        'user_id' => User::factory()->create()->id,
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => 'Kebede',
    ]);
    $teaching = $employee->positions()->create(['job_title' => 'teacher', 'is_primary' => true, 'hired_on' => '2024-09-01']);

    $response = $this->withHeaders(branchContext($branch))
        ->putJson("/api/v1/employees/{$employee->id}", [
            'first_name' => 'Kebede',
            'positions' => [
                ['id' => $teaching->id, 'job_title' => 'teacher', 'salary' => 12000, 'salary_level' => 5, 'is_primary' => true, 'hired_on' => '2024-09-01'],
                ['job_title' => 'director', 'salary' => 4000, 'hired_on' => '2025-01-01'],
            ],
        ])
        ->assertOk();

    expect($response->json('data.active_job_titles'))->toContain('teacher', 'director');
    expect($employee->positions()->count())->toBe(2);
    // The teacher row updated IN PLACE (attachments keep pointing at it).
    expect((float) $teaching->refresh()->salary)->toBe(12000.0);

    // The director position derives a director membership (position-driven roles).
    expect(Membership::where('user_id', $employee->user_id)
        ->where('branch_id', $branch->id)
        ->where('role', Role::Director->value)
        ->where('is_active', true)
        ->exists())->toBeTrue();

    // Dropping the director position deactivates that membership again.
    $this->withHeaders(branchContext($branch))
        ->putJson("/api/v1/employees/{$employee->id}", [
            'first_name' => 'Kebede',
            'positions' => [
                ['id' => $teaching->id, 'job_title' => 'teacher', 'is_primary' => true, 'hired_on' => '2024-09-01'],
            ],
        ])
        ->assertOk();

    expect(Membership::where('user_id', $employee->user_id)
        ->where('branch_id', $branch->id)
        ->where('role', Role::Director->value)
        ->where('is_active', true)
        ->exists())->toBeFalse();
});

it('removes an employee', function () {
    $branch = makeBranch();
    Sanctum::actingAs(principalOfSchool($branch));
    $employee = Employee::create([
        'user_id' => User::factory()->create()->id,
        'school_id' => $branch->school_id, 'branch_id' => $branch->id, 'first_name' => 'Temp',
    ]);

    $this->withHeaders(branchContext($branch))
        ->deleteJson("/api/v1/employees/{$employee->id}")
        ->assertOk();

    expect(Employee::find($employee->id))->toBeNull();
});

it('keeps the employee record and branch membership in sync in both directions', function () {
    $branch = makeBranch();
    $director = directorOf($branch);

    Sanctum::actingAs($director);

    $response = $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/employees', [
            'first_name' => 'Almaz', 'phone' => '0911222333',
            'positions' => [['job_title' => 'teacher', 'is_primary' => true, 'hired_on' => '2024-09-01']],
        ])->assertCreated();

    $employee = Employee::find($response->json('data.id'));
    $membership = Membership::where('user_id', $employee->user_id)->where('branch_id', $branch->id)->first();

    // Deactivating on the Users/Memberships side must reflect on the Staff side.
    $this->patchJson("/api/v1/memberships/{$membership->id}/status", ['is_active' => false])->assertOk();
    expect($employee->fresh()->is_active)->toBeFalse();

    // Reactivating on the Staff side must reflect back on the membership.
    $this->withHeaders(branchContext($branch))
        ->putJson("/api/v1/employees/{$employee->id}", [
            'first_name' => 'Almaz', 'is_active' => true,
        ])->assertOk();
    expect($membership->fresh()->is_active)->toBeTrue();
});

it('rejects a job title outside the catalog and never grants school-scope authority', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/employees', [
            'first_name' => 'X', 'phone' => '0911000999',
            'positions' => [['job_title' => 'warlord', 'is_primary' => true, 'hired_on' => '2024-09-01']],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('positions.0.job_title');

    // A principal JOB TITLE is legal HR data but derives no membership —
    // school-scope authority can never be minted from the staff endpoint.
    $created = $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/employees', [
            'first_name' => 'Y', 'phone' => '0911000998',
            'positions' => [['job_title' => 'principal', 'is_primary' => true, 'hired_on' => '2024-09-01']],
        ])->assertCreated();

    $userId = Employee::find($created->json('data.id'))->user_id;
    expect(Membership::where('user_id', $userId)->exists())->toBeFalse();
});

it('forbids a registrar from adding staff', function () {
    $branch = makeBranch();
    $registrar = User::factory()->create();
    Membership::create([
        'user_id' => $registrar->id, 'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'role' => Role::Registrar->value, 'scope' => Role::Registrar->scope()->value, 'is_active' => true,
    ]);
    Sanctum::actingAs($registrar);

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/employees', [
            'first_name' => 'X', 'phone' => '0911000888',
            'positions' => [['job_title' => 'teacher', 'is_primary' => true, 'hired_on' => '2024-09-01']],
        ])
        ->assertForbidden();
});

it('requires an active branch to add staff', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));

    $this->postJson('/api/v1/employees', [
        'first_name' => 'X', 'phone' => '0911000777',
        'positions' => [['job_title' => 'teacher', 'is_primary' => true, 'hired_on' => '2024-09-01']],
    ])->assertStatus(422);
});

it('filters and searches the staff list', function () {
    $branch = makeBranch();
    Employee::create(['user_id' => User::factory()->create()->id, 'school_id' => $branch->school_id, 'branch_id' => $branch->id, 'first_name' => 'Selam', 'father_name' => 'Tesfaye', 'is_active' => true]);
    Employee::create(['user_id' => User::factory()->create()->id, 'school_id' => $branch->school_id, 'branch_id' => $branch->id, 'first_name' => 'Abebe', 'father_name' => 'Kebede', 'is_active' => false]);
    Sanctum::actingAs(directorOf($branch));

    $active = $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/employees?is_active=true')
        ->assertOk();
    expect($active->json('data'))->toHaveCount(1);
    expect($active->json('data.0.first_name'))->toBe('Selam');

    $search = $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/employees?search=abebe')
        ->assertOk();
    expect($search->json('data'))->toHaveCount(1);
    expect($search->json('data.0.first_name'))->toBe('Abebe');
});

it('exports staff respecting the active filters', function () {
    $branch = makeBranch();
    Employee::create(['user_id' => User::factory()->create()->id, 'school_id' => $branch->school_id, 'branch_id' => $branch->id, 'first_name' => 'Selam']);
    Employee::create(['user_id' => User::factory()->create()->id, 'school_id' => $branch->school_id, 'branch_id' => $branch->id, 'first_name' => 'Abebe']);
    Sanctum::actingAs(directorOf($branch));

    $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/employees/export?search=abebe')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.first_name', 'Abebe');
});

/** A principal (school scope) who can delete employees. */
function principalOfSchool(Branch $branch): User
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

it('stores the full HR profile with allowances and returns it', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));

    $response = $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/employees', [
            'first_name' => 'Hana',
            'father_name' => 'Bekele',
            'phone' => '0911222333',
            'birth_date' => '1990-05-15',
            'email' => 'hana@example.com',
            'marital_status' => 'married',
            'nationality' => 'Ethiopian',
            'state' => 'Addis Ababa',
            'city' => 'Addis Ababa',
            'sub_city' => 'Bole',
            'woreda' => '03',
            'house_no' => '124',
            'retirement_on' => '2050-09-01',
            'check_in' => '08:00',
            'check_out' => '16:30',
            'positions' => [
                [
                    'job_title' => 'teacher', 'employment_type' => 'full_time',
                    'salary' => 15000, 'salary_level' => 4,
                    'hired_on' => '2024-09-01', 'last_promoted_on' => '2025-09-01',
                    'is_primary' => true,
                ],
            ],
            'qualifications' => [
                ['education_level' => 'bachelor', 'field_of_study' => 'Mathematics', 'institution' => 'AAU', 'graduation_year' => 2012],
                ['education_level' => 'pgdt', 'field_of_study' => 'Mathematics'],
            ],
            'allowances' => [
                ['name' => 'Housing Allowance', 'amount' => 2000],
                ['name' => 'Transport Allowance', 'amount' => 1500],
            ],
            'deductions' => [
                ['name' => 'Credit association', 'amount' => 500],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('data.email', 'hana@example.com')
        ->assertJsonPath('data.marital_status', 'married')
        ->assertJsonPath('data.birth_date', '1990-05-15')
        ->assertJsonPath('data.check_in', '08:00')
        ->assertJsonPath('data.check_out', '16:30')
        ->assertJsonPath('data.positions.0.salary_level', 4)
        ->assertJsonCount(2, 'data.qualifications')
        ->assertJsonCount(1, 'data.deductions')
        ->assertJsonCount(2, 'data.allowances');

    expect($response->json('data.allowances.0.name'))->toBe('Housing Allowance');
});

it('rejects an allowance name outside the catalog', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/employees', [
            'first_name' => 'Hana',
            'phone' => '0911222334',
            'positions' => [['job_title' => 'teacher', 'is_primary' => true, 'hired_on' => '2024-09-01']],
            'allowances' => [['name' => 'Bonus Allowance', 'amount' => 100]],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['allowances.0.name']);
});

it('replaces allowances on update', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));
    $employee = Employee::create([
        'user_id' => User::factory()->create()->id,
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => 'Kebede',
    ]);
    $employee->syncAllowances([['name' => 'Meal Allowance', 'amount' => 500]]);

    $this->withHeaders(branchContext($branch))
        ->putJson("/api/v1/employees/{$employee->id}", [
            'first_name' => 'Kebede',
            'allowances' => [['name' => 'Medical Allowance', 'amount' => 800]],
        ])
        ->assertOk()
        ->assertJsonCount(1, 'data.allowances')
        ->assertJsonPath('data.allowances.0.name', 'Medical Allowance');
});

it('uploads and deletes a staff attachment', function () {
    Storage::fake(config('filesystems.default'));

    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));
    $employee = Employee::create([
        'user_id' => User::factory()->create()->id,
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => 'Kebede',
    ]);

    $upload = $this->withHeaders(branchContext($branch))
        ->post("/api/v1/employees/{$employee->id}/attachments", [
            'name' => 'Degree certificate',
            'file' => UploadedFile::fake()->create('degree.pdf', 200, 'application/pdf'),
        ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Degree certificate');

    $attachmentId = $upload->json('data.id');
    expect($employee->attachments()->count())->toBe(1);

    $this->withHeaders(branchContext($branch))
        ->deleteJson("/api/v1/employees/{$employee->id}/attachments/{$attachmentId}")
        ->assertOk();

    expect($employee->attachments()->count())->toBe(0);
});

it('rejects an attachment with a disallowed file type', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));
    $employee = Employee::create([
        'user_id' => User::factory()->create()->id,
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => 'Kebede',
    ]);

    $this->withHeaders(branchContext($branch))
        ->post("/api/v1/employees/{$employee->id}/attachments", [
            'name' => 'Malware',
            'file' => UploadedFile::fake()->create('run.exe', 10, 'application/octet-stream'),
        ], ['Accept' => 'application/json'])
        ->assertStatus(422);
});

it('hides the trashed filter results from non-platform admins', function () {
    $branch = makeBranch();
    $employee = Employee::create([
        'user_id' => User::factory()->create()->id,
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => 'Gone',
    ]);
    $employee->delete();

    Sanctum::actingAs(principalOfSchool($branch));
    $asPrincipal = $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/employees?trashed=with')
        ->assertOk();
    expect($asPrincipal->json('data'))->toHaveCount(0);

    Sanctum::actingAs(platformAdmin());
    $asAdmin = $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/employees?trashed=with')
        ->assertOk();
    expect($asAdmin->json('data'))->toHaveCount(1);
});

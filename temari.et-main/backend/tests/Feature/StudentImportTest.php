<?php

use App\Enums\Role;
use App\Models\GradeLevel;
use App\Models\Notification;
use App\Models\Student;
use App\Models\StudentImport;
use App\Models\User;
use App\Services\Sms\SmsClient;
use Database\Seeders\GradeLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

/*
 * Bulk student import guard rail: browser-parsed rows → chunked validation →
 * per-row import through RegisterStudentAction. Asserts the four properties
 * that must never regress: tenancy (students.create at the import's branch),
 * SILENCE by default (no SMS/email unless the operator opted in), duplicate
 * safety (skip by default, explicit per-row overrides), and partial-safe
 * execution (a bad row fails alone).
 */

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(GradeLevelSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->sms = Mockery::spy(SmsClient::class);
    app()->instance(SmsClient::class, $this->sms);
});

function importGrade(string $code = 'G1'): int
{
    return GradeLevel::where('code', $code)->value('id');
}

function importRow(array $overrides = [], array $guardian = []): array
{
    return array_merge([
        'first_name' => 'Abel',
        'father_name' => 'Tesfaye',
        'gender' => 'male',
        'date_of_birth' => '2015-03-02',
        'grade_level_id' => importGrade(),
        'guardians' => [array_merge([
            'first_name' => 'Almaz',
            'phone' => '0911223344',
            'relationship' => 'mother',
        ], $guardian)],
    ], $overrides);
}

/** Create a draft import session as the given registrar. */
function importSession(User $registrar, $branch, $year): StudentImport
{
    Sanctum::actingAs($registrar);

    $response = test()->postJson('/api/v1/student-imports', [
        'academic_year_id' => $year->id,
        'file_name' => 'students.xlsx',
    ], branchContext($branch))->assertCreated();

    return StudentImport::findOrFail($response->json('data.id'));
}

// ── The happy path ──────────────────────────────────────────────────────────

it('imports clean rows through the registration pipeline without any SMS', function () {
    $branch = makeBranch();
    $year = activeYear($branch);
    $registrar = memberOf($branch, Role::Registrar);
    $import = importSession($registrar, $branch, $year);

    $this->postJson("/api/v1/student-imports/{$import->id}/rows", [
        'rows' => [
            ['row_number' => 1, 'data' => importRow()],
            ['row_number' => 2, 'data' => importRow(
                ['first_name' => 'Sara', 'gender' => 'female', 'date_of_birth' => '2015-06-10'],
                ['phone' => '0911223344'], // same guardian as row 1 — must reuse
            )],
        ],
    ], branchContext($branch))
        ->assertOk()
        ->assertJsonPath('data.0.status', 'ready')
        ->assertJsonPath('data.1.status', 'ready')
        // The shared phone is announced as a link, never an error.
        ->assertJsonPath('data.1.issues.0.code', 'guardian_existing');

    $this->postJson("/api/v1/student-imports/{$import->id}/commit", [], branchContext($branch))->assertOk();

    $import->refresh();
    expect($import->status->value)->toBe('completed')
        ->and($import->imported_count)->toBe(2)
        ->and($import->failed_count)->toBe(0);

    // Two students, enrolled in the year at the branch.
    $abel = Student::where('first_name', 'Abel')->first();
    $sara = Student::where('first_name', 'Sara')->first();
    expect($abel)->not->toBeNull()
        ->and($sara)->not->toBeNull()
        ->and($abel->enrollments()->where('academic_year_id', $year->id)->exists())->toBeTrue();

    // ONE guardian account serves both children.
    expect(User::where('phone', '0911223344')->count())->toBe(1)
        ->and($abel->guardians()->count())->toBe(1)
        ->and($sara->guardians()->count())->toBe(1);

    // The crown rule: nothing was texted.
    $this->sms->shouldNotHaveReceived('send');

    // The initiator got the in-app completion notice.
    expect(Notification::where('user_id', $registrar->id)
        ->where('event', 'system.student_import_completed')->exists())->toBeTrue();
});

it('sends guardian setup SMS only when the operator opted in', function () {
    $branch = makeBranch();
    $year = activeYear($branch);
    $registrar = memberOf($branch, Role::Registrar);
    $import = importSession($registrar, $branch, $year);

    $this->postJson("/api/v1/student-imports/{$import->id}/rows", [
        'rows' => [['row_number' => 1, 'data' => importRow()]],
    ], branchContext($branch))->assertOk();

    $this->postJson("/api/v1/student-imports/{$import->id}/commit", [
        'options' => ['send_sms' => true],
    ], branchContext($branch))->assertOk();

    expect(StudentImport::find($import->id)->imported_count)->toBe(1);
    $this->sms->shouldHaveReceived('send');
});

// ── Validation & fixes ──────────────────────────────────────────────────────

it('flags invalid rows, keeps them out of the import, and revalidates inline fixes', function () {
    $branch = makeBranch();
    $year = activeYear($branch);
    $registrar = memberOf($branch, Role::Registrar);
    $import = importSession($registrar, $branch, $year);

    $response = $this->postJson("/api/v1/student-imports/{$import->id}/rows", [
        'rows' => [
            ['row_number' => 1, 'data' => importRow(['father_name' => null])],
            ['row_number' => 2, 'data' => importRow(['first_name' => 'Meles'], ['phone' => 'not-a-phone'])],
        ],
    ], branchContext($branch))->assertOk();

    expect($response->json('data.0.status'))->toBe('error')
        ->and($response->json('data.1.status'))->toBe('error');

    // Fix row 1 from the grid → it turns ready.
    $rowId = $response->json('data.0.id');
    $this->patchJson("/api/v1/student-imports/{$import->id}/rows/{$rowId}", [
        'data' => importRow(['father_name' => 'Bekele']),
    ], branchContext($branch))->assertOk()->assertJsonPath('data.status', 'ready');

    $this->postJson("/api/v1/student-imports/{$import->id}/commit", [], branchContext($branch))->assertOk();

    $import->refresh();
    expect($import->imported_count)->toBe(1)
        ->and(Student::where('first_name', 'Meles')->exists())->toBeFalse();
});

it('requires a grade from the row or the import default', function () {
    $branch = makeBranch();
    $year = activeYear($branch);
    $registrar = memberOf($branch, Role::Registrar);
    $import = importSession($registrar, $branch, $year);

    $response = $this->postJson("/api/v1/student-imports/{$import->id}/rows", [
        'rows' => [['row_number' => 1, 'data' => importRow(['grade_level_id' => null])]],
    ], branchContext($branch))->assertOk();

    expect($response->json('data.0.status'))->toBe('error')
        ->and(collect($response->json('data.0.issues'))->pluck('code'))->toContain('grade_required');
});

// ── Duplicates ──────────────────────────────────────────────────────────────

it('skips duplicates by default and enrolls the match on enroll_existing', function () {
    $branch = makeBranch();
    $year = activeYear($branch);
    $registrar = memberOf($branch, Role::Registrar);

    $existing = $branch->students()->create([
        'school_id' => $branch->school_id,
        'first_name' => 'Abel', 'father_name' => 'Tesfaye',
        'gender' => 'male', 'date_of_birth' => '2015-03-02',
    ]);

    $import = importSession($registrar, $branch, $year);

    $response = $this->postJson("/api/v1/student-imports/{$import->id}/rows", [
        'rows' => [
            ['row_number' => 1, 'data' => importRow()],
            ['row_number' => 2, 'data' => importRow(['first_name' => 'Fresh', 'date_of_birth' => '2016-01-01'])],
        ],
    ], branchContext($branch))->assertOk();

    expect($response->json('data.0.status'))->toBe('duplicate')
        ->and($response->json('data.0.duplicate_student_id'))->toBe($existing->id)
        ->and($response->json('data.0.resolution'))->toBe('skip')
        ->and($response->json('data.1.status'))->toBe('ready');

    // Resolve row 1 to enroll the existing student instead.
    $rowId = $response->json('data.0.id');
    $this->patchJson("/api/v1/student-imports/{$import->id}/rows/{$rowId}", [
        'resolution' => 'enroll_existing',
    ], branchContext($branch))->assertOk();

    $this->postJson("/api/v1/student-imports/{$import->id}/commit", [], branchContext($branch))->assertOk();

    $import->refresh();
    expect($import->imported_count)->toBe(2)
        // No third "Abel" was created — the existing person got the enrollment.
        ->and(Student::where('first_name', 'Abel')->count())->toBe(1)
        ->and($existing->enrollments()->where('academic_year_id', $year->id)->exists())->toBeTrue();
});

it('leaves skip-resolved duplicates out and counts them as skipped', function () {
    $branch = makeBranch();
    $year = activeYear($branch);
    $registrar = memberOf($branch, Role::Registrar);

    $branch->students()->create([
        'school_id' => $branch->school_id,
        'first_name' => 'Abel', 'father_name' => 'Tesfaye',
        'gender' => 'male', 'date_of_birth' => '2015-03-02',
    ]);

    $import = importSession($registrar, $branch, $year);

    $this->postJson("/api/v1/student-imports/{$import->id}/rows", [
        'rows' => [
            ['row_number' => 1, 'data' => importRow()],
            ['row_number' => 2, 'data' => importRow(['first_name' => 'Fresh', 'date_of_birth' => '2016-01-01'])],
        ],
    ], branchContext($branch))->assertOk();

    $this->postJson("/api/v1/student-imports/{$import->id}/commit", [], branchContext($branch))->assertOk();

    $import->refresh();
    expect($import->imported_count)->toBe(1)
        ->and($import->skipped_count)->toBe(1)
        ->and(Student::where('first_name', 'Abel')->count())->toBe(1);
});

// ── Tenancy ─────────────────────────────────────────────────────────────────

it('denies staff of another school any access to the import', function () {
    $branch = makeBranch();
    $year = activeYear($branch);
    $registrar = memberOf($branch, Role::Registrar);
    $import = importSession($registrar, $branch, $year);

    $otherBranch = makeBranch('AA-0002');
    $outsider = memberOf($otherBranch, Role::Registrar);

    Sanctum::actingAs($outsider);

    $this->getJson("/api/v1/student-imports/{$import->id}", branchContext($otherBranch))->assertForbidden();
    $this->postJson("/api/v1/student-imports/{$import->id}/rows", [
        'rows' => [['row_number' => 1, 'data' => importRow()]],
    ], branchContext($otherBranch))->assertForbidden();
    $this->postJson("/api/v1/student-imports/{$import->id}/commit", [], branchContext($otherBranch))->assertForbidden();
});

it('refuses import creation to roles without students.create', function () {
    $branch = makeBranch();
    $year = activeYear($branch);
    $teacher = memberOf($branch, Role::Teacher);

    Sanctum::actingAs($teacher);

    $this->postJson('/api/v1/student-imports', [
        'academic_year_id' => $year->id,
        'file_name' => 'students.xlsx',
    ], branchContext($branch))->assertForbidden();
});

// ── Accounts option ─────────────────────────────────────────────────────────

it('provisions student login accounts when the option is on', function () {
    $branch = makeBranch();
    $year = activeYear($branch);
    $registrar = memberOf($branch, Role::Registrar);
    $import = importSession($registrar, $branch, $year);

    $this->postJson("/api/v1/student-imports/{$import->id}/rows", [
        'rows' => [['row_number' => 1, 'data' => importRow(['primary_phone' => '0977665544'])]],
    ], branchContext($branch))->assertOk();

    $this->postJson("/api/v1/student-imports/{$import->id}/commit", [
        'options' => ['create_student_accounts' => true],
    ], branchContext($branch))->assertOk();

    $student = Student::where('first_name', 'Abel')->first();
    expect($student->user_id)->not->toBeNull();
    // Accounts exist, but silence still holds: no setup SMS went out.
    $this->sms->shouldNotHaveReceived('send');
});

<?php

use App\Actions\SaveAcademicYearAction;
use App\Models\Branch;
use App\Models\GradeLevel;
use App\Models\ParentProfile;
use App\Models\SchoolProgram;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentGuardian;
use App\Models\User;
use App\Services\Sms\SmsClient;
use Database\Seeders\GradeLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

/**
 * The Users page is a PERSON DIRECTORY: students and parents with accounts
 * appear alongside staff, with relationship-derived access chips (ADR-012).
 * Visibility for school staff derives at READ TIME from an active enrollment
 * (or a guardianship of one) — nothing is stored, so leaving the school
 * removes the person from the directory automatically.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(GradeLevelSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    app()->instance(SmsClient::class, Mockery::spy(SmsClient::class));
});

/** An enrolled student WITH their own login, plus a linked parent account. */
function communityAccounts(Branch $branch): array
{
    $year = (new SaveAcademicYearAction)->execute($branch, ['name' => '2017 E.C.', 'status' => 'active']);

    $studentUser = User::create(['name' => 'Naol Tesfaye', 'phone' => '0955000001']);
    $student = Student::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => 'Naol', 'father_name' => 'Tesfaye', 'gender' => 'male',
        'user_id' => $studentUser->id,
    ]);
    StudentEnrollment::create([
        'student_id' => $student->id, 'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'academic_year_id' => $year->id,
        'school_program_id' => SchoolProgram::defaultFor($branch)->id,
        'grade_level_id' => GradeLevel::where('code', 'G4')->value('id'),
        'status' => 'active', 'enrolled_on' => now(),
    ]);

    $parentUser = User::create(['name' => 'Tesfaye Alemu', 'phone' => '0955000002']);
    $parentProfile = ParentProfile::create(['user_id' => $parentUser->id, 'first_name' => 'Tesfaye', 'father_name' => 'Alemu']);
    StudentGuardian::create([
        'student_id' => $student->id, 'parent_id' => $parentProfile->id,
        'relationship' => 'father', 'is_primary' => true,
    ]);

    return [$student, $studentUser, $parentUser];
}

it('lists parents of PENDING (fee-gated) enrollments in the users directory', function () {
    $branch = makeBranch();
    $year = (new SaveAcademicYearAction)->execute($branch, ['name' => '2017 E.C.', 'status' => 'active']);

    // A just-registered student: enrollment born pending on the registration
    // fee. The school already has live custody — finance must find the parent
    // to record the very payment that activates the enrollment.
    $student = Student::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => 'Lensa', 'father_name' => 'Gemechu', 'gender' => 'female',
    ]);
    StudentEnrollment::create([
        'student_id' => $student->id, 'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'academic_year_id' => $year->id,
        'school_program_id' => SchoolProgram::defaultFor($branch)->id,
        'grade_level_id' => GradeLevel::where('code', 'G4')->value('id'),
        'status' => 'pending', 'enrolled_on' => now(),
    ]);

    $parentUser = User::create(['name' => 'Gemechu Tolla', 'phone' => '0955000009']);
    $parentProfile = ParentProfile::create(['user_id' => $parentUser->id, 'first_name' => 'Gemechu']);
    StudentGuardian::create([
        'student_id' => $student->id, 'parent_id' => $parentProfile->id,
        'relationship' => 'father', 'is_primary' => true,
    ]);

    Sanctum::actingAs(directorOf($branch));

    $rows = collect(
        $this->withHeaders(branchContext($branch))->getJson('/api/v1/users')->assertOk()->json('data')
    )->keyBy('id');

    expect($rows)->toHaveKey($parentUser->id);
});

it('lists enrolled students and their parents to branch staff, read-only', function () {
    $branch = makeBranch();
    [, $studentUser, $parentUser] = communityAccounts($branch);

    Sanctum::actingAs(directorOf($branch));

    $rows = collect(
        $this->withHeaders(branchContext($branch))->getJson('/api/v1/users')->assertOk()->json('data')
    )->keyBy('id');

    expect($rows)->toHaveKey($studentUser->id)
        ->and($rows)->toHaveKey($parentUser->id)
        ->and($rows[$studentUser->id]['relationships']['student']['branch_name'])->toBe($branch->name)
        ->and($rows[$parentUser->id]['relationships']['parent']['children_count'])->toBe(1);
});

it('drops student and parent from the directory when the enrollment ends', function () {
    $branch = makeBranch();
    [$student, $studentUser, $parentUser] = communityAccounts($branch);

    StudentEnrollment::where('student_id', $student->id)->update(['status' => 'withdrawn']);

    Sanctum::actingAs(directorOf($branch));

    $ids = collect(
        $this->withHeaders(branchContext($branch))->getJson('/api/v1/users')->assertOk()->json('data')
    )->pluck('id');

    expect($ids)->not->toContain($studentUser->id)
        ->and($ids)->not->toContain($parentUser->id);
});

it('never shows another school\'s students to branch staff', function () {
    $branchA = makeBranch('AA-0001');
    $branchB = makeBranch('BB-0001');
    [, $studentUser, $parentUser] = communityAccounts($branchB);

    Sanctum::actingAs(directorOf($branchA));

    $ids = collect(
        $this->withHeaders(branchContext($branchA))->getJson('/api/v1/users')->assertOk()->json('data')
    )->pluck('id');

    expect($ids)->not->toContain($studentUser->id)
        ->and($ids)->not->toContain($parentUser->id);
});

it('shows relationship chips to platform admins and filters by the student/parent roles', function () {
    $branch = makeBranch();
    [, $studentUser, $parentUser] = communityAccounts($branch);

    Sanctum::actingAs(platformAdmin());

    $students = collect($this->getJson('/api/v1/users?role=student')->assertOk()->json('data'))->keyBy('id');
    expect($students)->toHaveKey($studentUser->id)
        ->and($students)->not->toHaveKey($parentUser->id)
        ->and($students[$studentUser->id]['relationships']['student']['school_name'])->toBe($branch->school->name);

    $parents = collect($this->getJson('/api/v1/users?role=parent')->assertOk()->json('data'))->keyBy('id');
    expect($parents)->toHaveKey($parentUser->id)
        ->and($parents[$parentUser->id]['relationships']['parent']['children'])->toContain('Naol Tesfaye');
});

it('keeps global account actions forbidden for school staff on relationship rows', function () {
    $branch = makeBranch();
    [, $studentUser] = communityAccounts($branch);

    Sanctum::actingAs(directorOf($branch));

    $this->withHeaders(branchContext($branch))
        ->patchJson("/api/v1/users/{$studentUser->id}/status", ['status' => 'inactive'])
        ->assertForbidden();

    $this->withHeaders(branchContext($branch))
        ->putJson("/api/v1/users/{$studentUser->id}", ['name' => 'X', 'phone' => '0955000001'])
        ->assertForbidden();
});

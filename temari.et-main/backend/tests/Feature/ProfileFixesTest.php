<?php

use App\Actions\EnrollStudentAction;
use App\Actions\SaveAcademicYearAction;
use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\GradeLevel;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\Sms\SmsClient;
use Database\Seeders\GradeLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(GradeLevelSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->sms = Mockery::spy(SmsClient::class);
    app()->instance(SmsClient::class, $this->sms);
});

function fixYear(Branch $branch): AcademicYear
{
    return (new SaveAcademicYearAction)->execute($branch, ['name' => '2017 E.C.', 'status' => 'active']);
}

function fixSection(Branch $branch, string $gradeCode, string $name = 'A', ?int $capacity = null): Section
{
    return $branch->sections()->create([
        'school_id' => $branch->school_id,
        'grade_level_id' => GradeLevel::where('code', $gradeCode)->value('id'),
        'name' => $name,
        'capacity' => $capacity,
    ]);
}

function fixStudent(Branch $branch, string $first = 'Abel'): Student
{
    return $branch->students()->create([
        'school_id' => $branch->school_id,
        'first_name' => $first,
        'father_name' => 'Tesfaye',
        'gender' => 'male',
    ]);
}

function fixEnroll(Student $student, AcademicYear $year, ?Section $section = null, ?int $gradeLevelId = null): StudentEnrollment
{
    return app(EnrollStudentAction::class)->execute($student, [
        'academic_year_id' => $year->id,
        'section_id' => $section?->id,
        'grade_level_id' => $gradeLevelId ?? $section?->grade_level_id,
    ]);
}

// ───────────────────── enrollment grade correction ─────────────────────

it('fixes a mistaken grade on a live enrollment and clears the mismatched section', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));
    $year = fixYear($branch);
    $sectionG1 = fixSection($branch, 'G1');
    $enrollment = fixEnroll(fixStudent($branch), $year, $sectionG1);

    $g2 = GradeLevel::where('code', 'G2')->value('id');

    $this->withHeaders(branchContext($branch))
        ->patchJson("/api/v1/enrollments/{$enrollment->id}", ['grade_level_id' => $g2])
        ->assertOk()
        ->assertJsonPath('data.grade_level_id', $g2)
        ->assertJsonPath('data.section_id', null);
});

it('moves the enrollment into a section of the corrected grade when one is named', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));
    $year = fixYear($branch);
    $sectionG1 = fixSection($branch, 'G1');
    $sectionG2 = fixSection($branch, 'G2', 'B');
    $enrollment = fixEnroll(fixStudent($branch), $year, $sectionG1);

    $this->withHeaders(branchContext($branch))
        ->patchJson("/api/v1/enrollments/{$enrollment->id}", [
            'grade_level_id' => $sectionG2->grade_level_id,
            'section_id' => $sectionG2->id,
        ])
        ->assertOk()
        ->assertJsonPath('data.section_id', $sectionG2->id);
});

it('rejects a section that does not match the corrected grade', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));
    $year = fixYear($branch);
    $sectionG1 = fixSection($branch, 'G1');
    $enrollment = fixEnroll(fixStudent($branch), $year, $sectionG1);

    $this->withHeaders(branchContext($branch))
        ->patchJson("/api/v1/enrollments/{$enrollment->id}", [
            'grade_level_id' => GradeLevel::where('code', 'G3')->value('id'),
            'section_id' => $sectionG1->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('section_id');
});

it('refuses a grade change once frozen term results exist', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));
    $year = fixYear($branch);
    $section = fixSection($branch, 'G1');
    $student = fixStudent($branch);
    $enrollment = fixEnroll($student, $year, $section);

    $enrollment->termResults()->create([
        'student_id' => $student->id,
        'term_id' => $year->terms()->first()->id,
        'school_id' => $branch->school_id,
        'branch_id' => $branch->id,
        'academic_year_id' => $year->id,
        'section_id' => $section->id,
        'grade_level_id' => $enrollment->grade_level_id,
        'computed_at' => now(),
    ]);

    $this->withHeaders(branchContext($branch))
        ->patchJson("/api/v1/enrollments/{$enrollment->id}", [
            'grade_level_id' => GradeLevel::where('code', 'G2')->value('id'),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('grade_level_id');
});

it('never edits closed-history enrollments', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));
    $year = fixYear($branch);
    $enrollment = fixEnroll(fixStudent($branch), $year, fixSection($branch, 'G1'));
    $enrollment->update(['status' => 'withdrawn']);

    $this->withHeaders(branchContext($branch))
        ->patchJson("/api/v1/enrollments/{$enrollment->id}", [
            'grade_level_id' => GradeLevel::where('code', 'G2')->value('id'),
        ])
        ->assertUnprocessable();
});

// ───────────────────────── employee phone rules ─────────────────────────

it('rejects a duplicate employee phone at the same branch but allows it at another', function () {
    $branchA = makeBranch();
    $branchB = Branch::create(['school_id' => $branchA->school_id, 'name' => 'East', 'code' => 'AA-0002']);
    Sanctum::actingAs(directorOf($branchA));

    $payload = fn () => [
        'first_name' => 'Selam',
        'father_name' => 'Tesfaye',
        'phone' => '0911555444',
        'positions' => [['job_title' => 'librarian', 'is_primary' => true, 'hired_on' => '2024-09-01']],
        'create_user_account' => false,
    ];

    $this->withHeaders(branchContext($branchA))
        ->postJson('/api/v1/employees', $payload())
        ->assertCreated();

    $this->withHeaders(branchContext($branchA))
        ->postJson('/api/v1/employees', $payload())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('phone');

    // Same person hired at a second branch of the school — allowed (ADR-011).
    Sanctum::actingAs(directorOf($branchB));
    $this->withHeaders(branchContext($branchB))
        ->postJson('/api/v1/employees', $payload())
        ->assertCreated();
});

it('updates an employee phone and re-keys the login account with it', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/employees', [
            'first_name' => 'Marta',
            'father_name' => 'Bekele',
            'phone' => '0911000111',
            'positions' => [['job_title' => 'teacher', 'is_primary' => true, 'hired_on' => '2024-09-01']],
        ])
        ->assertCreated();

    $employee = Employee::firstWhere('phone', '0911000111');
    expect($employee->user)->not->toBeNull();

    $this->withHeaders(branchContext($branch))
        ->putJson("/api/v1/employees/{$employee->id}", [
            'first_name' => 'Marta',
            'father_name' => 'Bekele',
            'phone' => '0911000222',
        ])
        ->assertOk();

    expect($employee->refresh()->phone)->toBe('0911000222');
    expect($employee->user->refresh()->phone)->toBe('0911000222');
});

it('refuses an employee phone update that collides at the branch', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));

    foreach ([['Selam', '0911000111'], ['Marta', '0911000333']] as [$name, $phone]) {
        $this->withHeaders(branchContext($branch))
            ->postJson('/api/v1/employees', [
                'first_name' => $name,
                'father_name' => 'Tesfaye',
                'phone' => $phone,
                'positions' => [['job_title' => 'librarian', 'is_primary' => true, 'hired_on' => '2024-09-01']],
                'create_user_account' => false,
            ])
            ->assertCreated();
    }

    $marta = Employee::firstWhere('phone', '0911000333');

    $this->withHeaders(branchContext($branch))
        ->putJson("/api/v1/employees/{$marta->id}", [
            'first_name' => 'Marta',
            'father_name' => 'Tesfaye',
            'phone' => '0911 00 01 11',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('phone');
});

// ─────────────────────── student phone follow-through ───────────────────────

it('re-keys a student login when the primary phone is corrected', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));

    $student = fixStudent($branch);
    $user = User::factory()->create(['phone' => '0912000111']);
    $student->forceFill(['primary_phone' => '0912000111', 'user_id' => $user->id])->save();

    $this->withHeaders(branchContext($branch))
        ->putJson("/api/v1/students/{$student->id}", [
            'first_name' => $student->first_name,
            'father_name' => $student->father_name,
            'gender' => 'male',
            'primary_phone' => '0912000999',
        ])
        ->assertOk();

    expect($student->refresh()->primary_phone)->toBe('0912000999');
    expect($user->refresh()->phone)->toBe('0912000999');
});

it("rejects a guardian's phone as the student's own on update", function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));

    $response = $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/students', [
            'first_name' => 'Hanna',
            'father_name' => 'Girma',
            'gender' => 'female',
            'guardians' => [guardianPayload(['phone' => '0911223344'])],
        ])
        ->assertCreated();

    $studentId = $response->json('data.id');

    $this->withHeaders(branchContext($branch))
        ->putJson("/api/v1/students/{$studentId}", [
            'first_name' => 'Hanna',
            'father_name' => 'Girma',
            'gender' => 'female',
            'primary_phone' => '0911223344',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('primary_phone');
});

// ─────────────────────────── photo → avatar sync ───────────────────────────

it('syncs an uploaded student photo onto the linked login avatar', function () {
    Storage::fake(config('filesystems.default'));
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));

    $student = fixStudent($branch);
    $user = User::factory()->create();
    $student->forceFill(['user_id' => $user->id])->save();

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/students/{$student->id}/photo", [
            'photo' => UploadedFile::fake()->image('student.jpg'),
        ])
        ->assertOk();

    $student->refresh();
    expect($student->photo_path)->not->toBeNull();
    expect($user->refresh()->avatar_path)->toBe($student->photo_path);
});

it('syncs an uploaded employee photo onto the linked login avatar', function () {
    Storage::fake(config('filesystems.default'));
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));

    $user = User::factory()->create();
    $employee = Employee::create([
        'user_id' => $user->id,
        'school_id' => $branch->school_id,
        'branch_id' => $branch->id,
        'first_name' => 'Selam',
        'phone' => '0911555444',
    ]);

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/employees/{$employee->id}/photo", [
            'photo' => UploadedFile::fake()->image('employee.jpg'),
        ])
        ->assertOk();

    expect($user->refresh()->avatar_path)->toBe($employee->refresh()->photo_path);
});

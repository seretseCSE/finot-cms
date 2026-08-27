<?php

use App\Actions\SaveAcademicYearAction;
use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\GradeLevel;
use App\Models\Membership;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\Sms\SmsClient;
use Database\Seeders\GradeLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(GradeLevelSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    app()->instance(SmsClient::class, Mockery::spy(SmsClient::class));
});

function sectionFor(Branch $branch, string $gradeCode = 'G1', string $name = 'A', ?int $capacity = null): Section
{
    return $branch->sections()->create([
        'school_id' => $branch->school_id,
        'grade_level_id' => GradeLevel::where('code', $gradeCode)->value('id'),
        'name' => $name,
        'capacity' => $capacity,
    ]);
}

function yearFor(Branch $branch, string $name = '2017 E.C.'): AcademicYear
{
    return (new SaveAcademicYearAction)->execute($branch, ['name' => $name, 'is_current' => true]);
}

it('registers a student scoped to the active branch', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));

    $response = $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/students', [
            'first_name' => 'Hana',
            'father_name' => 'Bekele',
            'grandfather_name' => 'Tadesse',
            'gender' => 'female',
            'guardians' => [guardianPayload()],
        ])
        ->assertCreated()
        ->assertJsonPath('data.full_name', 'Hana Bekele Tadesse')
        ->assertJsonPath('data.gender', 'female');

    $student = Student::find($response->json('data.id'));
    expect($student->branch_id)->toBe($branch->id);
    expect($student->school_id)->toBe($branch->school_id);
});

it('registers a student and enrolls them in one step', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));
    $year = yearFor($branch);
    $section = sectionFor($branch);

    $response = $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/students', [
            'first_name' => 'Abel',
            'father_name' => 'Girma',
            'gender' => 'male',
            'academic_year_id' => $year->id,
            'section_id' => $section->id,
            'guardians' => [guardianPayload()],
        ])
        ->assertCreated()
        ->assertJsonPath('data.current_enrollment.section_id', $section->id);

    $enrollment = StudentEnrollment::firstWhere('student_id', $response->json('data.id'));
    expect($enrollment->grade_level_id)->toBe($section->grade_level_id);
    expect($enrollment->status->value)->toBe('active');
});

it('stores the fayda id hashed, never in plaintext', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));

    $response = $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/students', [
            'first_name' => 'Sara',
            'father_name' => 'Mulu',
            'gender' => 'female',
            'fayda_id' => '1234567890',
            'guardians' => [guardianPayload()],
        ])
        ->assertCreated();

    $student = Student::find($response->json('data.id'));
    expect($student->fayda_hash)->toBe(hash('sha256', '1234567890'));
    expect($student->fayda_hash)->not->toBe('1234567890');
});

it('rejects enrolling beyond section capacity', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));
    $year = yearFor($branch);
    $section = sectionFor($branch, capacity: 1);

    $first = Student::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => 'A', 'father_name' => 'B', 'gender' => 'male',
    ]);
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/students/{$first->id}/enrollments", [
            'academic_year_id' => $year->id, 'section_id' => $section->id,
        ])->assertCreated();

    $second = Student::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => 'C', 'father_name' => 'D', 'gender' => 'female',
    ]);
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/students/{$second->id}/enrollments", [
            'academic_year_id' => $year->id, 'section_id' => $section->id,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('section_id');
});

it('rejects a second enrollment for the same student in one year', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));
    $year = yearFor($branch);
    $sectionA = sectionFor($branch, name: 'A');
    $sectionB = sectionFor($branch, name: 'B');

    $student = Student::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => 'E', 'father_name' => 'F', 'gender' => 'male',
    ]);

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/students/{$student->id}/enrollments", [
            'academic_year_id' => $year->id, 'section_id' => $sectionA->id,
        ])->assertCreated();

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/students/{$student->id}/enrollments", [
            'academic_year_id' => $year->id, 'section_id' => $sectionB->id,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('academic_year_id');
});

it('only lists students from the active branch', function () {
    $branchA = makeBranch('AA-0001');
    $branchB = makeBranch('AA-0002');

    Student::create(['school_id' => $branchA->school_id, 'branch_id' => $branchA->id, 'first_name' => 'A', 'father_name' => 'X', 'gender' => 'male']);
    Student::create(['school_id' => $branchB->school_id, 'branch_id' => $branchB->id, 'first_name' => 'B', 'father_name' => 'Y', 'gender' => 'female']);

    Sanctum::actingAs(directorOf($branchA));

    $response = $this->withHeaders(branchContext($branchA))
        ->getJson('/api/v1/students')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.branch_id'))->toBe($branchA->id);
});

it('lets a school admin list students across their school without selecting a branch', function () {
    $branchA = makeBranch('AA-0001');
    $branchB = $branchA->school->branches()->create(['name' => 'Second', 'code' => 'AA-0002']);
    $otherBranch = makeBranch('BB-0001');

    Student::create(['school_id' => $branchA->school_id, 'branch_id' => $branchA->id, 'first_name' => 'A', 'father_name' => 'X', 'gender' => 'male']);
    Student::create(['school_id' => $branchB->school_id, 'branch_id' => $branchB->id, 'first_name' => 'B', 'father_name' => 'Y', 'gender' => 'female']);
    Student::create(['school_id' => $otherBranch->school_id, 'branch_id' => $otherBranch->id, 'first_name' => 'C', 'father_name' => 'Z', 'gender' => 'male']);

    $admin = User::factory()->create();
    Membership::create([
        'user_id' => $admin->id,
        'school_id' => $branchA->school_id,
        'role' => Role::SchoolAdmin->value,
        'scope' => Role::SchoolAdmin->scope()->value,
        'is_active' => true,
    ]);
    Sanctum::actingAs($admin);

    $response = $this->withHeaders(['X-School-Id' => (string) $branchA->school_id])
        ->getJson('/api/v1/students')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(2);
    expect(collect($response->json('data'))->pluck('branch_id')->all())
        ->toEqualCanonicalizing([$branchA->id, $branchB->id]);
    expect($response->json('data.0.school_name'))->not->toBeNull();
    expect($response->json('data.0.branch_name'))->not->toBeNull();
});

it('lets platform staff list students across all schools without selecting a branch', function () {
    $branchA = makeBranch('AA-0001');
    $branchB = makeBranch('BB-0001');

    Student::create(['school_id' => $branchA->school_id, 'branch_id' => $branchA->id, 'first_name' => 'A', 'father_name' => 'X', 'gender' => 'male']);
    Student::create(['school_id' => $branchB->school_id, 'branch_id' => $branchB->id, 'first_name' => 'B', 'father_name' => 'Y', 'gender' => 'female']);

    $support = User::factory()->create();
    grantPlatformRole($support, Role::SupportAgent);
    Sanctum::actingAs($support);

    $response = $this->getJson('/api/v1/students')->assertOk();

    expect($response->json('data'))->toHaveCount(2);
    expect(collect($response->json('data'))->pluck('school_id')->unique()->count())->toBe(2);
    expect($response->json('data.0.school_name'))->not->toBeNull();
    expect($response->json('data.0.branch_name'))->not->toBeNull();
});

it('forbids a teacher from registering students', function () {
    $branch = makeBranch();
    $teacher = User::factory()->create();
    Membership::create([
        'user_id' => $teacher->id, 'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'role' => Role::Teacher->value, 'scope' => Role::Teacher->scope()->value, 'is_active' => true,
    ]);
    Sanctum::actingAs($teacher);

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/students', ['first_name' => 'Z', 'father_name' => 'Q', 'gender' => 'male', 'guardians' => [guardianPayload()]])
        ->assertForbidden();
});

it('requires an active branch to register a student', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));

    $this->postJson('/api/v1/students', ['first_name' => 'Z', 'father_name' => 'Q', 'gender' => 'male'])
        ->assertStatus(422);
});

it('does not let a director-elsewhere update a student in a school where they are only a teacher', function () {
    $branchA = makeBranch('AA-0001');   // school A — actor is only a TEACHER here
    $branchB = makeBranch('BB-0001');   // school B — actor is a DIRECTOR here

    // Actor: teacher at branch A + director at branch B. Globally they hold
    // `students.update` (via the director role) — but a teacher must NOT be able to
    // edit a student in school A where they only teach.
    $actor = memberOf($branchA, Role::Teacher);
    Membership::create([
        'user_id' => $actor->id,
        'school_id' => $branchB->school_id,
        'branch_id' => $branchB->id,
        'role' => Role::Director->value,
        'scope' => Role::Director->scope()->value,
        'is_active' => true,
    ]);

    $student = Student::create([
        'school_id' => $branchA->school_id,
        'branch_id' => $branchA->id,
        'first_name' => 'Sara', 'father_name' => 'T', 'gender' => 'female',
    ]);

    Sanctum::actingAs($actor);

    // Acting in the school A (teacher) context: the update is denied even though the
    // director role at school B globally carries students.update.
    $this->withHeaders(branchContext($branchA))
        ->putJson("/api/v1/students/{$student->id}", ['first_name' => 'Edited', 'father_name' => 'T', 'gender' => 'female'])
        ->assertStatus(403);

    expect($student->fresh()->first_name)->toBe('Sara');
});

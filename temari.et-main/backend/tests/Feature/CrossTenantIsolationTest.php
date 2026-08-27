<?php

use App\Actions\SaveAcademicYearAction;
use App\Enums\Role;
use App\Enums\TermStatus;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\GradeLevel;
use App\Models\Membership;
use App\Models\ParentProfile;
use App\Models\SchoolProgram;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentGuardian;
use App\Models\Subject;
use App\Models\SubjectAssignment;
use App\Models\Term;
use App\Models\User;
use Database\Seeders\GradeLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

/*
|--------------------------------------------------------------------------
| Cross-tenant isolation (ADR-010/011/012)
|--------------------------------------------------------------------------
| The kernel's contract, asserted end-to-end:
|  1. a role at School A grants NOTHING at School B — even for a user who is
|     a member of School B in a weaker role;
|  2. forged context headers can only reduce access, never widen it;
|  3. no context ⇒ no school/branch permissions (deny-by-default);
|  4. relationship access (parents) never crosses its own links;
|  5. closed terms are read-only; 6. teachers own only their continuous assessments.
*/

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(GradeLevelSeeder::class);
});

function isolationSetup(Branch $branch): array
{
    $year = (new SaveAcademicYearAction)->execute($branch, ['name' => '2017 E.C.', 'is_current' => true]);
    $section = $branch->sections()->create([
        'school_id' => $branch->school_id,
        'grade_level_id' => GradeLevel::where('code', 'G1')->value('id'),
        'name' => 'A',
    ]);
    $student = Student::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => 'Sara', 'father_name' => 'Test', 'gender' => 'female',
    ]);
    StudentEnrollment::create([
        'student_id' => $student->id, 'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'academic_year_id' => $year->id, 'school_program_id' => SchoolProgram::defaultFor($branch)->id,
        'section_id' => $section->id, 'grade_level_id' => $section->grade_level_id,
        'status' => 'active', 'enrolled_on' => now(),
    ]);

    return [$year, $section, $student];
}

// ── 1. Roles never leak across tenants ──────────────────────────────────────

it('gives a director at school A no write power at school B where they are only finance officer', function () {
    $branchA = makeBranch('AA-0001');
    $branchB = makeBranch('BB-0001');
    [, , $studentB] = isolationSetup($branchB);

    // Director at A (has sections.create there) + finance officer at B (no sections.create).
    $user = directorOf($branchA);
    Membership::create([
        'user_id' => $user->id, 'school_id' => $branchB->school_id, 'branch_id' => $branchB->id,
        'role' => Role::FinanceOfficer->value, 'scope' => Role::FinanceOfficer->scope()->value, 'is_active' => true,
    ]);

    Sanctum::actingAs($user);

    // The exact historical leak: global can('sections.create') + membership at B.
    $this->withHeaders(branchContext($branchB))->postJson('/api/v1/sections', [
        'grade_level_id' => GradeLevel::where('code', 'G1')->value('id'),
        'name' => 'Rogue',
    ])->assertForbidden();

    $this->withHeaders(branchContext($branchB))->postJson('/api/v1/students', [
        'first_name' => 'Rogue', 'father_name' => 'X', 'gender' => 'male',
        'guardians' => [guardianPayload()],
    ])->assertForbidden();

    $section = Section::where('branch_id', $branchB->id)->first();
    $this->withHeaders(branchContext($branchB))->postJson("/api/v1/sections/{$section->id}/attendance", [
        'date' => now()->toDateString(),
        'records' => [['student_id' => $studentB->id, 'status' => 'present']],
    ])->assertForbidden();

    // ...while at their own branch the same actions are allowed.
    $this->withHeaders(branchContext($branchA))->postJson('/api/v1/sections', [
        'grade_level_id' => GradeLevel::where('code', 'G1')->value('id'),
        'name' => 'Own',
    ])->assertCreated();
});

// ── 2. Forged context headers can only reduce access ────────────────────────

it('nullifies a forged branch header pointing at another school', function () {
    $branchA = makeBranch('AA-0001');
    $branchB = makeBranch('BB-0001');

    Sanctum::actingAs(schoolPrincipal($branchA));

    // X-School-Id = own school, X-Branch-Id = someone else's branch: the pair
    // fails validation, so no branch context resolves → 422, never a write.
    $this->withHeaders([
        'X-School-Id' => (string) $branchA->school_id,
        'X-Branch-Id' => (string) $branchB->id,
    ])->postJson('/api/v1/sections', [
        'grade_level_id' => GradeLevel::where('code', 'G1')->value('id'),
        'name' => 'Forged',
    ])->assertStatus(422);

    expect(Section::where('branch_id', $branchB->id)->exists())->toBeFalse();
});

it('nullifies a school header the user is not related to', function () {
    $branchA = makeBranch('AA-0001');
    $branchB = makeBranch('BB-0001');

    Sanctum::actingAs(schoolPrincipal($branchA));

    $this->withHeaders(branchContext($branchB))->getJson('/api/v1/users')->assertForbidden();
});

// ── 3. Deny-by-default without a context ─────────────────────────────────────

it('grants school roles nothing when no context header is sent', function () {
    $branch = makeBranch('AA-0001');

    Sanctum::actingAs(schoolPrincipal($branch));
    $this->getJson('/api/v1/users')->assertForbidden();

    Sanctum::actingAs(directorOf($branch));
    $this->getJson('/api/v1/users')->assertForbidden();
});

// ── 4. Relationship lane stays inside its own links ──────────────────────────

it('lets a parent see exactly their linked children and nothing else', function () {
    $branchA = makeBranch('AA-0001');
    $branchB = makeBranch('BB-0001');
    [, , $ownChild] = isolationSetup($branchA);
    [, , $otherChild] = isolationSetup($branchB);

    $parentUser = User::factory()->create();
    $profile = ParentProfile::create(['user_id' => $parentUser->id]);
    StudentGuardian::create([
        'student_id' => $ownChild->id, 'parent_id' => $profile->id,
        'relationship' => 'mother', 'is_primary' => true,
        'can_view_grades' => true, 'can_view_attendance' => false,
    ]);

    Sanctum::actingAs($parentUser);

    // /me/children: exactly the linked child.
    $children = collect($this->getJson('/api/v1/me/children')->assertOk()->json('data'));
    expect($children)->toHaveCount(1)
        ->and($children->first()['student_id'])->toBe($ownChild->id);

    // Someone else's child: hard 403 regardless of any headers.
    $term = Term::where('branch_id', $branchB->id)->where('sequence', 1)->first();
    $this->getJson("/api/v1/me/children/{$otherChild->id}/result-card?term_id={$term->id}")
        ->assertForbidden();

    // Per-link flags gate each capability: grades allowed, attendance not.
    $ownTerm = Term::where('branch_id', $branchA->id)->where('sequence', 1)->first();
    $this->getJson("/api/v1/me/children/{$ownChild->id}/result-card?term_id={$ownTerm->id}")->assertOk();
    $this->getJson("/api/v1/me/children/{$ownChild->id}/attendance-summary?term_id={$ownTerm->id}")->assertForbidden();

    // A parent has no staff permissions anywhere.
    $this->withHeaders(branchContext($branchA))->getJson('/api/v1/students')->assertForbidden();
});

// ── 5. Closed terms are read-only ────────────────────────────────────────────

it('refuses attendance and continuous assessment writes into a closed term', function () {
    $branch = makeBranch('AA-0001');
    [$year, $section, $student] = isolationSetup($branch);

    $term = Term::where('branch_id', $branch->id)->where('sequence', 1)->first();
    $term->update([
        'status' => TermStatus::Closed->value,
        'is_current' => true,
        'starts_on' => now()->subMonths(3),
        'ends_on' => now()->addMonths(1),
    ]);

    Sanctum::actingAs(directorOf($branch));

    $this->withHeaders(branchContext($branch))->postJson("/api/v1/sections/{$section->id}/attendance", [
        'date' => now()->toDateString(),
        'records' => [['student_id' => $student->id, 'status' => 'present']],
    ])->assertStatus(422);

    $subject = Subject::create(['code' => 'MTH-X', 'name' => 'Math']);
    $assignment = SubjectAssignment::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id, 'academic_year_id' => $year->id,
        'section_id' => $section->id, 'subject_id' => $subject->id, 'term_id' => $term->id,
    ]);

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/subject-assignments/{$assignment->id}/assessments", [
            'type' => 'quiz', 'name' => 'Quiz 1', 'max_score' => 10, 'weight' => 10,
        ])->assertStatus(422);
});

// ── 6. Teachers own only their continuous assessments ────────────────────────────────────

it('lets a teacher manage only their own subject assignment continuous assessment', function () {
    $branch = makeBranch('AA-0001');
    [$year, $section] = isolationSetup($branch);
    $term = Term::where('branch_id', $branch->id)->where('sequence', 1)->first();
    $subject = Subject::create(['code' => 'MTH-Y', 'name' => 'Math']);

    $owner = memberOf($branch);          // teacher who owns the assignment
    $intruder = memberOf($branch);       // another teacher of the same branch

    // Teacher-defined assessments are a branch opt-in (off by default).
    $branch->update(['settings' => ['teacher_assessments_enabled' => true]]);

    $ownerEmployee = Employee::create([
        'user_id' => $owner->id, 'school_id' => $branch->school_id,
        'branch_id' => $branch->id, 'first_name' => 'Owner',
    ]);

    $assignment = SubjectAssignment::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id, 'academic_year_id' => $year->id,
        'section_id' => $section->id, 'subject_id' => $subject->id, 'term_id' => $term->id,
        'employee_id' => $ownerEmployee->id,
    ]);

    $payload = ['type' => 'quiz', 'name' => 'Quiz 1', 'max_score' => 10, 'weight' => 10];

    Sanctum::actingAs($intruder);
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/subject-assignments/{$assignment->id}/assessments", $payload)
        ->assertForbidden();

    Sanctum::actingAs($owner);
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/subject-assignments/{$assignment->id}/assessments", $payload)
        ->assertCreated();
});

// ── 7. Dual enrollment across programs, blocked within one ──────────────────

it('allows dual enrollment across programs but not within the same program', function () {
    $branch = makeBranch('AA-0001');
    [$year, $section, $student] = isolationSetup($branch);

    // Through the canonical path: a new program starts offered in every grade
    // (the grade × program offering matrix would reject enrollments otherwise).
    $evening = SchoolProgram::addToBranch($branch, 'evening');
    $eveningSection = $branch->sections()->create([
        'school_id' => $branch->school_id,
        'grade_level_id' => $section->grade_level_id,
        'name' => 'EV-A',
    ]);

    Sanctum::actingAs(directorOf($branch));

    // Same year, same (regular) program → rejected.
    $this->withHeaders(branchContext($branch))->postJson("/api/v1/students/{$student->id}/enrollments", [
        'academic_year_id' => $year->id,
        'section_id' => $eveningSection->id,
    ])->assertStatus(422);

    // Same year, evening program → allowed (dual enrollment).
    $this->withHeaders(branchContext($branch))->postJson("/api/v1/students/{$student->id}/enrollments", [
        'academic_year_id' => $year->id,
        'section_id' => $eveningSection->id,
        'school_program_id' => $evening->id,
    ])->assertCreated();

    expect($student->enrollments()->where('status', 'active')->count())->toBe(2);
});

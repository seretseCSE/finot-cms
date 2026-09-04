<?php

use App\Actions\ComputeTermResultsAction;
use App\Actions\EnrollStudentAction;
use App\Actions\SaveAcademicYearAction;
use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\GradeLevel;
use App\Models\GradingScale;
use App\Models\Membership;
use App\Models\ParentProfile;
use App\Models\Section;
use App\Models\SectionHomeroom;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentGuardian;
use App\Models\StudentTermResult;
use App\Models\Subject;
use App\Models\SubjectAssignment;
use App\Models\User;
use Database\Seeders\GradeLevelSeeder;
use Database\Seeders\GradingScaleSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SubjectSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(GradeLevelSeeder::class);
    $this->seed(SubjectSeeder::class);
    $this->seed(GradingScaleSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function gradingYear(Branch $branch): AcademicYear
{
    return (new SaveAcademicYearAction())->execute($branch, ['name' => '2017 E.C.', 'status' => 'active']);
}

function gradingSection(Branch $branch, string $gradeCode = 'G1'): Section
{
    return $branch->sections()->create([
        'school_id' => $branch->school_id,
        'grade_level_id' => GradeLevel::where('code', $gradeCode)->value('id'),
        'name' => 'A',
    ]);
}

function gradingStudent(Branch $branch, string $first = 'Abel'): Student
{
    return $branch->students()->create([
        'school_id' => $branch->school_id,
        'first_name' => $first,
        'father_name' => 'Tesfaye',
        'gender' => 'male',
    ]);
}

function gradingEnroll(Student $student, AcademicYear $year, Section $section): StudentEnrollment
{
    return app(EnrollStudentAction::class)->execute($student, [
        'academic_year_id' => $year->id,
        'section_id' => $section->id,
        'grade_level_id' => $section->grade_level_id,
    ]);
}

function gradingTeacher(Branch $branch, string $name = 'Alemu'): Employee
{
    return Employee::create([
        'user_id' => User::factory()->create()->id,
        'school_id' => $branch->school_id,
        'branch_id' => $branch->id,
        'first_name' => $name,
        'father_name' => 'Bekele',
        'gender' => 'male',
    ]);
}

function gradingAssignment(Branch $branch, AcademicYear $year, Section $section, ?Employee $teacher = null, string $subjectCode = 'MATH'): SubjectAssignment
{
    return SubjectAssignment::create([
        'school_id' => $branch->school_id,
        'branch_id' => $branch->id,
        'academic_year_id' => $year->id,
        'section_id' => $section->id,
        'subject_id' => Subject::where('code', $subjectCode)->value('id'),
        'term_id' => $year->terms()->first()->id,
        'employee_id' => $teacher?->id,
        'periods_per_week' => 5,
    ]);
}

// ───────────────────────── grading scales ─────────────────────────

it('lists platform scales and lets a director add a school scale', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));

    $list = $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/grading-scales')
        ->assertOk()
        ->json('data');

    expect(collect($list)->pluck('code'))->toContain('et-percentage', 'et-letter', 'et-early-grade');

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/grading-scales', [
            'code' => 'house-scale',
            'name' => 'House Scale',
            'bands' => [
                ['min_score' => 50, 'max_score' => 100, 'letter' => 'P', 'label' => 'Pass', 'is_passing' => true],
                ['min_score' => 0, 'max_score' => 49.99, 'letter' => 'F', 'label' => 'Fail', 'is_passing' => false],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('data.is_platform', false);
});

it('rejects overlapping scale bands', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/grading-scales', [
            'code' => 'broken',
            'name' => 'Broken',
            'bands' => [
                ['min_score' => 40, 'max_score' => 100, 'letter' => 'P', 'label' => 'Pass', 'is_passing' => true],
                ['min_score' => 0, 'max_score' => 50, 'letter' => 'F', 'label' => 'Fail', 'is_passing' => false],
            ],
        ])
        ->assertStatus(422);
});

it('forbids teachers from managing grading scales', function () {
    $branch = makeBranch();
    Sanctum::actingAs(memberOf($branch));

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/grading-scales', [
            'code' => 'x', 'name' => 'X',
            'bands' => [['min_score' => 0, 'max_score' => 100, 'label' => 'All', 'is_passing' => true]],
        ])
        ->assertForbidden();
});

// ───────────────────────── grading policies ─────────────────────────

it('saves a branch grading policy and rejects overlapping grade windows', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));

    $letter = GradingScale::whereNull('school_id')->where('code', 'et-letter')->first();

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/grading-policies', [
            'grading_scale_id' => $letter->id,
            'display' => 'both',
            'min_grade_sort' => 12, // G9
        ])
        ->assertCreated();

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/grading-policies', [
            'grading_scale_id' => $letter->id,
            'display' => 'letter',
            'min_grade_sort' => 13,
        ])
        ->assertStatus(422);
});

// ───────────────────────── grade books ─────────────────────────

it('lets a director create a grade book whose weights must sum to 100', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));
    $year = gradingYear($branch);
    $term = $year->terms()->first();

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/continuous-assessments', [
            'term_id' => $term->id,
            'name' => 'Standard plan',
            'targets' => [['grade_level_id' => null, 'section_ids' => null, 'subject_ids' => null]],
            'items' => [
                ['type' => 'quiz', 'name' => 'Quiz 1', 'weight' => 10, 'max_score' => 10],
                ['type' => 'mid_exam', 'name' => 'Mid exam', 'weight' => 30, 'max_score' => 30],
            ],
        ])
        ->assertStatus(422); // sums to 40

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/continuous-assessments', [
            'term_id' => $term->id,
            'name' => 'Standard plan',
            'targets' => [['grade_level_id' => null, 'section_ids' => null, 'subject_ids' => null]],
            'items' => [
                ['type' => 'quiz', 'name' => 'Quiz 1', 'weight' => 10, 'max_score' => 10],
                ['type' => 'assignment', 'name' => 'Assignment', 'weight' => 20, 'max_score' => 20],
                ['type' => 'mid_exam', 'name' => 'Mid exam', 'weight' => 30, 'max_score' => 30],
                ['type' => 'final_exam', 'name' => 'Final exam', 'weight' => 40, 'max_score' => 40],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('data.total_weight', 100);
});

it('forbids teachers from creating grade books', function () {
    $branch = makeBranch();
    $teacher = memberOf($branch);
    Sanctum::actingAs($teacher);
    $year = gradingYear($branch);

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/continuous-assessments', [
            'term_id' => $year->terms()->first()->id,
            'name' => 'Rogue plan',
            'items' => [['type' => 'final_exam', 'name' => 'Final', 'weight' => 100, 'max_score' => 100]],
        ])
        ->assertForbidden();
});

// ───────────────────────── marklists ─────────────────────────

it('materialises the grade book into the teacher marklist and locks structure', function () {
    $branch = makeBranch();
    $director = directorOf($branch);
    Sanctum::actingAs($director);
    $year = gradingYear($branch);
    $term = $year->terms()->first();
    $section = gradingSection($branch);

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/continuous-assessments', [
            'term_id' => $term->id,
            'name' => 'Standard plan',
            'targets' => [['grade_level_id' => null, 'section_ids' => null, 'subject_ids' => null]],
            'items' => [
                ['type' => 'mid_exam', 'name' => 'Mid exam', 'weight' => 40, 'max_score' => 40],
                ['type' => 'final_exam', 'name' => 'Final exam', 'weight' => 60, 'max_score' => 60],
            ],
        ])->assertCreated();

    $teacher = gradingTeacher($branch);
    $assignment = gradingAssignment($branch, $year, $section, $teacher);
    gradingEnroll(gradingStudent($branch), $year, $section);

    Membership::create([
        'user_id' => $teacher->user_id,
        'school_id' => $branch->school_id,
        'branch_id' => $branch->id,
        'role' => Role::Teacher->value,
        'scope' => Role::Teacher->scope()->value,
        'is_active' => true,
    ]);

    Sanctum::actingAs($teacher->user);

    $grid = $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/marklists/{$assignment->id}")
        ->assertOk()
        ->json('data');

    expect(collect($grid['assessments'])->pluck('name'))->toContain('Mid exam', 'Final exam')
        ->and($grid['continuous_assessment']['name'])->toBe('Standard plan')
        ->and($grid['marklist']['status'])->toBe('draft');

    // The teacher cannot add ad-hoc assessments where a plan governs…
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/subject-assignments/{$assignment->id}/assessments", [
            'type' => 'quiz', 'name' => 'Surprise quiz', 'max_score' => 10, 'weight' => 10,
        ])
        ->assertStatus(422);

    // …nor edit or delete a planned assessment.
    $planned = $assignment->assessments()->first();
    $this->withHeaders(branchContext($branch))
        ->putJson("/api/v1/assessments/{$planned->id}", ['weight' => 90])
        ->assertStatus(422);
    $this->withHeaders(branchContext($branch))
        ->deleteJson("/api/v1/assessments/{$planned->id}")
        ->assertStatus(422);
});

it('runs the submit → approve → reopen marklist workflow with mark locking', function () {
    $branch = makeBranch();
    $director = directorOf($branch);
    $year = gradingYear($branch);
    $section = gradingSection($branch);
    $teacher = gradingTeacher($branch);
    $assignment = gradingAssignment($branch, $year, $section, $teacher);
    $student = gradingStudent($branch);
    gradingEnroll($student, $year, $section);

    // Teacher membership so grades.manage_own applies.
    Membership::create([
        'user_id' => $teacher->user_id,
        'school_id' => $branch->school_id,
        'branch_id' => $branch->id,
        'role' => Role::Teacher->value,
        'scope' => Role::Teacher->scope()->value,
        'is_active' => true,
    ]);

    Sanctum::actingAs($teacher->user);
    $headers = branchContext($branch);

    // Teacher-defined assessments are a branch opt-in (off by default).
    $branch->update(['settings' => ['teacher_assessments_enabled' => true]]);

    // Free-form continuous assessment (no plan): teacher creates the final exam and marks it.
    $assessmentId = $this->withHeaders($headers)
        ->postJson("/api/v1/subject-assignments/{$assignment->id}/assessments", [
            'type' => 'final_exam', 'name' => 'Final', 'max_score' => 100, 'weight' => 100,
        ])->assertCreated()->json('data.id');

    $this->withHeaders($headers)
        ->postJson("/api/v1/assessments/{$assessmentId}/results", [
            'results' => [['student_id' => $student->id, 'score' => 88]],
        ])->assertOk();

    // Scores above max are rejected.
    $this->withHeaders($headers)
        ->postJson("/api/v1/assessments/{$assessmentId}/results", [
            'results' => [['student_id' => $student->id, 'score' => 105]],
        ])->assertStatus(422);

    // Submit → marks lock.
    $this->withHeaders($headers)
        ->postJson("/api/v1/marklists/{$assignment->id}/submit")
        ->assertOk()
        ->assertJsonPath('data.status', 'submitted');

    $this->withHeaders($headers)
        ->postJson("/api/v1/assessments/{$assessmentId}/results", [
            'results' => [['student_id' => $student->id, 'score' => 90]],
        ])->assertStatus(422);

    // Teachers cannot approve.
    $this->withHeaders($headers)
        ->postJson("/api/v1/marklists/{$assignment->id}/approve")
        ->assertForbidden();

    // The director approves; the teacher can no longer reopen.
    Sanctum::actingAs($director);
    $this->withHeaders($headers)
        ->postJson("/api/v1/marklists/{$assignment->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    Sanctum::actingAs($teacher->user);
    $this->withHeaders($headers)
        ->postJson("/api/v1/marklists/{$assignment->id}/reopen")
        ->assertForbidden();

    // The director reopens; marks are editable again.
    Sanctum::actingAs($director);
    $this->withHeaders($headers)
        ->postJson("/api/v1/marklists/{$assignment->id}/reopen")
        ->assertOk()
        ->assertJsonPath('data.status', 'draft');

    Sanctum::actingAs($teacher->user);
    $this->withHeaders($headers)
        ->postJson("/api/v1/assessments/{$assessmentId}/results", [
            'results' => [['student_id' => $student->id, 'score' => 90]],
        ])->assertOk();
});

it('keeps marklists invisible to teachers of other schools', function () {
    $branch = makeBranch();
    $year = gradingYear($branch);
    $section = gradingSection($branch);
    $assignment = gradingAssignment($branch, $year, $section, gradingTeacher($branch));

    $otherBranch = makeBranch('AA-0002');
    Sanctum::actingAs(memberOf($otherBranch));

    $this->withHeaders(branchContext($otherBranch))
        ->getJson("/api/v1/marklists/{$assignment->id}")
        ->assertForbidden();
});

// ───────────────────────── grading in the freeze + documents ─────────────────────────

it('freezes letters through the branch policy and serves report card + transcript', function () {
    $branch = makeBranch();
    $director = directorOf($branch);
    Sanctum::actingAs($director);
    $year = gradingYear($branch);
    $term = $year->terms()->first();
    $section = gradingSection($branch);

    // Letters for every grade at this branch.
    $letter = GradingScale::whereNull('school_id')->where('code', 'et-letter')->first();
    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/grading-policies', [
            'grading_scale_id' => $letter->id,
            'display' => 'both',
        ])->assertCreated();

    $student = gradingStudent($branch);
    $enrollment = gradingEnroll($student, $year, $section);

    $assignment = gradingAssignment($branch, $year, $section);
    $assessment = $assignment->assessments()->create([
        'type' => 'final_exam', 'name' => 'Final', 'max_score' => 100, 'weight' => 100,
    ]);
    $assessment->results()->create(['student_id' => $student->id, 'score' => 84]);

    app(ComputeTermResultsAction::class)->execute($term);

    $result = StudentTermResult::firstWhere('student_enrollment_id', $enrollment->id);
    expect($result->grading['scale']['code'])->toBe('et-letter')
        ->and($result->grading['display'])->toBe('both')
        ->and($result->grading['overall']['letter'])->toBe('B')
        ->and($result->breakdown[0]['letter'])->toBe('B')
        ->and($result->breakdown[0]['is_passing'])->toBeTrue();

    // Conduct + comment overlay survives a recompute.
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/terms/{$term->id}/conduct", [
            'rows' => [['student_enrollment_id' => $enrollment->id, 'conduct' => 'A', 'comment' => 'Excellent behaviour']],
        ])->assertOk();

    app(ComputeTermResultsAction::class)->execute($term);
    expect($result->refresh()->conduct)->toBe('A');

    // Official report card reads the frozen row.
    $card = $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/reports/students/{$student->id}/report-card?term_id={$term->id}")
        ->assertOk()
        ->json('data');

    expect($card['average'])->toEqual(84.0)
        ->and($card['grading']['overall']['letter'])->toBe('B')
        ->and($card['conduct'])->toBe('A');

    // Transcript aggregates the frozen rows per year.
    $transcript = $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/reports/students/{$student->id}/transcript")
        ->assertOk()
        ->json('data');

    expect($transcript['years'])->toHaveCount(1)
        ->and($transcript['years'][0]['annual_average'])->toEqual(84.0)
        ->and($transcript['years'][0]['terms'][0]['rank'])->toBe(1);
});

// ───────────────────────── role coverage ─────────────────────────

it('lets a registrar view results and report cards but not manage grading', function () {
    $branch = makeBranch();
    $year = gradingYear($branch);
    $term = $year->terms()->first();
    $section = gradingSection($branch);
    $student = gradingStudent($branch);
    gradingEnroll($student, $year, $section);

    $assignment = gradingAssignment($branch, $year, $section);
    $assessment = $assignment->assessments()->create([
        'type' => 'final_exam', 'name' => 'Final', 'max_score' => 100, 'weight' => 100,
    ]);
    $assessment->results()->create(['student_id' => $student->id, 'score' => 75]);
    app(ComputeTermResultsAction::class)->execute($term);

    Sanctum::actingAs(memberOf($branch, Role::Registrar));
    $headers = branchContext($branch);

    $this->withHeaders($headers)->getJson("/api/v1/terms/{$term->id}/results")->assertOk();
    $this->withHeaders($headers)
        ->getJson("/api/v1/reports/students/{$student->id}/report-card?term_id={$term->id}")
        ->assertOk()
        ->assertJsonPath('data.average', 75);
    $this->withHeaders($headers)->getJson("/api/v1/terms/{$term->id}/grading-report")->assertOk();

    // No structural authority: grade books, scales, compute, approve.
    $this->withHeaders($headers)->postJson('/api/v1/continuous-assessments', [
        'term_id' => $term->id, 'name' => 'X',
        'items' => [['type' => 'final_exam', 'name' => 'F', 'weight' => 100, 'max_score' => 100]],
    ])->assertForbidden();
    $this->withHeaders($headers)->postJson("/api/v1/terms/{$term->id}/compute-results")->assertForbidden();
    $this->withHeaders($headers)->postJson("/api/v1/marklists/{$assignment->id}/approve")->assertForbidden();
});

it('serves the frozen report card and transcript through the parent /me lane', function () {
    $branch = makeBranch();
    $year = gradingYear($branch);
    $term = $year->terms()->first();
    $section = gradingSection($branch);
    $student = gradingStudent($branch);
    gradingEnroll($student, $year, $section);

    $assignment = gradingAssignment($branch, $year, $section);
    $assessment = $assignment->assessments()->create([
        'type' => 'final_exam', 'name' => 'Final', 'max_score' => 100, 'weight' => 100,
    ]);
    $assessment->results()->create(['student_id' => $student->id, 'score' => 91]);
    app(ComputeTermResultsAction::class)->execute($term);

    $parentUser = User::factory()->create();
    $parent = ParentProfile::create(['user_id' => $parentUser->id]);
    StudentGuardian::create([
        'student_id' => $student->id, 'parent_id' => $parent->id,
        'relationship' => 'father', 'is_active' => true, 'can_view_grades' => true,
    ]);

    Sanctum::actingAs($parentUser);

    $this->getJson("/api/v1/me/children/{$student->id}/report-card?term_id={$term->id}")
        ->assertOk()
        ->assertJsonPath('data.average', 91);
    $this->getJson("/api/v1/me/children/{$student->id}/transcript")
        ->assertOk()
        ->assertJsonPath('data.years.0.annual_average', 91);

    // The per-link grades flag is honored.
    StudentGuardian::query()->update(['can_view_grades' => false]);
    $this->getJson("/api/v1/me/children/{$student->id}/report-card?term_id={$term->id}")
        ->assertForbidden();
    $this->getJson("/api/v1/me/children/{$student->id}/transcript")->assertForbidden();
});

it('serves the own report card and transcript to a student account', function () {
    $branch = makeBranch();
    $year = gradingYear($branch);
    $term = $year->terms()->first();
    $section = gradingSection($branch);
    $student = gradingStudent($branch);
    $student->update(['user_id' => User::factory()->create()->id]);
    gradingEnroll($student, $year, $section);

    $assignment = gradingAssignment($branch, $year, $section);
    $assessment = $assignment->assessments()->create([
        'type' => 'final_exam', 'name' => 'Final', 'max_score' => 100, 'weight' => 100,
    ]);
    $assessment->results()->create(['student_id' => $student->id, 'score' => 66]);
    app(ComputeTermResultsAction::class)->execute($term);

    Sanctum::actingAs($student->fresh()->user);

    $this->getJson("/api/v1/me/student/report-card?term_id={$term->id}")
        ->assertOk()
        ->assertJsonPath('data.average', 66);
    $this->getJson('/api/v1/me/student/transcript')
        ->assertOk()
        ->assertJsonPath('data.years.0.terms.0.average', 66);
});

it('lets a school-wide principal create a grade book by naming the branch', function () {
    $branch = makeBranch();
    Sanctum::actingAs(schoolPrincipal($branch));
    $year = gradingYear($branch);
    $term = $year->terms()->first();

    // School-wide workspace (no X-Branch-Id): the payload names the branch.
    $this->withHeaders(schoolContext($branch))
        ->postJson('/api/v1/continuous-assessments', [
            'branch_id' => $branch->id,
            'term_id' => $term->id,
            'name' => 'School-wide plan',
            'targets' => [['grade_level_id' => null, 'section_ids' => null, 'subject_ids' => null]],
            'items' => [['type' => 'final_exam', 'name' => 'Final', 'weight' => 100, 'max_score' => 100]],
        ])
        ->assertCreated()
        ->assertJsonPath('data.branch_id', $branch->id);

    // Without naming a branch the write is refused (never guess a branch).
    $this->withHeaders(schoolContext($branch))
        ->postJson('/api/v1/continuous-assessments', [
            'term_id' => $term->id,
            'name' => 'No branch named',
            'items' => [['type' => 'final_exam', 'name' => 'Final', 'weight' => 100, 'max_score' => 100]],
        ])
        ->assertStatus(422);
});

it('lets the homeroom teacher save conduct but blocks other teachers', function () {
    $branch = makeBranch();
    $year = gradingYear($branch);
    $term = $year->terms()->first();
    $section = gradingSection($branch);
    $student = gradingStudent($branch);
    $enrollment = gradingEnroll($student, $year, $section);

    $assignment = gradingAssignment($branch, $year, $section);
    $assessment = $assignment->assessments()->create([
        'type' => 'final_exam', 'name' => 'Final', 'max_score' => 100, 'weight' => 100,
    ]);
    $assessment->results()->create(['student_id' => $student->id, 'score' => 80]);
    app(ComputeTermResultsAction::class)->execute($term);

    $homeroom = gradingTeacher($branch, 'Hana');
    Membership::create([
        'user_id' => $homeroom->user_id, 'school_id' => $branch->school_id,
        'branch_id' => $branch->id, 'role' => Role::Teacher->value,
        'scope' => Role::Teacher->scope()->value, 'is_active' => true,
    ]);
    SectionHomeroom::create([
        'section_id' => $section->id, 'academic_year_id' => $year->id, 'employee_id' => $homeroom->id,
    ]);

    Sanctum::actingAs($homeroom->user);
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/terms/{$term->id}/conduct", [
            'rows' => [['student_enrollment_id' => $enrollment->id, 'conduct' => 'B', 'comment' => 'Good']],
        ])->assertOk();

    expect(StudentTermResult::firstWhere('student_enrollment_id', $enrollment->id)->conduct)->toBe('B');

    // A teacher without the homeroom cannot.
    $other = gradingTeacher($branch, 'Kebede');
    Membership::create([
        'user_id' => $other->user_id, 'school_id' => $branch->school_id,
        'branch_id' => $branch->id, 'role' => Role::Teacher->value,
        'scope' => Role::Teacher->scope()->value, 'is_active' => true,
    ]);
    Sanctum::actingAs($other->user);
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/terms/{$term->id}/conduct", [
            'rows' => [['student_enrollment_id' => $enrollment->id, 'conduct' => 'A']],
        ])->assertForbidden();
});

it('aggregates the grading report from frozen rows', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));
    $year = gradingYear($branch);
    $term = $year->terms()->first();
    $section = gradingSection($branch);

    $abel = gradingStudent($branch, 'Abel');
    $marta = gradingStudent($branch, 'Marta');
    gradingEnroll($abel, $year, $section);
    gradingEnroll($marta, $year, $section);

    $assignment = gradingAssignment($branch, $year, $section);
    $assessment = $assignment->assessments()->create([
        'type' => 'final_exam', 'name' => 'Final', 'max_score' => 100, 'weight' => 100,
    ]);
    $assessment->results()->create(['student_id' => $abel->id, 'score' => 40]);
    $assessment->results()->create(['student_id' => $marta->id, 'score' => 90]);
    app(ComputeTermResultsAction::class)->execute($term);

    $report = $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/terms/{$term->id}/grading-report")
        ->assertOk()
        ->json('data');

    expect($report['totals']['students'])->toBe(2)
        ->and($report['totals']['average'])->toEqual(65.0)
        ->and($report['totals']['pass_rate'])->toEqual(50.0)
        ->and($report['subjects'][0]['students'])->toBe(2)
        ->and($report['marklists']['total'])->toBe(1)
        ->and($report['top_students'][0]['full_name'])->toContain('Marta')
        ->and($report['at_risk'])->toHaveCount(1)
        ->and($report['at_risk'][0]['full_name'])->toContain('Abel')
        ->and($report['gender'][0]['gender'])->toBe('male')
        ->and($report['gender'][0]['students'])->toBe(2)
        ->and($report['previous'])->toBeNull();

    // Grade narrowing: the section's grade keeps everything, another empties it.
    $gradeId = $section->grade_level_id;
    $narrowed = $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/terms/{$term->id}/grading-report?grade_level_id={$gradeId}")
        ->assertOk()
        ->json('data');
    expect($narrowed['totals']['students'])->toBe(2)
        ->and($narrowed['marklists']['total'])->toBe(1);

    $otherGrade = GradeLevel::where('code', 'G2')->value('id');
    $empty = $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/terms/{$term->id}/grading-report?grade_level_id={$otherGrade}")
        ->assertOk()
        ->json('data');
    expect($empty['totals']['students'])->toBe(0)
        ->and($empty['marklists']['total'])->toBe(0);
});

// ───────────────────────── section roster (class profile) ─────────────────────────

it('serves the section roster with frozen marks, homeroom and composition', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));
    $year = gradingYear($branch);
    $term = $year->terms()->first();
    $section = gradingSection($branch);

    $abel = gradingStudent($branch, 'Abel');
    $marta = $branch->students()->create([
        'school_id' => $branch->school_id,
        'first_name' => 'Marta',
        'father_name' => 'Kebede',
        'gender' => 'female',
        'date_of_birth' => now()->subYears(10)->toDateString(),
    ]);
    $abelEnrollment = gradingEnroll($abel, $year, $section);
    gradingEnroll($marta, $year, $section);

    // A pending enrollment holds a seat but must appear on NO class list.
    $ghost = gradingStudent($branch, 'Ghost');
    StudentEnrollment::create([
        'student_id' => $ghost->id,
        'school_id' => $branch->school_id,
        'branch_id' => $branch->id,
        'academic_year_id' => $year->id,
        'school_program_id' => $abelEnrollment->school_program_id,
        'section_id' => $section->id,
        'grade_level_id' => $section->grade_level_id,
        'status' => 'pending',
    ]);

    $homeroom = gradingTeacher($branch, 'Hana');
    // International input is accepted and normalised to the canonical local form
    // (App\Support\PhoneNumber) — asserted on the roster payload below.
    $homeroom->update(['phone' => '+251911000000']);
    SectionHomeroom::create([
        'section_id' => $section->id, 'academic_year_id' => $year->id, 'employee_id' => $homeroom->id,
    ]);

    $assignment = gradingAssignment($branch, $year, $section);
    $assessment = $assignment->assessments()->create([
        'type' => 'final_exam', 'name' => 'Final', 'max_score' => 100, 'weight' => 100,
    ]);
    $assessment->results()->create(['student_id' => $abel->id, 'score' => 40]);
    $assessment->results()->create(['student_id' => $marta->id, 'score' => 90]);
    app(ComputeTermResultsAction::class)->execute($term);

    $roster = $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/sections/{$section->id}/roster?term_id={$term->id}")
        ->assertOk()
        ->json('data');

    expect($roster['summary']['students'])->toBe(2)
        ->and($roster['summary']['male'])->toBe(1)
        ->and($roster['summary']['female'])->toBe(1)
        ->and($roster['summary']['average'])->toEqual(65.0)
        ->and($roster['homeroom']['name'])->toContain('Hana')
        ->and($roster['homeroom']['phone'])->toBe('0911000000')
        ->and($roster['subjects_count'])->toBe(1)
        ->and($roster['can_view_marks'])->toBeTrue()
        ->and(collect($roster['students'])->pluck('full_name')->join(' '))->not->toContain('Ghost');

    $martaRow = collect($roster['students'])->firstWhere('student_id', $marta->id);
    expect($martaRow['result']['average'])->toEqual(90.0)
        ->and($martaRow['result']['rank'])->toBe(1)
        ->and($martaRow['gender'])->toBe('female')
        ->and($martaRow['date_of_birth'])->not->toBeNull();
});

it('rejects a roster request whose term belongs to another branch', function () {
    $branch = makeBranch();
    $year = gradingYear($branch);
    $section = gradingSection($branch);

    $otherBranch = makeBranch('AA-0002');
    $otherTerm = gradingYear($otherBranch)->terms()->first();

    Sanctum::actingAs(directorOf($branch));
    $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/sections/{$section->id}/roster?term_id={$otherTerm->id}")
        ->assertNotFound();

    // And the section itself stays invisible across schools.
    Sanctum::actingAs(memberOf($otherBranch));
    $this->withHeaders(branchContext($otherBranch))
        ->getJson("/api/v1/sections/{$section->id}/roster?term_id={$year->terms()->first()->id}")
        ->assertForbidden();
});

// ───────────────────────── per-enrollment results (profile modal) ─────────────────────────

it('serves one enrollment history of frozen results and guards the scope', function () {
    $branch = makeBranch();
    $year = gradingYear($branch);
    $term = $year->terms()->first();
    $section = gradingSection($branch);
    $student = gradingStudent($branch);
    $enrollment = gradingEnroll($student, $year, $section);

    $assignment = gradingAssignment($branch, $year, $section);
    $assessment = $assignment->assessments()->create([
        'type' => 'final_exam', 'name' => 'Final', 'max_score' => 100, 'weight' => 100,
    ]);
    $assessment->results()->create(['student_id' => $student->id, 'score' => 77]);
    app(ComputeTermResultsAction::class)->execute($term);

    Sanctum::actingAs(directorOf($branch));
    $results = $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/student-enrollments/{$enrollment->id}/results")
        ->assertOk()
        ->json('data');

    expect($results)->toHaveCount(1)
        ->and($results[0]['average'])->toEqual(77.0)
        ->and($results[0]['term']['name'])->not->toBeNull()
        ->and($results[0]['breakdown'][0]['total'])->toEqual(77.0);

    // Another school sees nothing.
    $otherBranch = makeBranch('AA-0002');
    Sanctum::actingAs(memberOf($otherBranch));
    $this->withHeaders(branchContext($otherBranch))
        ->getJson("/api/v1/student-enrollments/{$enrollment->id}/results")
        ->assertForbidden();
});

// ───────────────────────── term results grade filter ─────────────────────────

it('filters term results by grade level and names the grade on each row', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));
    $year = gradingYear($branch);
    $term = $year->terms()->first();
    $section = gradingSection($branch);
    $student = gradingStudent($branch);
    gradingEnroll($student, $year, $section);

    $assignment = gradingAssignment($branch, $year, $section);
    $assessment = $assignment->assessments()->create([
        'type' => 'final_exam', 'name' => 'Final', 'max_score' => 100, 'weight' => 100,
    ]);
    $assessment->results()->create(['student_id' => $student->id, 'score' => 82]);
    app(ComputeTermResultsAction::class)->execute($term);

    $rows = $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/terms/{$term->id}/results?grade_level_id={$section->grade_level_id}")
        ->assertOk()
        ->json('data');
    expect($rows)->toHaveCount(1)
        ->and($rows[0]['grade_level_name'])->not->toBeNull();

    $otherGrade = GradeLevel::where('code', 'G2')->value('id');
    $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/terms/{$term->id}/results?grade_level_id={$otherGrade}")
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

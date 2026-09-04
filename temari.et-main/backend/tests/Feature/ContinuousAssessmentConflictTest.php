<?php

use App\Actions\SaveAcademicYearAction;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AssessmentResult;
use App\Models\Branch;
use App\Models\ContinuousAssessment;
use App\Models\Employee;
use App\Models\GradeLevel;
use App\Models\Section;
use App\Models\Subject;
use App\Models\SubjectAssignment;
use Database\Seeders\GradeLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SubjectSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

/*
 * Continuous-assessment plans target any mix of grade → sections → subjects.
 * Overlapping plans must never STACK on a shared marklist (4 planned + 5
 * planned = 9 columns). Saving an overlapping plan either passes cleanly (a
 * more specific plan out-precedes an unmarked general one), or returns the
 * conflict sheet (409) until the office confirms `replace` or `migrate`.
 */

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(GradeLevelSeeder::class);
    $this->seed(SubjectSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function caYear(Branch $branch): AcademicYear
{
    return (new SaveAcademicYearAction())->execute($branch, ['name' => '2017 E.C.', 'status' => 'active']);
}

function caSection(Branch $branch, string $gradeCode = 'G1', string $name = 'A'): Section
{
    return $branch->sections()->create([
        'school_id' => $branch->school_id,
        'grade_level_id' => GradeLevel::where('code', $gradeCode)->value('id'),
        'name' => $name,
    ]);
}

function caAssignment(Branch $branch, AcademicYear $year, Section $section, ?Employee $teacher = null, string $subjectCode = 'MATH'): SubjectAssignment
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

/** Targeting row helpers (grade → sections → subjects; null/[] = all). */
function caAllGrades(): array
{
    return ['grade_level_id' => null, 'section_ids' => null, 'subject_ids' => null];
}

function caSubjectTarget(string $subjectCode): array
{
    return ['grade_level_id' => null, 'section_ids' => null, 'subject_ids' => [Subject::where('code', $subjectCode)->value('id')]];
}

/** @return array<string, mixed> */
function caPlan(int $termId, array $overrides = []): array
{
    return [
        'term_id' => $termId,
        'name' => 'Plan',
        'targets' => [caAllGrades()],
        'items' => [
            ['type' => 'quiz', 'name' => 'Quiz', 'weight' => 40, 'max_score' => 10],
            ['type' => 'final_exam', 'name' => 'Final', 'weight' => 60, 'max_score' => 60],
        ],
        ...$overrides,
    ];
}

/** Open the marklist as the acting user so the plan materialises. */
function caOpenMarklist($test, Branch $branch, SubjectAssignment $assignment): array
{
    return $test->withHeaders(branchContext($branch))
        ->getJson("/api/v1/marklists/{$assignment->id}")
        ->assertOk()
        ->json('data');
}

it('creates a more specific plan over an unmarked general one without a prompt, and the marklist never stacks', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));
    $year = caYear($branch);
    $term = $year->terms()->first();
    $assignment = caAssignment($branch, $year, caSection($branch));

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/continuous-assessments', caPlan($term->id, ['name' => 'General plan']))
        ->assertCreated();

    // Materialise the general plan's two columns.
    expect(caOpenMarklist($this, $branch, $assignment)['assessments'])->toHaveCount(2);

    // Math-specific override — no marks recorded yet, so no confirmation.
    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/continuous-assessments', caPlan($term->id, [
            'name' => 'Math plan',
            'targets' => [caSubjectTarget('MATH')],
            'items' => [
                ['type' => 'quiz', 'name' => 'Quiz 1', 'weight' => 20, 'max_score' => 10],
                ['type' => 'mid_exam', 'name' => 'Mid', 'weight' => 30, 'max_score' => 30],
                ['type' => 'final_exam', 'name' => 'Final', 'weight' => 50, 'max_score' => 50],
            ],
        ]))
        ->assertCreated();

    // 3 columns from the Math plan — never 2 + 3 = 5.
    $grid = caOpenMarklist($this, $branch, $assignment);
    expect($grid['assessments'])->toHaveCount(3)
        ->and($grid['continuous_assessment']['name'])->toBe('Math plan');
});

it('returns the conflict sheet when the overlap carries recorded marks', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));
    $year = caYear($branch);
    $term = $year->terms()->first();
    $assignment = caAssignment($branch, $year, caSection($branch));

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/continuous-assessments', caPlan($term->id, ['name' => 'General plan']))
        ->assertCreated();

    caOpenMarklist($this, $branch, $assignment);

    $quiz = Assessment::where('subject_assignment_id', $assignment->id)->where('type', 'quiz')->firstOrFail();
    $student = $branch->students()->create([
        'school_id' => $branch->school_id, 'first_name' => 'Abel', 'father_name' => 'Tesfaye', 'gender' => 'male',
    ]);
    AssessmentResult::create(['assessment_id' => $quiz->id, 'student_id' => $student->id, 'score' => 8]);

    $response = $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/continuous-assessments', caPlan($term->id, [
            'name' => 'Math plan',
            'targets' => [caSubjectTarget('MATH')],
        ]))
        ->assertStatus(409)
        ->json();

    expect($response['code'])->toBe('plan_conflict')
        ->and($response['conflicts']['books'])->toHaveCount(1)
        ->and($response['conflicts']['books'][0]['name'])->toBe('General plan')
        ->and($response['conflicts']['books'][0]['marks_count'])->toBe(1)
        ->and($response['conflicts']['books'][0]['targets'][0]['grade_level_id'])->toBeNull();
});

it('replace starts fresh: overlapping columns and their marks are removed', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));
    $year = caYear($branch);
    $term = $year->terms()->first();
    $assignment = caAssignment($branch, $year, caSection($branch));

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/continuous-assessments', caPlan($term->id, ['name' => 'General plan']))
        ->assertCreated();

    caOpenMarklist($this, $branch, $assignment);

    $quiz = Assessment::where('subject_assignment_id', $assignment->id)->where('type', 'quiz')->firstOrFail();
    $student = $branch->students()->create([
        'school_id' => $branch->school_id, 'first_name' => 'Abel', 'father_name' => 'Tesfaye', 'gender' => 'male',
    ]);
    AssessmentResult::create(['assessment_id' => $quiz->id, 'student_id' => $student->id, 'score' => 8]);

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/continuous-assessments', caPlan($term->id, [
            'name' => 'Math plan',
            'targets' => [caSubjectTarget('MATH')],
            'conflict_strategy' => 'replace',
        ]))
        ->assertCreated();

    $grid = caOpenMarklist($this, $branch, $assignment);
    expect($grid['assessments'])->toHaveCount(2)
        ->and($grid['continuous_assessment']['name'])->toBe('Math plan');

    // The old quiz mark is gone with its column.
    expect(AssessmentResult::where('student_id', $student->id)->count())->toBe(0);
    // The general plan survives — it still governs every other subject.
    expect(ContinuousAssessment::where('name', 'General plan')->where('is_active', true)->exists())->toBeTrue();
});

it('migrate carries marks to same-type columns and rescales scores', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));
    $year = caYear($branch);
    $term = $year->terms()->first();
    $assignment = caAssignment($branch, $year, caSection($branch));

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/continuous-assessments', caPlan($term->id, ['name' => 'General plan']))
        ->assertCreated();

    caOpenMarklist($this, $branch, $assignment);

    $quiz = Assessment::where('subject_assignment_id', $assignment->id)->where('type', 'quiz')->firstOrFail();
    $student = $branch->students()->create([
        'school_id' => $branch->school_id, 'first_name' => 'Abel', 'father_name' => 'Tesfaye', 'gender' => 'male',
    ]);
    // 8 / 10 on the old quiz.
    AssessmentResult::create(['assessment_id' => $quiz->id, 'student_id' => $student->id, 'score' => 8]);

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/continuous-assessments', caPlan($term->id, [
            'name' => 'Math plan',
            'targets' => [caSubjectTarget('MATH')],
            'items' => [
                ['type' => 'quiz', 'name' => 'Quiz 1', 'weight' => 40, 'max_score' => 20],
                ['type' => 'final_exam', 'name' => 'Final', 'weight' => 60, 'max_score' => 60],
            ],
            'conflict_strategy' => 'migrate',
        ]))
        ->assertCreated();

    $grid = caOpenMarklist($this, $branch, $assignment);
    expect($grid['assessments'])->toHaveCount(2);

    // 8/10 became 16/20 on the new quiz column.
    $newQuiz = Assessment::where('subject_assignment_id', $assignment->id)
        ->where('type', 'quiz')->where('max_score', 20)->firstOrFail();
    $migrated = AssessmentResult::where('assessment_id', $newQuiz->id)->where('student_id', $student->id)->firstOrFail();
    expect((float) $migrated->score)->toBe(16.0)
        ->and(AssessmentResult::where('student_id', $student->id)->count())->toBe(1);
});

it('targets specific sections only — an untargeted section runs free-form', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));
    $year = caYear($branch);
    $term = $year->terms()->first();

    $sectionA = caSection($branch, 'G1', 'A');
    $sectionB = caSection($branch, 'G1', 'B');
    $assignmentA = caAssignment($branch, $year, $sectionA);
    $assignmentB = caAssignment($branch, $year, $sectionB);

    $g1 = GradeLevel::where('code', 'G1')->value('id');

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/continuous-assessments', caPlan($term->id, [
            'name' => 'Section A plan',
            'targets' => [['grade_level_id' => $g1, 'section_ids' => [$sectionA->id], 'subject_ids' => null]],
        ]))
        ->assertCreated();

    // Section A gets the plan; section B stays free-form (no plan governs it).
    $gridA = caOpenMarklist($this, $branch, $assignmentA);
    expect($gridA['continuous_assessment']['name'] ?? null)->toBe('Section A plan');

    $gridB = caOpenMarklist($this, $branch, $assignmentB);
    expect($gridB['continuous_assessment'] ?? null)->toBeNull()
        ->and($gridB['can_define_assessments'])->toBeTrue();
});

it('retires a fully shadowed plan on replace', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));
    $year = caYear($branch);
    $term = $year->terms()->first();
    $section = caSection($branch);
    caAssignment($branch, $year, $section);

    $g1 = GradeLevel::where('code', 'G1')->value('id');

    // Narrow plan: only section A's Math.
    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/continuous-assessments', caPlan($term->id, [
            'name' => 'Old section plan',
            'targets' => [['grade_level_id' => $g1, 'section_ids' => [$section->id], 'subject_ids' => null]],
        ]))
        ->assertCreated();

    // A wider plan that covers the same assignment, confirmed as replace.
    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/continuous-assessments', caPlan($term->id, [
            'name' => 'New grade plan',
            'targets' => [['grade_level_id' => $g1, 'section_ids' => null, 'subject_ids' => null]],
            'conflict_strategy' => 'replace',
        ]))
        ->assertCreated();

    expect(ContinuousAssessment::where('name', 'Old section plan')->exists())->toBeFalse()
        ->and(ContinuousAssessment::where('name', 'New grade plan')->where('is_active', true)->exists())->toBeTrue();
});

it('flags and clears free-form assessments when a plan takes over', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));
    $year = caYear($branch);
    $term = $year->terms()->first();
    $assignment = caAssignment($branch, $year, caSection($branch));

    // Free-form column with a recorded mark, before any plan exists.
    $adhoc = Assessment::create([
        'subject_assignment_id' => $assignment->id,
        'type' => 'test', 'name' => 'Teacher test', 'max_score' => 20, 'weight' => 20,
    ]);
    $student = $branch->students()->create([
        'school_id' => $branch->school_id, 'first_name' => 'Abel', 'father_name' => 'Tesfaye', 'gender' => 'male',
    ]);
    AssessmentResult::create(['assessment_id' => $adhoc->id, 'student_id' => $student->id, 'score' => 15]);

    $response = $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/continuous-assessments', caPlan($term->id))
        ->assertStatus(409)
        ->json();

    expect($response['conflicts']['free_form']['assessments'])->toBe(1)
        ->and($response['conflicts']['free_form']['marks_count'])->toBe(1);

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/continuous-assessments', caPlan($term->id, ['conflict_strategy' => 'replace']))
        ->assertCreated();

    $grid = caOpenMarklist($this, $branch, $assignment);
    expect($grid['assessments'])->toHaveCount(2)
        ->and(collect($grid['assessments'])->pluck('name'))->not->toContain('Teacher test');
});

it('rejects a duplicate grade and a non-exclusive all-grades row', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));
    $year = caYear($branch);
    $term = $year->terms()->first();
    $g1 = GradeLevel::where('code', 'G1')->value('id');

    // Same grade twice.
    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/continuous-assessments', caPlan($term->id, [
            'targets' => [
                ['grade_level_id' => $g1, 'section_ids' => null, 'subject_ids' => null],
                ['grade_level_id' => $g1, 'section_ids' => null, 'subject_ids' => null],
            ],
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrorFor('targets');

    // All-grades row alongside a specific grade.
    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/continuous-assessments', caPlan($term->id, [
            'targets' => [
                caAllGrades(),
                ['grade_level_id' => $g1, 'section_ids' => null, 'subject_ids' => null],
            ],
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrorFor('targets');
});

it('blocks a second plan that targets a grade+section slot another plan already owns', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));
    $year = caYear($branch);
    $term = $year->terms()->first();
    $g9 = GradeLevel::where('code', 'G9')->value('id');
    $section = caSection($branch, 'G9', 'A');
    $mathId = Subject::where('code', 'MATH')->value('id');

    $slot = fn (array $extra = []) => [
        'grade_level_id' => $g9,
        'section_ids' => [$section->id],
        'subject_ids' => null,
        ...$extra,
    ];

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/continuous-assessments', caPlan($term->id, [
            'name' => 'First plan',
            'targets' => [$slot()],
        ]))
        ->assertCreated();

    // Same grade + section + (all subjects) → duplicate, not the marks flow.
    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/continuous-assessments', caPlan($term->id, [
            'name' => 'Duplicate plan',
            'targets' => [$slot()],
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrorFor('targets');

    // A subject-specific override on the same slot IS allowed (precedence).
    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/continuous-assessments', caPlan($term->id, [
            'name' => 'Math override',
            'targets' => [$slot(['subject_ids' => [$mathId]])],
        ]))
        ->assertCreated();

    // Re-saving the first plan unchanged must not clash with itself.
    $first = ContinuousAssessment::where('name', 'First plan')->firstOrFail();
    $this->withHeaders(branchContext($branch))
        ->putJson("/api/v1/continuous-assessments/{$first->id}", caPlan($term->id, [
            'name' => 'First plan',
            'targets' => [$slot()],
        ]))
        ->assertOk();
});

it('gates teacher-defined assessments behind the branch setting', function () {
    $branch = makeBranch();
    $year = caYear($branch);
    $teacherUser = memberOf($branch);
    $employee = Employee::create([
        'user_id' => $teacherUser->id,
        'school_id' => $branch->school_id,
        'branch_id' => $branch->id,
        'first_name' => 'Alemu',
        'father_name' => 'Bekele',
        'gender' => 'male',
    ]);
    $assignment = caAssignment($branch, $year, caSection($branch), $employee);

    Sanctum::actingAs($teacherUser);

    $payload = ['type' => 'quiz', 'name' => 'My quiz', 'max_score' => 10, 'weight' => 10];

    // Off by default: the office defines the plan.
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/subject-assignments/{$assignment->id}/assessments", $payload)
        ->assertStatus(422);

    $branch->update(['settings' => ['teacher_assessments_enabled' => true]]);

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/subject-assignments/{$assignment->id}/assessments", $payload)
        ->assertCreated();

    // Supervisors are never gated by the branch opt-in.
    $branch->update(['settings' => ['teacher_assessments_enabled' => false]]);
    Sanctum::actingAs(directorOf($branch));

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/subject-assignments/{$assignment->id}/assessments", [
            'type' => 'assignment', 'name' => 'Office task', 'max_score' => 10, 'weight' => 10,
        ])
        ->assertCreated();
});

it('exposes the structure authority on the marklist grid', function () {
    $branch = makeBranch();
    $year = caYear($branch);
    $teacherUser = memberOf($branch);
    $employee = Employee::create([
        'user_id' => $teacherUser->id,
        'school_id' => $branch->school_id,
        'branch_id' => $branch->id,
        'first_name' => 'Alemu',
        'father_name' => 'Bekele',
        'gender' => 'male',
    ]);
    $assignment = caAssignment($branch, $year, caSection($branch), $employee);

    Sanctum::actingAs($teacherUser);

    expect(caOpenMarklist($this, $branch, $assignment)['can_define_assessments'])->toBeFalse();

    $branch->update(['settings' => ['teacher_assessments_enabled' => true]]);

    expect(caOpenMarklist($this, $branch, $assignment)['can_define_assessments'])->toBeTrue();
});

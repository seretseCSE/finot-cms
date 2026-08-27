<?php

use App\Actions\SaveAcademicYearAction;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Notification;
use App\Models\TeacherEvaluation;
use App\Models\User;
use App\Support\EvaluationPolicy;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

/** @return array{0: Branch, 1: Employee, 2: User, 3: int} */
function evalWorld(): array
{
    $branch = makeBranch();
    $year = (new SaveAcademicYearAction)->execute($branch, ['name' => '2017 E.C.', 'status' => 'active']);

    $teacherUser = memberOf($branch);
    $teacher = Employee::create([
        'user_id' => $teacherUser->id,
        'school_id' => $branch->school_id,
        'branch_id' => $branch->id,
        'first_name' => 'Alemu',
        'father_name' => 'Bekele',
        'gender' => 'male',
    ]);

    return [$branch, $teacher, $teacherUser, $year->terms()->first()->id];
}

it('provisions the MoE default rubric with weights summing to 100', function () {
    [$branch] = evalWorld();
    Sanctum::actingAs(directorOf($branch));

    $template = $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/hr/evaluation-template')
        ->assertOk()
        ->json('data');

    expect($template['criteria'])->toHaveCount(count(EvaluationPolicy::DEFAULT_CRITERIA))
        ->and((float) collect($template['criteria'])->sum('weight'))->toEqual(100.0);
});

it('rejects a rubric whose weights do not sum to 100', function () {
    [$branch] = evalWorld();
    Sanctum::actingAs(directorOf($branch));

    $templateId = $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/hr/evaluation-template')->json('data.id');

    $this->withHeaders(branchContext($branch))
        ->putJson("/api/v1/hr/evaluation-templates/{$templateId}", [
            'criteria' => [
                ['domain' => 'teaching', 'label' => 'Teaching', 'weight' => 60, 'max_score' => 5],
                ['domain' => 'ethics', 'label' => 'Ethics', 'weight' => 30, 'max_score' => 5],
            ],
        ])
        ->assertStatus(422);
});

it('runs the full appraisal ritual: draft → scored → shared → acknowledged', function () {
    [$branch, $teacher, $teacherUser, $termId] = evalWorld();
    $director = directorOf($branch);
    Sanctum::actingAs($director);

    $created = $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/hr/evaluations', [
            'employee_id' => $teacher->id,
            'term_id' => $termId,
        ])
        ->assertCreated()
        ->json('data');

    expect($created['status'])->toBe('draft')
        ->and($created['scores'])->toHaveCount(count(EvaluationPolicy::DEFAULT_CRITERIA));

    // A second appraisal for the same employee × term is refused.
    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/hr/evaluations', ['employee_id' => $teacher->id, 'term_id' => $termId])
        ->assertStatus(422);

    // Sharing an incompletely scored draft is refused.
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/hr/evaluations/{$created['id']}/submit")
        ->assertStatus(422);

    // Score everything at 4/5 → overall 80.
    $this->withHeaders(branchContext($branch))
        ->putJson("/api/v1/hr/evaluations/{$created['id']}", [
            'strengths' => 'Strong classroom presence.',
            'scores' => collect($created['scores'])
                ->map(fn (array $s): array => ['id' => $s['id'], 'score' => 4])
                ->all(),
        ])
        ->assertOk()
        ->assertJsonPath('data.overall_score', 80);

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/hr/evaluations/{$created['id']}/submit")
        ->assertOk()
        ->assertJsonPath('data.status', 'submitted');

    $note = Notification::where('user_id', $teacherUser->id)->first();
    expect($note?->event)->toBe('hr.evaluation_shared');

    // The teacher reads their own record and signs it with a comment.
    Sanctum::actingAs($teacherUser);
    $mine = $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/hr/evaluations?term_id={$termId}&mine=1")
        ->assertOk()
        ->json('data');
    expect($mine)->toHaveCount(1);

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/hr/evaluations/{$created['id']}/acknowledge", [
            'teacher_comment' => 'Seen — thank you for the feedback.',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'acknowledged');

    expect(Notification::where('user_id', $director->id)->where('event', 'hr.evaluation_acknowledged')->count())
        ->toBe(1);
});

it('keeps drafts invisible to the teacher and locks scoring to managers', function () {
    [$branch, $teacher, $teacherUser, $termId] = evalWorld();
    Sanctum::actingAs(directorOf($branch));

    $id = $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/hr/evaluations', ['employee_id' => $teacher->id, 'term_id' => $termId])
        ->json('data.id');

    Sanctum::actingAs($teacherUser);

    // A draft is the evaluator's private worksheet.
    $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/hr/evaluations/{$id}")
        ->assertForbidden();
    expect($this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/hr/evaluations?term_id={$termId}&mine=1")
        ->json('data'))->toHaveCount(0);

    // Teachers can neither list the register nor start appraisals.
    $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/hr/evaluations?term_id={$termId}")
        ->assertForbidden();
    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/hr/evaluations', ['employee_id' => $teacher->id, 'term_id' => $termId])
        ->assertForbidden();
});

it('locks the rubric to its own school — an outsider director gets 403', function () {
    [$branch] = evalWorld();
    Sanctum::actingAs(directorOf($branch));

    $templateId = $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/hr/evaluation-template')->json('data.id');

    // A director of ANOTHER school, in their own valid context, must not
    // reach this school's rubric.
    $otherBranch = makeBranch('AA-0002');
    Sanctum::actingAs(directorOf($otherBranch));

    $this->withHeaders(branchContext($otherBranch))
        ->putJson("/api/v1/hr/evaluation-templates/{$templateId}", [
            'criteria' => [
                ['domain' => 'teaching', 'label' => 'Hijacked', 'weight' => 100, 'max_score' => 5],
            ],
        ])
        ->assertForbidden();

    // The home director still can.
    Sanctum::actingAs(directorOf($branch));
    $this->withHeaders(branchContext($branch))
        ->putJson("/api/v1/hr/evaluation-templates/{$templateId}", [
            'criteria' => [
                ['domain' => 'teaching', 'label' => 'Teaching', 'weight' => 60, 'max_score' => 5],
                ['domain' => 'ethics', 'label' => 'Ethics', 'weight' => 40, 'max_score' => 5],
            ],
        ])
        ->assertOk();
});

it('isolates appraisals across schools', function () {
    [$branch, $teacher, , $termId] = evalWorld();
    Sanctum::actingAs(directorOf($branch));
    $id = $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/hr/evaluations', ['employee_id' => $teacher->id, 'term_id' => $termId])
        ->json('data.id');

    $otherBranch = makeBranch('AA-0002');
    Sanctum::actingAs(directorOf($otherBranch));

    $this->withHeaders(branchContext($otherBranch))
        ->getJson("/api/v1/hr/evaluations/{$id}")
        ->assertForbidden();

    // And the outsider cannot appraise another school's employee.
    $this->withHeaders(branchContext($otherBranch))
        ->postJson('/api/v1/hr/evaluations', ['employee_id' => $teacher->id, 'term_id' => $termId])
        ->assertForbidden();

    expect(TeacherEvaluation::query()->count())->toBe(1);
});

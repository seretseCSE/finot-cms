<?php

use App\Actions\EnrollStudentAction;
use App\Actions\SaveAcademicYearAction;
use App\Enums\MarklistStatus;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AssessmentResult;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\GradeLevel;
use App\Models\Marklist;
use App\Models\Notification;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectAssignment;
use App\Models\User;
use Database\Seeders\GradeLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SubjectSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(GradeLevelSeeder::class);
    $this->seed(SubjectSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function monitorWorld(): array
{
    $branch = makeBranch();
    $year = (new SaveAcademicYearAction())->execute($branch, ['name' => '2017 E.C.', 'status' => 'active']);
    $section = $branch->sections()->create([
        'school_id' => $branch->school_id,
        'grade_level_id' => GradeLevel::where('code', 'G1')->value('id'),
        'name' => 'A',
    ]);

    foreach (['Abel', 'Sara'] as $name) {
        $student = $branch->students()->create([
            'school_id' => $branch->school_id,
            'first_name' => $name,
            'father_name' => 'Tesfaye',
            'gender' => $name === 'Sara' ? 'female' : 'male',
        ]);
        app(EnrollStudentAction::class)->execute($student, [
            'academic_year_id' => $year->id,
            'section_id' => $section->id,
            'grade_level_id' => $section->grade_level_id,
        ]);
    }

    return [$branch, $year, $section];
}

function monitorTeacher(Branch $branch, string $name = 'Alemu'): Employee
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

function monitorAssignment(Branch $branch, AcademicYear $year, Section $section, Employee $teacher, string $subjectCode): SubjectAssignment
{
    return SubjectAssignment::create([
        'school_id' => $branch->school_id,
        'branch_id' => $branch->id,
        'academic_year_id' => $year->id,
        'section_id' => $section->id,
        'subject_id' => Subject::where('code', $subjectCode)->value('id'),
        'term_id' => $year->terms()->first()->id,
        'employee_id' => $teacher->id,
        'periods_per_week' => 5,
    ]);
}

it('shows per-teacher marklist status with entry completeness, worst first', function () {
    [$branch, $year, $section] = monitorWorld();
    $term = $year->terms()->first();

    $behind = monitorTeacher($branch, 'Alemu');
    $ahead = monitorTeacher($branch, 'Chaltu');

    // Ahead: submitted marklist, 1 assessment column, 1 of 2 students marked = 50%.
    $submitted = monitorAssignment($branch, $year, $section, $ahead, 'MATH');
    Marklist::create([
        'subject_assignment_id' => $submitted->id,
        'school_id' => $branch->school_id,
        'branch_id' => $branch->id,
        'term_id' => $term->id,
        'status' => MarklistStatus::Submitted,
        'submitted_at' => now(),
        'submitted_by' => $ahead->user_id,
    ]);
    $assessment = Assessment::create([
        'subject_assignment_id' => $submitted->id,
        'type' => 'test',
        'name' => 'Test 1',
        'max_score' => 20,
        'weight' => 20,
    ]);
    AssessmentResult::create([
        'assessment_id' => $assessment->id,
        'student_id' => Student::where('first_name', 'Abel')->value('id'),
        'score' => 15,
    ]);

    // Behind: no marklist row at all.
    monitorAssignment($branch, $year, $section, $behind, 'ENG');

    Sanctum::actingAs(directorOf($branch));

    $response = $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/terms/{$term->id}/marklist-status")
        ->assertOk();

    expect($response->json('meta'))
        ->total->toBe(2)
        ->not_started->toBe(1)
        ->submitted->toBe(1)
        ->approved->toBe(0);

    $rows = $response->json('data');
    expect($rows[0]['status'])->toBe('not_started')
        ->and($rows[0]['teacher']['name'])->toContain('Alemu')
        ->and($rows[1]['status'])->toBe('submitted')
        ->and($rows[1]['entry']['percent'])->toBe(50)
        ->and($rows[1]['entry']['students'])->toBe(2);
});

it('reminds only the teachers who are behind, one folded note per teacher', function () {
    [$branch, $year, $section] = monitorWorld();
    $term = $year->terms()->first();
    $sectionB = $branch->sections()->create([
        'school_id' => $branch->school_id,
        'grade_level_id' => $section->grade_level_id,
        'name' => 'B',
    ]);

    $behind = monitorTeacher($branch, 'Alemu');
    $done = monitorTeacher($branch, 'Chaltu');

    // Two pending classes for the same teacher → ONE reminder listing both.
    monitorAssignment($branch, $year, $section, $behind, 'MATH');
    monitorAssignment($branch, $year, $sectionB, $behind, 'ENG');

    $approved = monitorAssignment($branch, $year, $section, $done, 'AMH');
    Marklist::create([
        'subject_assignment_id' => $approved->id,
        'school_id' => $branch->school_id,
        'branch_id' => $branch->id,
        'term_id' => $term->id,
        'status' => MarklistStatus::Approved,
        'approved_at' => now(),
    ]);

    Sanctum::actingAs(directorOf($branch));

    // Explicit targets only — a body-less call must refuse, never blast all.
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/terms/{$term->id}/marklist-reminders")
        ->assertStatus(422);

    $allIds = SubjectAssignment::query()->where('term_id', $term->id)->pluck('id')->all();

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/terms/{$term->id}/marklist-reminders", ['subject_assignment_ids' => $allIds])
        ->assertOk()
        ->assertJsonPath('data.teachers', 1)
        ->assertJsonPath('data.assignments', 2);

    expect(Notification::where('user_id', $behind->user_id)->count())->toBe(1)
        ->and(Notification::where('user_id', $done->user_id)->count())->toBe(0);

    $note = Notification::where('user_id', $behind->user_id)->first();
    expect($note->event)->toBe('academics.marklist_reminder')
        ->and($note->data['pending'])->toBe(2);

    // Nagging again folds into the same unread row instead of stacking —
    // and the fold counter must never clobber the pending-marklists figure.
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/terms/{$term->id}/marklist-reminders", [
            'subject_assignment_ids' => SubjectAssignment::query()
                ->where('term_id', $term->id)->pluck('id')->all(),
        ])
        ->assertOk();

    $folded = Notification::where('user_id', $behind->user_id)->get();
    expect($folded)->toHaveCount(1)
        ->and($folded->first()->data['pending'])->toBe(2)
        ->and($folded->first()->data['count'])->toBe(2);
});

it('stacks reminders about DIFFERENT classes while folding repeats of the same one', function () {
    [$branch, $year, $section] = monitorWorld();
    $term = $year->terms()->first();

    $teacher = monitorTeacher($branch, 'Alemu');
    $math = monitorAssignment($branch, $year, $section, $teacher, 'MATH');
    $eng = monitorAssignment($branch, $year, $section, $teacher, 'ENG');

    Sanctum::actingAs(directorOf($branch));

    $remind = fn (array $ids) => $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/terms/{$term->id}/marklist-reminders", ['subject_assignment_ids' => $ids])
        ->assertOk();

    // Math… then English, nothing read in between → TWO unread notes.
    $remind([$math->id]);
    $remind([$eng->id]);

    $rows = Notification::where('user_id', $teacher->user_id)->orderBy('id')->get();
    expect($rows)->toHaveCount(2)
        ->and($rows[0]->data['classes'])->toContain('Mathematics')
        ->and($rows[1]->data['classes'])->toContain('English');

    // Spam-clicking the SAME class still folds instead of stacking.
    $remind([$math->id]);
    $remind([$math->id]);

    $after = Notification::where('user_id', $teacher->user_id)->get();
    expect($after)->toHaveCount(2)
        ->and($after->firstWhere(fn ($n) => str_contains($n->data['classes'], 'Mathematics'))->data['count'])->toBe(3);
});

it('delivers a fresh reminder after the teacher read the previous one', function () {
    [$branch, $year, $section] = monitorWorld();
    $term = $year->terms()->first();

    $teacher = monitorTeacher($branch, 'Alemu');
    $math = monitorAssignment($branch, $year, $section, $teacher, 'MATH');
    $eng = monitorAssignment($branch, $year, $section, $teacher, 'ENG');

    Sanctum::actingAs(directorOf($branch));

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/terms/{$term->id}/marklist-reminders", ['subject_assignment_ids' => [$math->id]])
        ->assertOk();

    // The teacher reads it — the dedupe slot must not stay locked forever.
    Notification::where('user_id', $teacher->user_id)->update(['read_at' => now()]);

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/terms/{$term->id}/marklist-reminders", ['subject_assignment_ids' => [$eng->id]])
        ->assertOk();

    $rows = Notification::where('user_id', $teacher->user_id)->orderBy('id')->get();
    expect($rows)->toHaveCount(2)
        ->and($rows[0]->read_at)->not->toBeNull()      // history kept
        ->and($rows[1]->read_at)->toBeNull()           // NEW unread reminder
        ->and($rows[1]->data['classes'])->toContain('English')
        ->and($rows[1]->data['count'])->toBe(1);
});

it('keeps the monitor supervisory — a teacher gets 403', function () {
    [$branch, $year] = monitorWorld();
    $term = $year->terms()->first();

    Sanctum::actingAs(memberOf($branch));

    $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/terms/{$term->id}/marklist-status")
        ->assertForbidden();

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/terms/{$term->id}/marklist-reminders")
        ->assertForbidden();
});

<?php

use App\Actions\EnrollStudentAction;
use App\Actions\SaveAcademicYearAction;
use App\Enums\LessonPlanStatus;
use App\Enums\TermStatus;
use App\Models\AcademicYear;
use App\Models\AnnualLessonPlan;
use App\Models\Branch;
use App\Models\DailyLessonPlan;
use App\Models\Employee;
use App\Models\GradeLevel;
use App\Models\ParentProfile;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentGuardian;
use App\Models\Subject;
use App\Models\SubjectAssignment;
use App\Models\User;
use App\Models\WeeklyLessonPlan;
use Database\Seeders\GradeLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SubjectSeeder;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

/*
 * THE LESSON PLANNING GUARD RAIL. Asserts the three-tier design end to end:
 * the teacher-authored annual plan (the MoE grid — units with timelines,
 * rationale, prerequisites, aids, assessment, pages; teaching-load guard),
 * the DAILY lesson plan lane (MoE daily format: stages, differentiation,
 * per-sitting deliveries with coverage, the auto-resolved weekly container,
 * My Day, duplicate/bump), the submit → approve/decline workflow where
 * director AND principal each hold review authority independently (plus the
 * opt-in department-head lane), the pacing gate (no next week while last
 * week has uncovered sittings — unless justified), the /me relationship lane
 * (approved-only, guardian-linked, section-scoped) and tenant isolation.
 */

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(GradeLevelSeeder::class);
    $this->seed(SubjectSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

// ───────────────────────────── fixtures ─────────────────────────────

function lpYear(Branch $branch): AcademicYear
{
    return (new SaveAcademicYearAction())->execute($branch, ['name' => '2017 E.C.', 'status' => 'active']);
}

function lpSection(Branch $branch, string $name = 'A', string $gradeCode = 'G7'): Section
{
    return $branch->sections()->create([
        'school_id' => $branch->school_id,
        'grade_level_id' => GradeLevel::where('code', $gradeCode)->value('id'),
        'name' => $name,
    ]);
}

/** @return array{0: User, 1: Employee} */
function lpTeacher(Branch $branch): array
{
    $user = memberOf($branch);
    $employee = Employee::create([
        'user_id' => $user->id,
        'school_id' => $branch->school_id,
        'branch_id' => $branch->id,
        'first_name' => 'Alemu',
        'father_name' => 'Bekele',
        'gender' => 'male',
    ]);

    return [$user, $employee];
}

function lpClass(Branch $branch, AcademicYear $year, Section $section, Employee $teacher, string $subjectCode = 'MATH'): SubjectAssignment
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

/** A draft annual plan created through the API by its owning teacher. */
function lpPlan(Branch $branch, User $teacher, SubjectAssignment $class): AnnualLessonPlan
{
    Sanctum::actingAs($teacher);

    $response = test()->withHeaders(branchContext($branch))->postJson('/api/v1/lesson-plans', [
        'academic_year_id' => $class->academic_year_id,
        'subject_id' => $class->subject_id,
        'grade_level_id' => Section::find($class->section_id)->grade_level_id,
        'goals' => '<p>Master the national Grade 7 mathematics syllabus.</p>',
        'periods_per_week' => 5,
        'total_periods' => 160,
    ])->assertCreated();

    return AnnualLessonPlan::findOrFail($response->json('data.id'));
}

/** Add a dated unit (full MoE grid fields) through the API. */
function lpUnit(Branch $branch, User $teacher, AnnualLessonPlan $plan, array $overrides = []): int
{
    Sanctum::actingAs($teacher);

    return test()->withHeaders(branchContext($branch))
        ->postJson("/api/v1/lesson-plans/{$plan->id}/units", array_merge([
            'title' => 'Unit 1 — Integers',
            'objectives' => 'Understand integer operations.',
            'rationale' => 'The base for algebra in the second semester.',
            'prerequisite_knowledge' => 'Whole-number arithmetic.',
            'teaching_aids' => 'Number line chart, counters.',
            'assessment_techniques' => 'Oral questions, exercises, unit test.',
            'page_from' => 1,
            'page_to' => 24,
            'starts_on' => now()->subWeeks(2)->toDateString(),
            'ends_on' => now()->addWeeks(2)->toDateString(),
            'planned_periods' => 10,
        ], $overrides))->assertCreated()->json('data.id');
}

/** Submit + approve an annual plan (approver defaults to the branch director). */
function lpApprovedPlan(Branch $branch, User $teacher, AnnualLessonPlan $plan, ?User $approver = null): AnnualLessonPlan
{
    Sanctum::actingAs($teacher);
    test()->withHeaders(branchContext($branch))->postJson("/api/v1/lesson-plans/{$plan->id}/submit")->assertOk();

    Sanctum::actingAs($approver ?? directorOf($branch));
    test()->withHeaders(branchContext($branch))->postJson("/api/v1/lesson-plans/{$plan->id}/approve")->assertOk();

    return $plan->fresh();
}

/**
 * Create a DAILY plan through the API — the weekly container is auto-
 * resolved from the date.
 */
function lpDay(Branch $branch, User $teacher, AnnualLessonPlan $plan, string $date, Section $section, array $overrides = []): DailyLessonPlan
{
    Sanctum::actingAs($teacher);

    $response = test()->withHeaders(branchContext($branch))
        ->postJson("/api/v1/lesson-plans/{$plan->id}/days", array_merge([
            'teaches_on' => $date,
            'topic' => 'Adding integers',
            'objectives' => 'Students will be able to add integers with unlike signs.',
            'deliveries' => [['section_id' => $section->id]],
        ], $overrides))->assertCreated();

    return DailyLessonPlan::findOrFail($response->json('data.id'));
}

/** An actively enrolled student with a portal account. */
function lpStudent(Branch $branch, AcademicYear $year, Section $section): Student
{
    $user = User::factory()->create();

    $student = Student::create([
        'user_id' => $user->id,
        'first_name' => 'Sara',
        'father_name' => 'Tesfaye',
        'gender' => 'female',
    ]);

    app(EnrollStudentAction::class)->execute($student, [
        'academic_year_id' => $year->id,
        'section_id' => $section->id,
        'grade_level_id' => $section->grade_level_id,
    ]);

    return $student;
}

function lpParentOf(Student $student): User
{
    $user = User::factory()->create();

    $parent = ParentProfile::create([
        'user_id' => $user->id,
        'first_name' => 'Worknesh',
        'father_name' => 'Abebe',
        'gender' => 'female',
    ]);

    StudentGuardian::create([
        'student_id' => $student->id,
        'parent_id' => $parent->id,
        'relationship' => 'mother',
        'can_view_grades' => true,
        'can_view_attendance' => true,
        'can_pay_fees' => true,
        'is_primary' => true,
        'is_active' => true,
    ]);

    return $user;
}

// ───────────────────────── annual plan workflow ─────────────────────────

it('lets a teacher create an annual plan only for a class they actually teach', function () {
    $branch = makeBranch();
    [$teacherUser, $employee] = lpTeacher($branch);
    $year = lpYear($branch);
    $section = lpSection($branch);
    lpClass($branch, $year, $section, $employee);

    // A subject they do NOT teach is refused.
    Sanctum::actingAs($teacherUser);
    $this->withHeaders(branchContext($branch))->postJson('/api/v1/lesson-plans', [
        'academic_year_id' => $year->id,
        'subject_id' => Subject::where('code', '!=', 'MATH')->whereNull('school_id')->value('id'),
        'grade_level_id' => $section->grade_level_id,
    ])->assertStatus(422);

    $plan = lpPlan($branch, $teacherUser, SubjectAssignment::first());
    expect($plan->status)->toBe(LessonPlanStatus::Draft)
        ->and($plan->employee_id)->toBe($employee->id)
        ->and($plan->periods_per_week)->toBe(5)
        ->and($plan->total_periods)->toBe(160);

    // Duplicate plans for the same subject × grade are refused.
    $this->withHeaders(branchContext($branch))->postJson('/api/v1/lesson-plans', [
        'academic_year_id' => $year->id,
        'subject_id' => $plan->subject_id,
        'grade_level_id' => $plan->grade_level_id,
    ])->assertStatus(422);
});

it('walks the annual plan through submit, decline, resubmit and approve', function () {
    $branch = makeBranch();
    [$teacherUser, $employee] = lpTeacher($branch);
    $year = lpYear($branch);
    $section = lpSection($branch);
    $class = lpClass($branch, $year, $section, $employee);
    $plan = lpPlan($branch, $teacherUser, $class);

    // No units yet → submission refused.
    Sanctum::actingAs($teacherUser);
    $this->withHeaders(branchContext($branch))->postJson("/api/v1/lesson-plans/{$plan->id}/submit")->assertStatus(422);

    $unitId = lpUnit($branch, $teacherUser, $plan);

    // The unit carries the full MoE grid row.
    $shown = $this->withHeaders(branchContext($branch))->getJson("/api/v1/lesson-plans/{$plan->id}")->assertOk()->json('data');
    $unit = collect($shown['units'])->firstWhere('id', $unitId);
    expect($unit['rationale'])->toContain('algebra')
        ->and($unit['prerequisite_knowledge'])->toContain('arithmetic')
        ->and($unit['teaching_aids'])->toContain('Number line')
        ->and($unit['assessment_techniques'])->toContain('unit test')
        ->and($unit['page_from'])->toBe(1)
        ->and($unit['page_to'])->toBe(24);

    $this->withHeaders(branchContext($branch))->postJson("/api/v1/lesson-plans/{$plan->id}/submit")->assertOk();

    // The owning teacher cannot decide their own plan.
    $this->withHeaders(branchContext($branch))->postJson("/api/v1/lesson-plans/{$plan->id}/approve")->assertForbidden();

    // Director declines with a reason — required.
    $director = directorOf($branch);
    Sanctum::actingAs($director);
    $this->withHeaders(branchContext($branch))->postJson("/api/v1/lesson-plans/{$plan->id}/decline")->assertStatus(422);
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/lesson-plans/{$plan->id}/decline", ['reason' => 'Chapter timeline ignores the exam weeks.'])
        ->assertOk();

    expect($plan->fresh()->status)->toBe(LessonPlanStatus::Declined);

    // Declined = editable again; the teacher fixes and resubmits, the
    // PRINCIPAL (school scope) approves — independent authority.
    Sanctum::actingAs($teacherUser);
    $this->withHeaders(branchContext($branch))
        ->putJson("/api/v1/lesson-plans/{$plan->id}", ['goals' => '<p>Adjusted for exam weeks.</p>'])
        ->assertOk();
    $this->withHeaders(branchContext($branch))->postJson("/api/v1/lesson-plans/{$plan->id}/submit")->assertOk();

    Sanctum::actingAs(schoolPrincipal($branch));
    $this->withHeaders(schoolContext($branch))->postJson("/api/v1/lesson-plans/{$plan->id}/approve")->assertOk();

    $plan->refresh();
    expect($plan->status)->toBe(LessonPlanStatus::Approved)
        ->and($plan->decline_reason)->toBeNull();

    // Approved = frozen: unit edits and goal edits are refused until reopened.
    Sanctum::actingAs($teacherUser);
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/lesson-plans/{$plan->id}/units", ['title' => 'Late unit'])
        ->assertStatus(422);
});

it('keeps lesson plans inside their tenant and ownership lanes', function () {
    $branchA = makeBranch();
    $branchB = makeBranch('AA-0002');
    [$teacherUser, $employee] = lpTeacher($branchA);
    $year = lpYear($branchA);
    $section = lpSection($branchA);
    $class = lpClass($branchA, $year, $section, $employee);
    $plan = lpPlan($branchA, $teacherUser, $class);

    // A colleague teacher in the SAME branch sees nothing of it.
    Sanctum::actingAs(memberOf($branchA));
    $this->withHeaders(branchContext($branchA))->getJson("/api/v1/lesson-plans/{$plan->id}")->assertForbidden();

    // School B's director: no visibility, no authority.
    Sanctum::actingAs(directorOf($branchB));
    $this->withHeaders(branchContext($branchB))->getJson("/api/v1/lesson-plans/{$plan->id}")->assertForbidden();
    $this->withHeaders(branchContext($branchB))->postJson("/api/v1/lesson-plans/{$plan->id}/approve")->assertForbidden();

    // The register: the teacher sees only their own plans; the branch
    // director sees the branch's plans.
    Sanctum::actingAs($teacherUser);
    $own = $this->withHeaders(branchContext($branchA))->getJson('/api/v1/lesson-plans')->assertOk()->json('data');
    expect(collect($own)->pluck('id'))->toContain($plan->id);

    Sanctum::actingAs(directorOf($branchB));
    $foreign = $this->withHeaders(branchContext($branchB))->getJson('/api/v1/lesson-plans')->assertOk()->json('data');
    expect(collect($foreign)->pluck('id'))->not->toContain($plan->id);
});

// ───────────────────────── the daily lane ─────────────────────────

it('creates a daily plan in the MoE format with stages and deliveries, auto-resolving the week', function () {
    $branch = makeBranch();
    [$teacherUser, $employee] = lpTeacher($branch);
    $year = lpYear($branch);
    $section = lpSection($branch);
    $sectionB = lpSection($branch, 'B');
    $class = lpClass($branch, $year, $section, $employee);
    $plan = lpPlan($branch, $teacherUser, $class);
    $unitId = lpUnit($branch, $teacherUser, $plan);

    $monday = now()->startOfWeek()->toDateString();

    // A section from another grade is refused.
    $foreignSection = lpSection($branch, 'A', 'G8');
    Sanctum::actingAs($teacherUser);
    $this->withHeaders(branchContext($branch))->postJson("/api/v1/lesson-plans/{$plan->id}/days", [
        'teaches_on' => $monday,
        'topic' => 'Adding integers',
        'deliveries' => [['section_id' => $foreignSection->id]],
    ])->assertStatus(422);

    $day = lpDay($branch, $teacherUser, $plan, $monday, $section, [
        'annual_plan_unit_id' => $unitId,
        'subtopic' => 'Unlike signs',
        'rationale' => 'Needed for every later operation.',
        'prerequisite_knowledge' => 'Counting and the number line.',
        'support_slow' => 'Extra worked example with counters.',
        'support_fast' => 'Challenge set from page 24.',
        'stages' => [
            ['stage' => 'intro', 'learning_contents' => 'Recap', 'teacher_activity' => 'Ask review questions', 'student_activity' => 'Answer', 'assessment_techniques' => 'Oral questions'],
            ['stage' => 'main', 'learning_contents' => 'Worked examples', 'teacher_activity' => 'Demonstrate', 'student_activity' => 'Practice in pairs', 'teaching_aids' => 'Number line chart', 'page' => '12'],
            ['stage' => 'conclusion', 'learning_contents' => 'Summary', 'teacher_activity' => 'Summarise', 'student_activity' => 'Exit ticket'],
        ],
        'deliveries' => [
            ['section_id' => $section->id, 'period_number' => 2],
            ['section_id' => $sectionB->id, 'period_number' => 5],
        ],
    ]);

    // The week container was born automatically as a draft, Monday-anchored.
    $week = WeeklyLessonPlan::findOrFail($day->weekly_lesson_plan_id);
    expect($week->week_starts_on->toDateString())->toBe($monday)
        ->and($week->status)->toBe(LessonPlanStatus::Draft);

    // Full payload: three ordered stages, two sittings.
    $shown = $this->withHeaders(branchContext($branch))->getJson("/api/v1/daily-plans/{$day->id}")->assertOk()->json('data');
    expect(collect($shown['stages'])->pluck('stage')->all())->toBe(['intro', 'main', 'conclusion'])
        ->and(collect($shown['deliveries'])->pluck('section.id'))->toContain($section->id, $sectionB->id)
        ->and($shown['unit_title'])->toContain('Integers')
        ->and($shown['editable'])->toBeTrue();

    // Autosave: field edit + emptying a stage removes its row, coverage kept.
    $this->withHeaders(branchContext($branch))->putJson("/api/v1/daily-plans/{$day->id}", [
        'homework' => 'Exercise 2.1 numbers 1-10.',
        'stages' => [
            ['stage' => 'conclusion', 'learning_contents' => null, 'teacher_activity' => null, 'student_activity' => null],
        ],
    ])->assertOk();

    expect($day->fresh()->homework)->toContain('2.1')
        ->and($day->stages()->count())->toBe(2);

    // A second daily plan on the same date lands in the SAME week container.
    $day2 = lpDay($branch, $teacherUser, $plan, now()->startOfWeek()->addDays(2)->toDateString(), $section);
    expect($day2->weekly_lesson_plan_id)->toBe($week->id);
});

it('serves the teacher day view and supports duplicate as the bump', function () {
    $branch = makeBranch();
    [$teacherUser, $employee] = lpTeacher($branch);
    $year = lpYear($branch);
    $section = lpSection($branch);
    $class = lpClass($branch, $year, $section, $employee);
    $plan = lpPlan($branch, $teacherUser, $class);
    lpUnit($branch, $teacherUser, $plan);

    $monday = now()->startOfWeek()->toDateString();
    $day = lpDay($branch, $teacherUser, $plan, $monday, $section);

    // My Day (no published timetable → class-list fallback) still surfaces
    // the planned lesson and the unit suggestion for the date.
    Sanctum::actingAs($teacherUser);
    $myDay = $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/lesson-plans/my-day?date='.$monday)
        ->assertOk()->json('data');

    expect($myDay['has_timetable'])->toBeFalse()
        ->and($myDay['items'])->toHaveCount(1)
        ->and($myDay['items'][0]['daily']['topic'])->toBe('Adding integers')
        ->and($myDay['items'][0]['plan']['id'])->toBe($plan->id)
        ->and($myDay['items'][0]['suggested_unit']['title'])->toContain('Integers');

    // Another teacher's my-day is empty — never someone else's classes.
    Sanctum::actingAs(memberOf($branch));
    $other = $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/lesson-plans/my-day?date='.$monday)
        ->assertOk()->json('data');
    expect($other['items'])->toBeEmpty();

    // Duplicate to Wednesday (the bump): content + fresh coverage; the
    // source's pending sittings close as missed.
    Sanctum::actingAs($teacherUser);
    $wednesday = now()->startOfWeek()->addDays(2)->toDateString();
    $copyId = $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/daily-plans/{$day->id}/duplicate", [
            'teaches_on' => $wednesday,
            'mark_source_missed' => true,
        ])->assertCreated()->json('data.id');

    $copy = DailyLessonPlan::findOrFail($copyId);
    expect($copy->topic)->toBe($day->topic)
        ->and($copy->teaches_on->toDateString())->toBe($wednesday)
        ->and($copy->deliveries()->first()->coverage->value)->toBe('pending')
        ->and($day->fresh()->deliveries()->first()->coverage->value)->toBe('missed');
});

// ───────────────────────── weekly lane + pacing gate ─────────────────────────

it('runs the weekly workflow with prefill and review over daily plans', function () {
    $branch = makeBranch();
    [$teacherUser, $employee] = lpTeacher($branch);
    $year = lpYear($branch);
    $section = lpSection($branch);
    $class = lpClass($branch, $year, $section, $employee);
    $plan = lpPlan($branch, $teacherUser, $class);
    $unitId = lpUnit($branch, $teacherUser, $plan);

    $weekStart = now()->startOfWeek()->toDateString();
    $day = lpDay($branch, $teacherUser, $plan, $weekStart, $section, ['annual_plan_unit_id' => $unitId]);
    $week = WeeklyLessonPlan::findOrFail($day->weekly_lesson_plan_id);

    // Weekly submission demands an APPROVED annual plan.
    Sanctum::actingAs($teacherUser);
    $this->withHeaders(branchContext($branch))->postJson("/api/v1/weekly-plans/{$week->id}/submit")->assertStatus(422);

    lpApprovedPlan($branch, $teacherUser, $plan);

    // Prefill offers the unit scheduled over this week.
    Sanctum::actingAs($teacherUser);
    $prefill = $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/lesson-plans/{$plan->id}/weeks/prefill?week_starts_on={$weekStart}")
        ->assertOk()->json('data');
    expect(collect($prefill['units'])->pluck('id'))->toContain($unitId)
        ->and($prefill['existing_id'])->toBe($week->id)
        ->and($prefill['needs_justification'])->toBeFalse();

    $this->withHeaders(branchContext($branch))->postJson("/api/v1/weekly-plans/{$week->id}/submit")->assertOk();

    // Submitted week = frozen content: adding a day bounces.
    $this->withHeaders(branchContext($branch))->postJson("/api/v1/lesson-plans/{$plan->id}/days", [
        'teaches_on' => $weekStart,
        'topic' => 'Late addition',
        'deliveries' => [['section_id' => $section->id]],
    ])->assertStatus(422);

    // Director declines (reason required), teacher revises and resubmits,
    // director approves.
    Sanctum::actingAs(directorOf($branch));
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/weekly-plans/{$week->id}/decline", ['reason' => 'Friday is a holiday — replan it.'])
        ->assertOk();

    Sanctum::actingAs($teacherUser);
    $this->withHeaders(branchContext($branch))->putJson("/api/v1/daily-plans/{$day->id}", [
        'topic' => 'Adding integers (revised)',
    ])->assertOk();
    $this->withHeaders(branchContext($branch))->postJson("/api/v1/weekly-plans/{$week->id}/submit")->assertOk();

    Sanctum::actingAs(directorOf($branch));
    $this->withHeaders(branchContext($branch))->postJson("/api/v1/weekly-plans/{$week->id}/approve")->assertOk();

    expect($week->fresh()->status)->toBe(LessonPlanStatus::Approved);
});

it('blocks the next week while the last one has uncovered sittings, unless justified', function () {
    $branch = makeBranch();
    [$teacherUser, $employee] = lpTeacher($branch);
    $year = lpYear($branch);
    $section = lpSection($branch);
    $class = lpClass($branch, $year, $section, $employee);
    $plan = lpPlan($branch, $teacherUser, $class);
    $unitId = lpUnit($branch, $teacherUser, $plan);
    lpApprovedPlan($branch, $teacherUser, $plan);

    $lastWeek = now()->subWeek()->startOfWeek()->toDateString();
    $thisWeek = now()->startOfWeek()->toDateString();

    // Week 1: two lessons submitted + approved, sittings never marked.
    $day1 = lpDay($branch, $teacherUser, $plan, $lastWeek, $section, ['annual_plan_unit_id' => $unitId]);
    $day2 = lpDay($branch, $teacherUser, $plan, now()->subWeek()->startOfWeek()->addDays(2)->toDateString(), $section, [
        'topic' => 'Subtracting integers', 'annual_plan_unit_id' => $unitId,
    ]);
    $week1 = WeeklyLessonPlan::findOrFail($day1->weekly_lesson_plan_id);

    Sanctum::actingAs($teacherUser);
    $this->withHeaders(branchContext($branch))->postJson("/api/v1/weekly-plans/{$week1->id}/submit")->assertOk();
    Sanctum::actingAs(directorOf($branch));
    $this->withHeaders(branchContext($branch))->postJson("/api/v1/weekly-plans/{$week1->id}/approve")->assertOk();

    // Coverage before the week is filed is refused… (draft week 2 below)
    $day3 = lpDay($branch, $teacherUser, $plan, $thisWeek, $section, ['topic' => 'Multiplying integers']);
    $week2 = WeeklyLessonPlan::findOrFail($day3->weekly_lesson_plan_id);
    Sanctum::actingAs($teacherUser);
    $this->withHeaders(branchContext($branch))->postJson("/api/v1/daily-plans/{$day3->id}/coverage", [
        'items' => [['delivery_id' => $day3->deliveries()->first()->id, 'coverage' => 'covered']],
    ])->assertStatus(422);

    // Week 2 submission without a justification → refused, field error.
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/weekly-plans/{$week2->id}/submit")
        ->assertStatus(422)
        ->assertJsonValidationErrors('lag_justification');

    // Prefill flags the gate + the carryover lessons.
    $prefill = $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/lesson-plans/{$plan->id}/weeks/prefill?week_starts_on={$thisWeek}")
        ->assertOk()->json('data');
    expect($prefill['needs_justification'])->toBeTrue()
        ->and(count($prefill['carryover']['lessons']))->toBe(2);

    // With a justification the submission goes through, FLAGGED for review.
    $this->withHeaders(branchContext($branch))->postJson("/api/v1/weekly-plans/{$week2->id}/submit", [
        'lag_justification' => 'Two school days lost to a regional holiday.',
    ])->assertOk();
    expect($week2->fresh()->lag_justification)->not->toBeNull();

    // Marking week 1's sittings covered lifts the gate for the NEXT week.
    foreach ([$day1, $day2] as $day) {
        $this->withHeaders(branchContext($branch))->postJson("/api/v1/daily-plans/{$day->id}/coverage", [
            'items' => $day->deliveries()->get()->map(fn ($d) => ['delivery_id' => $d->id, 'coverage' => 'covered'])->all(),
        ])->assertOk();
    }

    Sanctum::actingAs(directorOf($branch));
    $this->withHeaders(branchContext($branch))->postJson("/api/v1/weekly-plans/{$week2->id}/approve")->assertOk();

    Sanctum::actingAs($teacherUser);
    $this->withHeaders(branchContext($branch))->postJson("/api/v1/daily-plans/{$day3->id}/coverage", [
        'items' => [['delivery_id' => $day3->deliveries()->first()->id, 'coverage' => 'covered']],
    ])->assertOk();

    $nextWeek = now()->addWeek()->startOfWeek()->toDateString();
    $day4 = lpDay($branch, $teacherUser, $plan, $nextWeek, $section, ['topic' => 'Dividing integers']);
    $week3 = WeeklyLessonPlan::findOrFail($day4->weekly_lesson_plan_id);
    $this->withHeaders(branchContext($branch))->postJson("/api/v1/weekly-plans/{$week3->id}/submit")->assertOk();
});

it('feeds the review inbox and the pacing dashboard', function () {
    $branch = makeBranch();
    [$teacherUser, $employee] = lpTeacher($branch);
    $year = lpYear($branch);
    $section = lpSection($branch);
    $class = lpClass($branch, $year, $section, $employee);
    $plan = lpPlan($branch, $teacherUser, $class);
    $unitId = lpUnit($branch, $teacherUser, $plan);

    Sanctum::actingAs($teacherUser);
    $this->withHeaders(branchContext($branch))->postJson("/api/v1/lesson-plans/{$plan->id}/submit")->assertOk();

    // Teachers hold no review authority — the inbox is reviewer-only.
    $this->withHeaders(branchContext($branch))->getJson('/api/v1/lesson-plans/review')->assertForbidden();

    $director = directorOf($branch);
    Sanctum::actingAs($director);
    $inbox = $this->withHeaders(branchContext($branch))->getJson('/api/v1/lesson-plans/review')->assertOk()->json('data');
    expect(collect($inbox['annual'])->pluck('id'))->toContain($plan->id);

    $this->withHeaders(branchContext($branch))->postJson("/api/v1/lesson-plans/{$plan->id}/approve")->assertOk();

    $day = lpDay($branch, $teacherUser, $plan, now()->startOfWeek()->toDateString(), $section, ['annual_plan_unit_id' => $unitId]);
    $week = WeeklyLessonPlan::findOrFail($day->weekly_lesson_plan_id);
    Sanctum::actingAs($teacherUser);
    $this->withHeaders(branchContext($branch))->postJson("/api/v1/weekly-plans/{$week->id}/submit")->assertOk();

    Sanctum::actingAs($director);
    $inbox = $this->withHeaders(branchContext($branch))->getJson('/api/v1/lesson-plans/review')->assertOk()->json('data');
    $weeklyRow = collect($inbox['weekly'])->firstWhere('id', $week->id);
    expect($weeklyRow)->not->toBeNull()
        ->and($weeklyRow['lessons_count'])->toBe(1);

    $pacing = $this->withHeaders(branchContext($branch))->getJson('/api/v1/lesson-plans/pacing')->assertOk()->json('data');
    $row = collect($pacing)->firstWhere('id', $plan->id);
    expect($row)->not->toBeNull()
        ->and($row['pacing']['planned_periods'])->toBe(10)
        ->and($row['weeks_total'])->toBe(1);
});

it('lets a department head review only when the school opts in — and never their own plans', function () {
    $branch = makeBranch();
    [$teacherUser, $employee] = lpTeacher($branch);
    $year = lpYear($branch);
    $section = lpSection($branch);
    $class = lpClass($branch, $year, $section, $employee);
    $plan = lpPlan($branch, $teacherUser, $class);
    lpUnit($branch, $teacherUser, $plan);

    // A fellow teacher holding an active department_head position.
    [$deptUser, $deptEmployee] = lpTeacher($branch);
    $deptEmployee->positions()->create(['job_title' => 'department_head', 'is_primary' => true]);

    Sanctum::actingAs($teacherUser);
    $this->withHeaders(branchContext($branch))->postJson("/api/v1/lesson-plans/{$plan->id}/submit")->assertOk();

    // Setting OFF (default): no authority.
    Sanctum::actingAs($deptUser);
    $this->withHeaders(branchContext($branch))->postJson("/api/v1/lesson-plans/{$plan->id}/approve")->assertForbidden();
    $this->withHeaders(branchContext($branch))->getJson('/api/v1/lesson-plans/review')->assertForbidden();

    // Flip the school setting on.
    $branch->school->update(['settings' => ['lesson_plan_department_review' => true]]);
    Cache::flush();

    $inbox = $this->withHeaders(branchContext($branch))->getJson('/api/v1/lesson-plans/review')->assertOk()->json('data');
    expect(collect($inbox['annual'])->pluck('id'))->toContain($plan->id);
    $this->withHeaders(branchContext($branch))->postJson("/api/v1/lesson-plans/{$plan->id}/approve")->assertOk();

    // Their OWN plan still needs the director — no self-review lane.
    $deptClass = lpClass($branch, $year, lpSection($branch, 'C'), $deptEmployee, 'ENG');
    $deptPlan = lpPlan($branch, $deptUser, $deptClass);
    lpUnit($branch, $deptUser, $deptPlan);
    Sanctum::actingAs($deptUser);
    $this->withHeaders(branchContext($branch))->postJson("/api/v1/lesson-plans/{$deptPlan->id}/submit")->assertOk();
    $this->withHeaders(branchContext($branch))->postJson("/api/v1/lesson-plans/{$deptPlan->id}/approve")->assertForbidden();
});

it('locks weekly and daily mutations once the term closes', function () {
    $branch = makeBranch();
    [$teacherUser, $employee] = lpTeacher($branch);
    $year = lpYear($branch);
    $section = lpSection($branch);
    $class = lpClass($branch, $year, $section, $employee);
    $plan = lpPlan($branch, $teacherUser, $class);
    lpUnit($branch, $teacherUser, $plan);
    lpApprovedPlan($branch, $teacherUser, $plan);

    $day = lpDay($branch, $teacherUser, $plan, now()->startOfWeek()->toDateString(), $section);
    $week = WeeklyLessonPlan::findOrFail($day->weekly_lesson_plan_id);

    // Point the week at a CLOSED term — every mutation must bounce.
    $term = $year->terms()->first();
    $term->update(['status' => TermStatus::Closed]);
    $week->update(['term_id' => $term->id]);

    Sanctum::actingAs($teacherUser);
    $this->withHeaders(branchContext($branch))
        ->putJson("/api/v1/weekly-plans/{$week->id}", ['notes' => 'too late'])
        ->assertStatus(422);
    $this->withHeaders(branchContext($branch))->postJson("/api/v1/weekly-plans/{$week->id}/submit")->assertStatus(422);
    $this->withHeaders(branchContext($branch))
        ->putJson("/api/v1/daily-plans/{$day->id}", ['topic' => 'too late'])
        ->assertStatus(422);
});

// ───────────────────────── the family lane ─────────────────────────

it('shows families only the approved roadmap and this week of approved topics', function () {
    $branch = makeBranch();
    [$teacherUser, $employee] = lpTeacher($branch);
    $year = lpYear($branch);
    $section = lpSection($branch);
    $sectionB = lpSection($branch, 'B');
    $class = lpClass($branch, $year, $section, $employee);
    $student = lpStudent($branch, $year, $section);
    $plan = lpPlan($branch, $teacherUser, $class);
    $unitId = lpUnit($branch, $teacherUser, $plan);

    // Draft plan → invisible to the student.
    Sanctum::actingAs($student->user);
    $data = $this->getJson('/api/v1/me/student/lesson-plans')->assertOk()->json('data');
    $math = collect($data['subjects'])->firstWhere('subject.code', 'MATH');
    expect($math['has_plan'])->toBeFalse();

    lpApprovedPlan($branch, $teacherUser, $plan);

    // Approved annual plan, draft week → roadmap visible, week not yet.
    $day = lpDay($branch, $teacherUser, $plan, now()->startOfWeek()->toDateString(), $section, [
        'annual_plan_unit_id' => $unitId,
        'deliveries' => [
            ['section_id' => $section->id, 'period_number' => 2],
            ['section_id' => $sectionB->id, 'period_number' => 4],
        ],
    ]);
    $week = WeeklyLessonPlan::findOrFail($day->weekly_lesson_plan_id);

    // A lesson that only touches the OTHER section: must stay invisible to
    // this student even once the week is approved.
    lpDay($branch, $teacherUser, $plan, now()->startOfWeek()->addDay()->toDateString(), $sectionB, [
        'topic' => 'Section B catch-up',
        'deliveries' => [['section_id' => $sectionB->id]],
    ]);

    Sanctum::actingAs($student->user);
    $data = $this->getJson('/api/v1/me/student/lesson-plans')->assertOk()->json('data');
    $math = collect($data['subjects'])->firstWhere('subject.code', 'MATH');
    expect($math['has_plan'])->toBeTrue()
        ->and($math['units_total'])->toBe(1)
        ->and($math['current_week'])->toBeNull();

    // Approve the week → this week's topics appear, section-scoped.
    Sanctum::actingAs($teacherUser);
    $this->withHeaders(branchContext($branch))->postJson("/api/v1/weekly-plans/{$week->id}/submit")->assertOk();
    Sanctum::actingAs(directorOf($branch));
    $this->withHeaders(branchContext($branch))->postJson("/api/v1/weekly-plans/{$week->id}/approve")->assertOk();

    Sanctum::actingAs($student->user);
    $data = $this->getJson('/api/v1/me/student/lesson-plans')->assertOk()->json('data');
    $math = collect($data['subjects'])->firstWhere('subject.code', 'MATH');
    expect($math['current_week']['lessons'])->toHaveCount(1)
        ->and($math['current_week']['lessons'][0]['topic'])->toBe('Adding integers');

    // A linked guardian sees the same; a stranger parent is refused.
    Sanctum::actingAs(lpParentOf($student));
    $this->getJson("/api/v1/me/children/{$student->id}/lesson-plans")->assertOk();

    $strangerParent = User::factory()->create();
    ParentProfile::create([
        'user_id' => $strangerParent->id,
        'first_name' => 'Chaltu', 'father_name' => 'Gemeda', 'gender' => 'female',
    ]);
    Sanctum::actingAs($strangerParent);
    $this->getJson("/api/v1/me/children/{$student->id}/lesson-plans")->assertForbidden();
});

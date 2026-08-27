<?php

use App\Actions\SaveAcademicYearAction;
use App\Models\Branch;
use App\Models\GradeLevel;
use App\Models\Section;
use App\Models\Subject;
use App\Models\SubjectAssignment;
use App\Models\Term;
use App\Models\TimetableSlot;
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

function makeSection(Branch $branch): Section
{
    $grade = GradeLevel::first();

    return $branch->sections()->create([
        'school_id' => $branch->school_id,
        'grade_level_id' => $grade->id,
        'name' => 'A',
    ]);
}

function makeTerm(Branch $branch): Term
{
    $year = (new SaveAcademicYearAction)->execute($branch, ['name' => '2017 E.C.', 'is_current' => true]);

    return $year->terms()->first();
}

it('lists subjects (all authenticated users)', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));

    $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/subjects')
        ->assertOk()
        ->assertJsonPath('meta.total', Subject::count());
});

it('lets a director assign a subject to a section', function () {
    $branch = makeBranch();
    $section = makeSection($branch);
    $term = makeTerm($branch);
    $subject = Subject::where('code', 'MATH')->first();

    Sanctum::actingAs(directorOf($branch));

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/sections/{$section->id}/subject-assignments", [
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'periods_per_week' => 5,
        ])
        ->assertCreated()
        ->assertJsonPath('data.subject.code', 'MATH');
});

it('places a lesson on a draft timetable version', function () {
    $branch = makeBranch();
    $section = makeSection($branch);
    $term = makeTerm($branch);
    $subject = Subject::where('code', 'ENG')->first();

    $director = directorOf($branch);
    Sanctum::actingAs($director);

    $assignment = SubjectAssignment::create([
        'school_id' => $section->school_id,
        'branch_id' => $section->branch_id,
        'academic_year_id' => $term->academic_year_id,
        'section_id' => $section->id,
        'subject_id' => $subject->id,
        'term_id' => $term->id,
        'periods_per_week' => 4,
    ]);

    $headers = branchContext($branch);

    $this->withHeaders($headers)
        ->postJson("/api/v1/terms/{$term->id}/periods/defaults")
        ->assertOk();

    $versionId = $this->withHeaders($headers)
        ->postJson("/api/v1/terms/{$term->id}/timetable-versions", ['name' => 'Draft 1'])
        ->assertCreated()
        ->json('data.id');

    $this->withHeaders($headers)
        ->postJson("/api/v1/timetable-versions/{$versionId}/slots", [
            'subject_assignment_id' => $assignment->id,
            'day_of_week' => 1,
            'period_number' => 1,
        ])
        ->assertCreated()
        ->assertJsonPath('data.period_number', 1);
});

it('reports first-time setup state on the versions index', function () {
    $branch = makeBranch();
    $term = makeTerm($branch);

    Sanctum::actingAs(directorOf($branch));
    $headers = branchContext($branch);

    // Fresh term: nothing set up yet — the frontend opens the guided wizard.
    $this->withHeaders($headers)
        ->getJson("/api/v1/terms/{$term->id}/timetable-versions")
        ->assertOk()
        ->assertJsonPath('meta.has_periods', false)
        ->assertJsonPath('meta.rooms_count', 0);

    $this->withHeaders($headers)->postJson("/api/v1/terms/{$term->id}/periods/defaults")->assertOk();
    $this->withHeaders($headers)
        ->postJson('/api/v1/rooms', ['name' => 'Chemistry lab', 'type' => 'lab'])
        ->assertCreated();

    $this->withHeaders($headers)
        ->getJson("/api/v1/terms/{$term->id}/timetable-versions")
        ->assertOk()
        ->assertJsonPath('meta.has_periods', true)
        ->assertJsonPath('meta.rooms_count', 1);
});

it('refuses to generate when no assignment has weekly periods', function () {
    $branch = makeBranch();
    $section = makeSection($branch);
    $term = makeTerm($branch);
    $subject = Subject::where('code', 'MATH')->first();

    Sanctum::actingAs(directorOf($branch));
    $headers = branchContext($branch);

    // A real grid row — but its load was never filled in (the auto-generated
    // default): the solver would "succeed" empty, so generate must refuse.
    $assignment = SubjectAssignment::create([
        'school_id' => $section->school_id,
        'branch_id' => $section->branch_id,
        'academic_year_id' => $term->academic_year_id,
        'section_id' => $section->id,
        'subject_id' => $subject->id,
        'term_id' => $term->id,
        'periods_per_week' => 0,
    ]);

    $this->withHeaders($headers)->postJson("/api/v1/terms/{$term->id}/periods/defaults")->assertOk();

    $versionId = $this->withHeaders($headers)
        ->postJson("/api/v1/terms/{$term->id}/timetable-versions", ['name' => 'Draft 1'])
        ->json('data.id');

    // The index meta mirrors it so the setup wizard can warn up front.
    $this->withHeaders($headers)
        ->getJson("/api/v1/terms/{$term->id}/timetable-versions")
        ->assertOk()
        ->assertJsonPath('meta.has_loads', false);

    $this->withHeaders($headers)
        ->postJson("/api/v1/timetable-versions/{$versionId}/generate")
        ->assertStatus(422);

    // Setting the load unblocks generation — the sync test queue runs the
    // solver inline, so the slots must exist right after the call.
    $assignment->update(['periods_per_week' => 5]);

    $this->withHeaders($headers)
        ->getJson("/api/v1/terms/{$term->id}/timetable-versions")
        ->assertOk()
        ->assertJsonPath('meta.has_loads', true);

    $this->withHeaders($headers)
        ->postJson("/api/v1/timetable-versions/{$versionId}/generate")
        ->assertOk();

    expect(TimetableSlot::where('timetable_version_id', $versionId)->count())->toBe(5);
});

it('rejects a section clash when placing a lesson', function () {
    $branch = makeBranch();
    $section = makeSection($branch);
    $term = makeTerm($branch);
    [$math, $eng] = [Subject::where('code', 'MATH')->first(), Subject::where('code', 'ENG')->first()];

    Sanctum::actingAs(directorOf($branch));

    $make = fn ($subject) => SubjectAssignment::create([
        'school_id' => $section->school_id,
        'branch_id' => $section->branch_id,
        'academic_year_id' => $term->academic_year_id,
        'section_id' => $section->id,
        'subject_id' => $subject->id,
        'term_id' => $term->id,
        'periods_per_week' => 3,
    ]);

    $a = $make($math);
    $b = $make($eng);

    $headers = branchContext($branch);
    $this->withHeaders($headers)->postJson("/api/v1/terms/{$term->id}/periods/defaults")->assertOk();

    $versionId = $this->withHeaders($headers)
        ->postJson("/api/v1/terms/{$term->id}/timetable-versions", ['name' => 'Draft 1'])
        ->json('data.id');

    $cell = ['day_of_week' => 2, 'period_number' => 3];

    $this->withHeaders($headers)
        ->postJson("/api/v1/timetable-versions/{$versionId}/slots", ['subject_assignment_id' => $a->id] + $cell)
        ->assertCreated();

    // Another subject of the SAME section in the same cell → section clash.
    $this->withHeaders($headers)
        ->postJson("/api/v1/timetable-versions/{$versionId}/slots", ['subject_assignment_id' => $b->id] + $cell)
        ->assertStatus(422)
        ->assertJsonPath('conflicts.0.code', 'section_clash');
});

<?php

use App\Actions\SaveAcademicYearAction;
use App\Models\Employee;
use App\Models\GradeLevel;
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

function teacherWithCapability($branch, string $name, array $subjectCodes, string $gradeCode): Employee
{
    $employee = Employee::create([
        'user_id' => User::factory()->create()->id,
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => $name, 'is_active' => true,
    ]);
    $employee->positions()->create(['job_title' => 'teacher', 'is_primary' => true]);

    $gradeId = GradeLevel::where('code', $gradeCode)->value('id');
    foreach ($subjectCodes as $code) {
        $employee->teacherSubjects()->create([
            'subject_id' => Subject::where('code', $code)->value('id'),
            'grade_level_id' => $gradeId,
        ]);
    }

    return $employee;
}

it('generates the curriculum grid and pre-fills only unambiguous teachers', function () {
    $branch = makeBranch();
    $g1 = GradeLevel::where('code', 'G1')->value('id');
    $section = $branch->sections()->create(['school_id' => $branch->school_id, 'grade_level_id' => $g1, 'name' => 'A']);

    // One unambiguous Math teacher; two competing English teachers.
    $math = teacherWithCapability($branch, 'MathOnly', ['MATH'], 'G1');
    teacherWithCapability($branch, 'Eng1', ['ENG'], 'G1');
    teacherWithCapability($branch, 'Eng2', ['ENG'], 'G1');

    Sanctum::actingAs(directorOf($branch));
    $year = (new SaveAcademicYearAction())->execute($branch, ['name' => '2017 E.C.']);
    $term = $year->terms->first();

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/terms/{$term->id}/generate-assignments")
        ->assertOk();

    $mathSubject = Subject::where('code', 'MATH')->value('id');
    $engSubject = Subject::where('code', 'ENG')->value('id');
    $physics = Subject::where('code', 'PHY')->value('id');

    // Grade-1 applicable subjects generated; Physics (grades 9-12) skipped.
    expect(SubjectAssignment::where('term_id', $term->id)->where('subject_id', $physics)->exists())->toBeFalse();

    // Unambiguous capability pre-filled; ambiguous left unassigned.
    expect(SubjectAssignment::where('term_id', $term->id)->where('subject_id', $mathSubject)->value('employee_id'))->toBe($math->id);
    expect(SubjectAssignment::where('term_id', $term->id)->where('subject_id', $engSubject)->value('employee_id'))->toBeNull();

    // Idempotent: a second run creates nothing.
    $before = SubjectAssignment::where('term_id', $term->id)->count();
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/terms/{$term->id}/generate-assignments")
        ->assertOk()
        ->assertJsonPath('data.created', 0);
    expect(SubjectAssignment::where('term_id', $term->id)->count())->toBe($before);
});

it('copies assignments from a sibling semester and clones a semester with its grid', function () {
    $branch = makeBranch();
    $g1 = GradeLevel::where('code', 'G1')->value('id');
    $branch->sections()->create(['school_id' => $branch->school_id, 'grade_level_id' => $g1, 'name' => 'A']);
    teacherWithCapability($branch, 'Solo', ['MATH'], 'G1');

    Sanctum::actingAs(directorOf($branch));
    $year = (new SaveAcademicYearAction())->execute($branch, ['name' => '2017 E.C.']);
    [$term1, $term2] = $year->terms->all();

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/terms/{$term1->id}/generate-assignments")->assertOk();
    $count = SubjectAssignment::where('term_id', $term1->id)->count();
    expect($count)->toBeGreaterThan(0);

    // Copy Semester 1's grid onto Semester 2.
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/terms/{$term2->id}/copy-assignments", ['source_term_id' => $term1->id])
        ->assertOk()
        ->assertJsonPath('data.created', $count);

    // Clone Semester 1 into a new semester with its grid.
    $clone = $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/terms/{$term1->id}/clone", ['name' => 'Semester 3'])
        ->assertCreated();

    expect(SubjectAssignment::where('term_id', $clone->json('data.id'))->count())->toBe($count);
});

it('returns the assignment matrix with pickers metadata', function () {
    $branch = makeBranch();
    $g1 = GradeLevel::where('code', 'G1')->value('id');
    $branch->sections()->create(['school_id' => $branch->school_id, 'grade_level_id' => $g1, 'name' => 'A']);
    teacherWithCapability($branch, 'Solo', ['MATH'], 'G1');

    Sanctum::actingAs(directorOf($branch));
    $year = (new SaveAcademicYearAction())->execute($branch, ['name' => '2017 E.C.']);
    $term = $year->terms->first();

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/terms/{$term->id}/generate-assignments")->assertOk();

    $matrix = $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/terms/{$term->id}/assignment-matrix")
        ->assertOk();

    expect($matrix->json('meta.teachers'))->toHaveCount(1);
    expect($matrix->json('meta.capabilities.0.subject_id'))->not->toBeNull();
    expect($matrix->json('meta.sections'))->toHaveCount(1);
    expect(count($matrix->json('data')))->toBeGreaterThan(0);
});

it('provisions semesters with auto-generated grids only when the flag is on', function () {
    $branch = makeBranch();
    $g1 = GradeLevel::where('code', 'G1')->value('id');
    $branch->sections()->create(['school_id' => $branch->school_id, 'grade_level_id' => $g1, 'name' => 'A']);

    Sanctum::actingAs(directorOf($branch));

    // Default OFF: no assignments appear.
    $off = $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/academic-years', ['name' => '2018 E.C.', 'starts_on' => '2026-09-11', 'ends_on' => '2027-06-30'])
        ->assertCreated();
    $offTermIds = collect($off->json('data.terms'))->pluck('id');
    expect(SubjectAssignment::whereIn('term_id', $offTermIds)->count())->toBe(0);

    // Explicit opt-in: every provisioned semester gets its grid.
    $on = $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/academic-years', [
            'name' => '2019 E.C.', 'starts_on' => '2027-09-11', 'ends_on' => '2028-06-30',
            'auto_generate_assignments' => true,
        ])
        ->assertCreated();
    $onTermIds = collect($on->json('data.terms'))->pluck('id');
    expect(SubjectAssignment::whereIn('term_id', $onTermIds)->count())->toBeGreaterThan(0);
});

it('generates rows for every grade with sections, not just one', function () {
    $branch = makeBranch();
    foreach (['KG1', 'G1', 'G5', 'G7', 'G9', 'G12'] as $code) {
        $branch->sections()->create([
            'school_id' => $branch->school_id,
            'grade_level_id' => GradeLevel::where('code', $code)->value('id'),
            'name' => 'A',
        ]);
    }

    Sanctum::actingAs(directorOf($branch));
    $year = (new SaveAcademicYearAction())->execute($branch, ['name' => '2017 E.C.']);
    $term = $year->terms->first();

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/terms/{$term->id}/generate-assignments")
        ->assertOk();

    $bySection = SubjectAssignment::where('term_id', $term->id)
        ->join('sections', 'sections.id', '=', 'subject_assignments.section_id')
        ->join('grade_levels', 'grade_levels.id', '=', 'sections.grade_level_id')
        ->selectRaw('grade_levels.code, count(*) as c')
        ->groupBy('grade_levels.code')
        ->pluck('c', 'code');

    foreach (['KG1', 'G1', 'G5', 'G7', 'G9', 'G12'] as $code) {
        expect($bySection[$code] ?? 0)->toBeGreaterThan(0);
    }
});

it('autofills only unambiguous unassigned slots', function () {
    $branch = makeBranch();
    $g1 = GradeLevel::where('code', 'G1')->value('id');
    $branch->sections()->create(['school_id' => $branch->school_id, 'grade_level_id' => $g1, 'name' => 'A']);

    // Two competing English teachers -> ENG stays unassigned after generation.
    $eng1 = teacherWithCapability($branch, 'Eng1', ['ENG'], 'G1');
    teacherWithCapability($branch, 'Eng2', ['ENG'], 'G1');

    Sanctum::actingAs(directorOf($branch));
    $year = (new SaveAcademicYearAction())->execute($branch, ['name' => '2017 E.C.']);
    $term = $year->terms->first();

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/terms/{$term->id}/generate-assignments")->assertOk();

    $eng = Subject::where('code', 'ENG')->value('id');
    expect(SubjectAssignment::where('term_id', $term->id)->where('subject_id', $eng)->value('employee_id'))->toBeNull();

    // Still ambiguous -> autofill fills nothing for ENG.
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/terms/{$term->id}/autofill-assignments")
        ->assertOk()
        ->assertJsonPath('data.filled', 0);

    // One teacher leaves -> ENG becomes unambiguous and autofill picks it up.
    Employee::where('first_name', 'Eng2')->update(['is_active' => false]);

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/terms/{$term->id}/autofill-assignments")
        ->assertOk()
        ->assertJsonPath('data.filled', 1);

    expect(SubjectAssignment::where('term_id', $term->id)->where('subject_id', $eng)->value('employee_id'))->toBe($eng1->id);
});

it('force-copies over an identical grid, replacing teachers', function () {
    $branch = makeBranch();
    $g1 = GradeLevel::where('code', 'G1')->value('id');
    $branch->sections()->create(['school_id' => $branch->school_id, 'grade_level_id' => $g1, 'name' => 'A']);
    $math = teacherWithCapability($branch, 'Solo', ['MATH'], 'G1');

    Sanctum::actingAs(directorOf($branch));
    $year = (new SaveAcademicYearAction())->execute($branch, ['name' => '2017 E.C.']);
    [$term1, $term2] = $year->terms->all();

    // Both semesters generated -> identical pairs; Semester 2 loses its Math teacher.
    foreach ([$term1, $term2] as $term) {
        $this->withHeaders(branchContext($branch))
            ->postJson("/api/v1/terms/{$term->id}/generate-assignments")->assertOk();
    }
    $mathSubject = Subject::where('code', 'MATH')->value('id');
    SubjectAssignment::where('term_id', $term2->id)->where('subject_id', $mathSubject)
        ->update(['employee_id' => null]);

    // Plain copy: nothing new.
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/terms/{$term2->id}/copy-assignments", ['source_term_id' => $term1->id])
        ->assertOk()
        ->assertJsonPath('data.created', 0)
        ->assertJsonPath('data.updated', 0);

    // Forced copy: overwrites the differing pair from the source.
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/terms/{$term2->id}/copy-assignments", ['source_term_id' => $term1->id, 'replace' => true])
        ->assertOk()
        ->assertJsonPath('data.created', 0)
        ->assertJsonPath('data.updated', 1);

    expect(SubjectAssignment::where('term_id', $term2->id)->where('subject_id', $mathSubject)->value('employee_id'))
        ->toBe($math->id);
});

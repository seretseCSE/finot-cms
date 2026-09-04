<?php

use App\Actions\EnrollStudentAction;
use App\Actions\SaveAcademicYearAction;
use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\GradeLevel;
use App\Models\School;
use App\Models\SchoolProgram;
use App\Models\Student;
use App\Support\GradeOffering;
use Database\Seeders\GradeLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(GradeLevelSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

/** @return list<int> */
function offeringGradeIds(array $codes): array
{
    return GradeLevel::whereIn('code', $codes)->orderBy('sort_order')->pluck('id')->all();
}

function offeringYear(Branch $branch): AcademicYear
{
    return (new SaveAcademicYearAction())->execute($branch, ['name' => '2018 E.C.', 'status' => 'active']);
}

function offeringStudent(Branch $branch): Student
{
    return $branch->students()->create([
        'school_id' => $branch->school_id,
        'first_name' => 'Abel',
        'father_name' => 'Tesfaye',
        'gender' => 'male',
    ]);
}

// ───────────────────────── provisioning defaults ─────────────────────────

it('provisions Regular across every grade when a branch is created without a matrix', function () {
    $school = School::create(['name' => 'Unity Academy']);
    Sanctum::actingAs(platformAdmin());

    $this->postJson("/api/v1/schools/{$school->id}/branches", [
        'name' => 'Main', 'code' => 'AA-9001',
    ])->assertCreated();

    $branch = Branch::firstWhere('code', 'AA-9001');
    $regular = SchoolProgram::firstWhere(['branch_id' => $branch->id, 'type' => 'regular']);

    expect($regular->gradeLevels()->count())->toBe(GradeLevel::count());
    expect(GradeOffering::supportedGradeIds($branch->id))->toHaveCount(GradeLevel::count());
});

it('stores an explicit grade × program matrix on creation', function () {
    $school = School::create(['name' => 'Unity Academy']);
    Sanctum::actingAs(platformAdmin());

    $response = $this->postJson("/api/v1/schools/{$school->id}/branches", [
        'name' => 'Main', 'code' => 'AA-9002',
        'programs' => [
            ['type' => 'regular', 'grade_level_ids' => offeringGradeIds(['G1', 'G2', 'G3', 'G4'])],
            ['type' => 'night', 'grade_level_ids' => offeringGradeIds(['G3', 'G4'])],
        ],
    ]);

    $response->assertCreated();
    expect(collect($response->json('data.programs'))->pluck('type')->sort()->values()->all())
        ->toBe(['night', 'regular']);

    $branch = Branch::firstWhere('code', 'AA-9002');

    expect(GradeOffering::supportedGradeIds($branch->id))->toHaveCount(4);
    $night = SchoolProgram::firstWhere(['branch_id' => $branch->id, 'type' => 'night']);
    expect($night->gradeLevels()->pluck('code')->sort()->values()->all())->toBe(['G3', 'G4']);
});

it('gives a program added outside the editor every grade by default', function () {
    $branch = makeBranch();

    $program = SchoolProgram::addToBranch($branch, 'extension');

    expect($program->gradeLevels()->count())->toBe(GradeLevel::count());
});

// ───────────────────────── scoped /grade-levels ─────────────────────────

it('scopes grade levels to the branch offering in a branch context', function () {
    $branch = makeBranch();
    GradeOffering::sync($branch, [
        ['type' => 'regular', 'grade_level_ids' => offeringGradeIds(['G1', 'G2', 'G3'])],
        ['type' => 'night', 'grade_level_ids' => offeringGradeIds(['G3'])],
    ]);
    Sanctum::actingAs(directorOf($branch));

    $response = $this->withHeaders(branchContext($branch))->getJson('/api/v1/grade-levels');

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('code')->all())->toBe(['G1', 'G2', 'G3']);

    $night = SchoolProgram::firstWhere(['branch_id' => $branch->id, 'type' => 'night']);
    $g3 = collect($response->json('data'))->firstWhere('code', 'G3');
    expect($g3['program_ids'])->toContain($night->id);
    $g1 = collect($response->json('data'))->firstWhere('code', 'G1');
    expect($g1['program_ids'])->not->toContain($night->id);
});

it('returns the full ladder with ?all=1 and for an unconfigured branch', function () {
    $branch = makeBranch();
    GradeOffering::sync($branch, [['type' => 'regular', 'grade_level_ids' => offeringGradeIds(['G1'])]]);
    Sanctum::actingAs(directorOf($branch));

    $all = $this->withHeaders(branchContext($branch))->getJson('/api/v1/grade-levels?all=1');
    expect($all->json('data'))->toHaveCount(GradeLevel::count());

    // A branch with no matrix rows at all (predates the feature) is unscoped.
    $bare = School::create(['name' => 'Bare School'])->branches()->create(['name' => 'Main', 'code' => 'AA-9003']);
    Sanctum::actingAs(directorOf($bare));
    $fallback = $this->withHeaders(branchContext($bare))->getJson('/api/v1/grade-levels');
    expect($fallback->json('data'))->toHaveCount(GradeLevel::count());
});

it('shows a school manager the union across branches, narrowable by branch_id', function () {
    $branchA = makeBranch();
    $school = School::find($branchA->school_id);
    $branchB = $school->branches()->create(['name' => 'Second', 'code' => 'AA-9004']);
    GradeOffering::sync($branchA, [['type' => 'regular', 'grade_level_ids' => offeringGradeIds(['G1', 'G2'])]]);
    GradeOffering::sync($branchB, [['type' => 'regular', 'grade_level_ids' => offeringGradeIds(['G9', 'G10'])]]);

    Sanctum::actingAs(schoolPrincipal($branchA));

    $union = $this->withHeaders(schoolContext($school))->getJson('/api/v1/grade-levels');
    expect(collect($union->json('data'))->pluck('code')->all())->toBe(['G1', 'G2', 'G9', 'G10']);

    $narrowed = $this->withHeaders(schoolContext($school))->getJson("/api/v1/grade-levels?branch_id={$branchB->id}");
    expect(collect($narrowed->json('data'))->pluck('code')->all())->toBe(['G9', 'G10']);
});

// ───────────────────────── write validation ─────────────────────────

it('rejects a section in a grade the branch does not offer', function () {
    $branch = makeBranch();
    GradeOffering::sync($branch, [['type' => 'regular', 'grade_level_ids' => offeringGradeIds(['G1', 'G2'])]]);
    Sanctum::actingAs(directorOf($branch));

    $this->withHeaders(branchContext($branch))->postJson('/api/v1/sections', [
        'grade_level_id' => GradeLevel::where('code', 'G9')->value('id'),
        'name' => 'A',
    ])->assertUnprocessable()->assertJsonValidationErrors('grade_level_id');

    $this->withHeaders(branchContext($branch))->postJson('/api/v1/sections', [
        'grade_level_id' => GradeLevel::where('code', 'G1')->value('id'),
        'name' => 'A',
    ])->assertCreated();
});

it('rejects an enrollment whose grade or program is not offered', function () {
    $branch = makeBranch();
    GradeOffering::sync($branch, [
        ['type' => 'regular', 'grade_level_ids' => offeringGradeIds(['G1', 'G2'])],
        ['type' => 'night', 'grade_level_ids' => offeringGradeIds(['G2'])],
    ]);
    $year = offeringYear($branch);
    $night = SchoolProgram::firstWhere(['branch_id' => $branch->id, 'type' => 'night']);

    // Unsupported grade.
    expect(fn () => app(EnrollStudentAction::class)->execute(offeringStudent($branch), [
        'academic_year_id' => $year->id,
        'grade_level_id' => GradeLevel::where('code', 'G9')->value('id'),
    ]))->toThrow(ValidationException::class);

    // Grade offered, but not in this program.
    expect(fn () => app(EnrollStudentAction::class)->execute(offeringStudent($branch), [
        'academic_year_id' => $year->id,
        'grade_level_id' => GradeLevel::where('code', 'G1')->value('id'),
        'school_program_id' => $night->id,
    ]))->toThrow(ValidationException::class);

    // The happy path still enrolls.
    $enrollment = app(EnrollStudentAction::class)->execute(offeringStudent($branch), [
        'academic_year_id' => $year->id,
        'grade_level_id' => GradeLevel::where('code', 'G2')->value('id'),
        'school_program_id' => $night->id,
    ]);
    expect($enrollment->exists)->toBeTrue();
});

// ───────────────────────── in-use removal guards ─────────────────────────

it('blocks unchecking a grade that still has live enrollments or sections', function () {
    $branch = makeBranch();
    GradeOffering::sync($branch, [['type' => 'regular', 'grade_level_ids' => offeringGradeIds(['G1', 'G2'])]]);
    $year = offeringYear($branch);
    app(EnrollStudentAction::class)->execute(offeringStudent($branch), [
        'academic_year_id' => $year->id,
        'grade_level_id' => GradeLevel::where('code', 'G1')->value('id'),
    ]);

    Sanctum::actingAs(schoolPrincipal($branch));

    // G1 holds a live enrollment — removal rejects with a usage message.
    $this->withHeaders(schoolContext($branch))->patchJson("/api/v1/branches/{$branch->id}", [
        'programs' => [['type' => 'regular', 'grade_level_ids' => offeringGradeIds(['G2'])]],
    ])->assertUnprocessable()->assertJsonValidationErrors('programs');

    // G2 is unused — narrowing to G1 succeeds.
    $this->withHeaders(schoolContext($branch))->patchJson("/api/v1/branches/{$branch->id}", [
        'programs' => [['type' => 'regular', 'grade_level_ids' => offeringGradeIds(['G1'])]],
    ])->assertOk();

    expect(GradeOffering::supportedGradeIds($branch->id))->toBe(offeringGradeIds(['G1']));
});

it('blocks removing every grade from the offering', function () {
    $branch = makeBranch();
    GradeOffering::sync($branch, [['type' => 'regular', 'grade_level_ids' => offeringGradeIds(['G1'])]]);
    Sanctum::actingAs(schoolPrincipal($branch));

    $this->withHeaders(schoolContext($branch))->patchJson("/api/v1/branches/{$branch->id}", [
        'programs' => [['type' => 'regular', 'grade_level_ids' => []]],
    ])->assertUnprocessable()->assertJsonValidationErrors('programs');
});

it('keeps programs absent from the payload untouched (additive sync)', function () {
    $branch = makeBranch();
    GradeOffering::sync($branch, [
        ['type' => 'regular', 'grade_level_ids' => offeringGradeIds(['G1', 'G2'])],
        ['type' => 'night', 'grade_level_ids' => offeringGradeIds(['G2'])],
    ]);
    Sanctum::actingAs(schoolPrincipal($branch));

    $this->withHeaders(schoolContext($branch))->patchJson("/api/v1/branches/{$branch->id}", [
        'programs' => [['type' => 'regular', 'grade_level_ids' => offeringGradeIds(['G1', 'G2', 'G3'])]],
    ])->assertOk();

    $night = SchoolProgram::firstWhere(['branch_id' => $branch->id, 'type' => 'night']);
    expect($night->gradeLevels()->pluck('code')->all())->toBe(['G2']);
    expect(GradeOffering::supportedGradeIds($branch->id))->toHaveCount(3);
});

// ───────────────────────── list stats ─────────────────────────

it('derives the list grade span from the offering, not from sections', function () {
    $branch = makeBranch();
    GradeOffering::sync($branch, [
        ['type' => 'regular', 'grade_level_ids' => offeringGradeIds(['G9', 'G10', 'G11', 'G12'])],
    ]);
    Sanctum::actingAs(schoolPrincipal($branch));

    // No sections exist at all — the span must still show the configured matrix.
    $names = GradeLevel::whereIn('code', ['G9', 'G12'])->orderBy('sort_order')->pluck('name');

    $row = collect($this->withHeaders(schoolContext($branch))->getJson('/api/v1/branches')
        ->assertOk()->json('data'))->firstWhere('id', $branch->id);

    expect($row['grade_min'])->toBe($names[0]);
    expect($row['grade_max'])->toBe($names[1]);
});

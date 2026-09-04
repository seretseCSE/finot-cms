<?php

use App\Actions\SaveAcademicYearAction;
use App\Enums\AcademicYearStatus;
use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\FeeStructure;
use App\Models\GradeLevel;
use App\Models\SchoolProgram;
use App\Models\Section;
use App\Models\Term;
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

it('exposes the seeded national grade levels to any authenticated user', function () {
    Sanctum::actingAs(User::factory()->create());

    $response = $this->getJson('/api/v1/grade-levels')->assertOk();

    expect($response->json('data'))->toHaveCount(15);
    $response->assertJsonPath('data.0.code', 'KG1');
    expect(GradeLevel::where('code', 'G6')->value('has_national_exam'))->toBeTrue();
    expect(GradeLevel::where('code', 'G8')->value('has_national_exam'))->toBeTrue();
    expect(GradeLevel::where('code', 'G12')->value('has_national_exam'))->toBeTrue();
    expect(GradeLevel::where('code', 'G10')->value('has_national_exam'))->toBeFalse();
});

it('lets a director create an academic year with two auto-seeded semesters', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));

    $response = $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/academic-years', ['name' => '2017 E.C.', 'starts_on' => '2026-09-11', 'ends_on' => '2027-06-30', 'status' => 'active'])
        ->assertCreated()
        ->assertJsonPath('data.name', '2017 E.C.')
        ->assertJsonPath('data.status', 'active');

    expect($response->json('data.terms'))->toHaveCount(2);
    expect(Term::where('branch_id', $branch->id)->pluck('sequence')->all())->toBe([1, 2]);
});

it('keeps only one current academic year per branch', function () {
    $branch = makeBranch();
    $director = directorOf($branch);
    Sanctum::actingAs($director);

    $first = (new SaveAcademicYearAction())->execute($branch, ['name' => '2016 E.C.', 'status' => 'active']);

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/academic-years', ['name' => '2017 E.C.', 'starts_on' => '2026-09-11', 'ends_on' => '2027-06-30', 'status' => 'active'])
        ->assertCreated();

    expect($first->refresh()->status)->toBe(AcademicYearStatus::Completed);
    expect(AcademicYear::where('branch_id', $branch->id)->where('status', 'active')->count())->toBe(1);
});

it('requires an active branch to create an academic year', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));

    // No X-Branch-Id header → 422.
    $this->postJson('/api/v1/academic-years', ['name' => '2017 E.C.'])
        ->assertStatus(422);
});

it('forbids a director from creating sections in a branch they do not belong to', function () {
    $branchA = makeBranch('AA-0001');
    $branchB = makeBranch('AA-0002');
    Sanctum::actingAs(directorOf($branchA));

    // Acting director belongs to A, but sends B's context.
    $this->withHeaders(branchContext($branchB))
        ->postJson('/api/v1/sections', [
            'grade_level_id' => GradeLevel::where('code', 'G1')->value('id'),
            'name' => 'A',
        ])
        ->assertStatus(422); // context rejected → no active branch resolved
});

it('creates a section and enforces unique name per grade level', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));
    $gradeId = GradeLevel::where('code', 'G1')->value('id');

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/sections', ['grade_level_id' => $gradeId, 'name' => 'A', 'capacity' => 40])
        ->assertCreated()
        ->assertJsonPath('data.name', 'A')
        ->assertJsonPath('data.grade_level.code', 'G1');

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/sections', ['grade_level_id' => $gradeId, 'name' => 'A'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');

    expect(Section::where('branch_id', $branch->id)->count())->toBe(1);
});

it('recreates a section after deletion by resurrecting the trashed row', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));
    $gradeId = GradeLevel::where('code', 'G1')->value('id');

    $created = $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/sections', ['grade_level_id' => $gradeId, 'name' => 'A', 'capacity' => 40])
        ->assertCreated()
        ->json('data.id');

    $this->withHeaders(branchContext($branch))
        ->deleteJson("/api/v1/sections/{$created}")
        ->assertOk();

    // Same grade + name again: the trashed row must not 23505 the insert —
    // the original row comes back (same id → historical links survive).
    $recreated = $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/sections', ['grade_level_id' => $gradeId, 'name' => 'A', 'capacity' => 35])
        ->assertCreated()
        ->json('data.id');

    expect($recreated)->toBe($created);
    expect(Section::where('branch_id', $branch->id)->count())->toBe(1);
    expect((int) Section::find($created)->capacity)->toBe(35);
});

it('allows the same section name under different grade levels', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));

    foreach (['G1', 'G2'] as $code) {
        $this->withHeaders(branchContext($branch))
            ->postJson('/api/v1/sections', [
                'grade_level_id' => GradeLevel::where('code', $code)->value('id'),
                'name' => 'A',
            ])
            ->assertCreated();
    }

    expect(Section::where('branch_id', $branch->id)->count())->toBe(2);
});

it('activates a semester exclusively, closing the other active one of the same program', function () {
    $branch = makeBranch();
    $director = directorOf($branch);
    Sanctum::actingAs($director);

    $year = (new SaveAcademicYearAction())->execute($branch, ['name' => '2017 E.C.']);
    [$term1, $term2] = $year->terms->all();

    $this->withHeaders(branchContext($branch))
        ->patchJson("/api/v1/terms/{$term1->id}/status", ['status' => 'active'])
        ->assertOk()
        ->assertJsonPath('data.is_current', true)
        ->assertJsonPath('data.status', 'active');

    $response = $this->withHeaders(branchContext($branch))
        ->patchJson("/api/v1/terms/{$term2->id}/status", ['status' => 'active'])
        ->assertOk();

    // The previously active semester closes (read-only) and loses currency.
    expect($response->json('meta.closed_terms'))->toBe([$term1->refresh()->name]);
    expect($term1->status->value)->toBe('closed');
    expect($term1->is_current)->toBeFalse();
    expect(Term::where('branch_id', $branch->id)->where('is_current', true)->count())->toBe(1);

    // A closed semester can be reopened to planned.
    $this->withHeaders(branchContext($branch))
        ->patchJson("/api/v1/terms/{$term1->id}/status", ['status' => 'planned'])
        ->assertOk()
        ->assertJsonPath('data.status', 'planned');
});

it('provisions the requested number of semesters (1-5) on creation', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));

    $response = $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/academic-years', ['name' => '2018 E.C.', 'starts_on' => '2026-09-11', 'ends_on' => '2027-06-30', 'terms_count' => 4])
        ->assertCreated();

    expect($response->json('data.terms'))->toHaveCount(4);
    expect($response->json('data.terms.3.name'))->toBe('Semester 4');
    // Auto-seeded semesters run on the branch's Regular program by default.
    expect($response->json('data.terms.0.program.type'))->toBe('regular');
});

it('switches year status from the dedicated endpoint and demotes the previous active year', function () {
    $branch = makeBranch();
    $old = (new SaveAcademicYearAction())->execute($branch, ['name' => '2016 E.C.', 'status' => 'active']);
    $new = (new SaveAcademicYearAction())->execute($branch, ['name' => '2017 E.C.']);

    Sanctum::actingAs(directorOf($branch));

    $this->withHeaders(branchContext($branch))
        ->patchJson("/api/v1/academic-years/{$new->id}/status", ['status' => 'active'])
        ->assertOk()
        ->assertJsonPath('data.status', 'active');

    expect($old->refresh()->status)->toBe(AcademicYearStatus::Completed);

    // And back down again — completing the active year leaves none active.
    $this->withHeaders(branchContext($branch))
        ->patchJson("/api/v1/academic-years/{$new->id}/status", ['status' => 'completed'])
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');

    expect(AcademicYear::where('branch_id', $branch->id)->where('status', 'active')->count())->toBe(0);
});

it('adds a semester under a year with full schedule fields, auto-adding a missing branch program', function () {
    $branch = makeBranch();
    $year = (new SaveAcademicYearAction())->execute($branch, ['name' => '2017 E.C.', 'terms_count' => 1]);
    Sanctum::actingAs(directorOf($branch));

    expect(SchoolProgram::where('branch_id', $branch->id)->where('type', 'night')->exists())->toBeFalse();

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/academic-years/{$year->id}/terms", [
            'name' => 'Night Quarter 1',
            'program_type' => 'night',
            'starts_on' => '2026-09-15',
            'ends_on' => '2026-11-30',
            'class_starts_at' => '18:00',
            'class_ends_at' => '21:00',
            'period_minutes' => 40,
            'is_quarter' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.sequence', 2)
        ->assertJsonPath('data.program.type', 'night')
        ->assertJsonPath('data.is_quarter', true)
        ->assertJsonPath('data.class_starts_at', '18:00')
        ->assertJsonPath('data.period_minutes', 40);

    // Picking a program the branch didn't run yet updates the branch settings.
    expect(SchoolProgram::where('branch_id', $branch->id)->where('type', 'night')->exists())->toBeTrue();
});

it('caps a year at 5 semesters and refuses deleting the last one', function () {
    $branch = makeBranch();
    $year = (new SaveAcademicYearAction())->execute($branch, ['name' => '2017 E.C.', 'terms_count' => 5]);
    Sanctum::actingAs(directorOf($branch));

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/academic-years/{$year->id}/terms", ['name' => 'Extra'])
        ->assertStatus(422);

    $solo = (new SaveAcademicYearAction())->execute($branch, ['name' => '2018 E.C.', 'terms_count' => 1]);
    $term = $solo->terms()->first();

    $this->withHeaders(branchContext($branch))
        ->deleteJson("/api/v1/terms/{$term->id}")
        ->assertStatus(422);
});

it('lists semesters standalone in the branch context', function () {
    $branch = makeBranch();
    (new SaveAcademicYearAction())->execute($branch, ['name' => '2017 E.C.', 'terms_count' => 2]);

    $other = makeBranch('AA-0002');
    (new SaveAcademicYearAction())->execute($other, ['name' => '2017 E.C.', 'terms_count' => 3]);

    Sanctum::actingAs(directorOf($branch));

    $response = $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/terms')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(2);
    expect($response->json('data.0.academic_year_name'))->toBe('2017 E.C.');
});

it('soft-deletes a year\'s semesters and fees along with the year', function () {
    $branch = makeBranch();
    $year = (new SaveAcademicYearAction())->execute($branch, ['name' => '2017 E.C.', 'terms_count' => 2]);
    $fee = FeeStructure::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id, 'academic_year_id' => $year->id,
        'name' => 'Tuition', 'amount' => 100, 'type' => 'monthly',
    ]);

    Sanctum::actingAs(directorOf($branch));

    $this->withHeaders(branchContext($branch))
        ->deleteJson("/api/v1/academic-years/{$year->id}")
        ->assertOk();

    expect(Term::where('academic_year_id', $year->id)->count())->toBe(0);
    expect(Term::withTrashed()->where('academic_year_id', $year->id)->count())->toBe(2);
    expect(FeeStructure::find($fee->id))->toBeNull();
    expect(FeeStructure::withTrashed()->find($fee->id)->trashed())->toBeTrue();

    // The semesters list no longer shows the orphans.
    $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/terms')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('rejects an academic year whose window overlaps an existing one in the branch', function () {
    $branch = makeBranch();
    (new SaveAcademicYearAction())->execute($branch, [
        'name' => '2017 E.C.', 'starts_on' => '2026-09-11', 'ends_on' => '2027-06-30',
    ]);
    Sanctum::actingAs(directorOf($branch));

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/academic-years', [
            'name' => '2017 E.C. (overlap)', 'starts_on' => '2027-01-01', 'ends_on' => '2027-08-30',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('starts_on');

    expect(AcademicYear::where('branch_id', $branch->id)->count())->toBe(1);
});

it('allows an academic year that runs back-to-back with an existing one', function () {
    $branch = makeBranch();
    (new SaveAcademicYearAction())->execute($branch, [
        'name' => '2017 E.C.', 'starts_on' => '2025-09-11', 'ends_on' => '2026-06-30',
    ]);
    Sanctum::actingAs(directorOf($branch));

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/academic-years', [
            'name' => '2018 E.C.', 'starts_on' => '2026-09-11', 'ends_on' => '2027-06-30',
        ])
        ->assertCreated();

    expect(AcademicYear::where('branch_id', $branch->id)->count())->toBe(2);
});

it('lets an overlapping year exist in a different branch', function () {
    $branchA = makeBranch('AA-0001');
    $branchB = makeBranch('AA-0002');
    (new SaveAcademicYearAction())->execute($branchA, [
        'name' => '2017 E.C.', 'starts_on' => '2026-09-11', 'ends_on' => '2027-06-30',
    ]);
    Sanctum::actingAs(directorOf($branchB));

    $this->withHeaders(branchContext($branchB))
        ->postJson('/api/v1/academic-years', [
            'name' => '2017 E.C.', 'starts_on' => '2026-09-11', 'ends_on' => '2027-06-30',
        ])
        ->assertCreated();
});

it('rejects a semester whose window overlaps another of the same program', function () {
    $branch = makeBranch();
    $year = (new SaveAcademicYearAction())->execute($branch, ['name' => '2017 E.C.', 'terms_count' => 1]);
    Sanctum::actingAs(directorOf($branch));

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/academic-years/{$year->id}/terms", [
            'name' => 'Semester 1', 'starts_on' => '2026-09-11', 'ends_on' => '2027-01-15',
        ])
        ->assertCreated();

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/academic-years/{$year->id}/terms", [
            'name' => 'Semester 2', 'starts_on' => '2027-01-01', 'ends_on' => '2027-06-30',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('starts_on');
});

it('allows overlapping semester windows across different programs', function () {
    $branch = makeBranch();
    $year = (new SaveAcademicYearAction())->execute($branch, ['name' => '2017 E.C.', 'terms_count' => 1]);
    Sanctum::actingAs(directorOf($branch));

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/academic-years/{$year->id}/terms", [
            'name' => 'Regular S1', 'program_type' => 'regular',
            'starts_on' => '2026-09-11', 'ends_on' => '2027-01-15',
        ])
        ->assertCreated();

    // A night-program semester may share the same dates — different program.
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/academic-years/{$year->id}/terms", [
            'name' => 'Night S1', 'program_type' => 'night',
            'starts_on' => '2026-09-11', 'ends_on' => '2027-01-15',
        ])
        ->assertCreated();
});

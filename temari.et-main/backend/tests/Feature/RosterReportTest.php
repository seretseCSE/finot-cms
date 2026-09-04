<?php

use App\Actions\EnrollStudentAction;
use App\Actions\SaveAcademicYearAction;
use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\GradeLevel;
use App\Models\Membership;
use App\Models\Section;
use App\Models\SectionHomeroom;
use App\Models\StudentEnrollment;
use App\Models\StudentTermResult;
use App\Models\Subject;
use App\Models\Term;
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

function rosterYear(Branch $branch): AcademicYear
{
    return (new SaveAcademicYearAction())->execute($branch, ['name' => '2017 E.C.', 'status' => 'active']);
}

function rosterSection(Branch $branch, string $name = 'A', string $gradeCode = 'G1'): Section
{
    return $branch->sections()->create([
        'school_id' => $branch->school_id,
        'grade_level_id' => GradeLevel::where('code', $gradeCode)->value('id'),
        'name' => $name,
    ]);
}

function rosterEnroll(Branch $branch, AcademicYear $year, Section $section, string $first, string $gender = 'male'): StudentEnrollment
{
    $student = $branch->students()->create([
        'school_id' => $branch->school_id,
        'first_name' => $first,
        'father_name' => 'Tesfaye',
        'gender' => $gender,
    ]);

    return app(EnrollStudentAction::class)->execute($student, [
        'academic_year_id' => $year->id,
        'section_id' => $section->id,
        'grade_level_id' => $section->grade_level_id,
    ]);
}

/**
 * Freeze one result row directly — the roster endpoints only READ
 * student_term_results, so tests skip the assessment plumbing.
 *
 * @param  array<string, ?float>  $subjects  code → total (MATH, ENG …)
 */
function rosterFreeze(StudentEnrollment $enrollment, Term $term, array $subjects, ?int $rank = null, ?int $rankOf = null): StudentTermResult
{
    $breakdown = collect($subjects)->map(fn (?float $total, string $code) => [
        'subject_id' => Subject::where('code', $code)->value('id'),
        'code' => $code,
        'name' => $code,
        'total' => $total,
        'letter' => null,
        'band_label' => null,
        'is_passing' => $total === null ? null : $total >= 50,
    ])->values()->all();

    $scored = collect($breakdown)->whereNotNull('total');

    return StudentTermResult::create([
        'student_id' => $enrollment->student_id,
        'student_enrollment_id' => $enrollment->id,
        'term_id' => $term->id,
        'school_id' => $enrollment->school_id,
        'branch_id' => $enrollment->branch_id,
        'academic_year_id' => $enrollment->academic_year_id,
        'section_id' => $enrollment->section_id,
        'grade_level_id' => $enrollment->grade_level_id,
        'total' => $scored->isEmpty() ? null : round((float) $scored->sum('total'), 2),
        'average' => $scored->isEmpty() ? null : round((float) $scored->avg('total'), 2),
        'rank' => $rank,
        'rank_of' => $rankOf,
        'subject_count' => $scored->count(),
        'breakdown' => $breakdown,
        'grading' => null,
        'computed_at' => now(),
    ]);
}

// ───────────────────────── semester field ─────────────────────────

it('stores the semester tag on quarter terms and rejects invalid values', function () {
    $branch = makeBranch();
    $year = rosterYear($branch);
    Sanctum::actingAs(directorOf($branch));

    $created = $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/academic-years/{$year->id}/terms", [
            'name' => 'Quarter 3',
            'is_quarter' => true,
            'semester' => 2,
        ])
        ->assertCreated()
        ->json('data');

    expect($created['is_quarter'])->toBeTrue()
        ->and($created['semester'])->toBe(2);

    $termId = $created['id'];

    $this->withHeaders(branchContext($branch))
        ->putJson("/api/v1/terms/{$termId}", ['name' => 'Quarter 3', 'semester' => 3])
        ->assertUnprocessable();

    // Turning the quarter flag off clears the grouping.
    $this->withHeaders(branchContext($branch))
        ->putJson("/api/v1/terms/{$termId}", ['name' => 'Quarter 3', 'is_quarter' => false, 'semester' => 2])
        ->assertOk()
        ->assertJsonPath('data.semester', null);
});

// ───────────────────────── term roster ─────────────────────────

it('returns the term roster: subject column union, keyed scores, rank order', function () {
    $branch = makeBranch();
    $year = rosterYear($branch);
    $section = rosterSection($branch);
    $term = $year->terms()->first();

    $abel = rosterEnroll($branch, $year, $section, 'Abel');
    $hana = rosterEnroll($branch, $year, $section, 'Hana', 'female');
    rosterFreeze($abel, $term, ['MATH' => 80, 'ENG' => 70], rank: 2, rankOf: 2);
    rosterFreeze($hana, $term, ['MATH' => 95, 'AMH' => 88], rank: 1, rankOf: 2);

    Sanctum::actingAs(directorOf($branch));

    $res = $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/terms/{$term->id}/roster?section_id={$section->id}")
        ->assertOk()
        ->json();

    $columns = collect($res['data']['columns'])->pluck('code');
    expect($columns->all())->toContain('MATH', 'ENG', 'AMH')
        ->and($res['data']['rows'])->toHaveCount(2)
        ->and($res['meta']['students'])->toBe(2)
        ->and($res['meta']['computed_at'])->not->toBeNull();

    // Rank order: Hana first.
    $first = $res['data']['rows'][0];
    expect($first['full_name'])->toContain('Hana')
        ->and($first['rank'])->toBe(1)
        ->and($first['average'])->toEqualWithDelta(91.5, 0.01);

    // Scores are keyed by subject_id.
    $mathId = Subject::where('code', 'MATH')->value('id');
    expect($first['scores'][(string) $mathId]['total'])->toEqualWithDelta(95.0, 0.01)
        ->and($first['scores'][(string) $mathId]['is_passing'])->toBeTrue();
});

it('narrows the term roster by section and by grade', function () {
    $branch = makeBranch();
    $year = rosterYear($branch);
    $term = $year->terms()->first();
    $g1a = rosterSection($branch, 'A', 'G1');
    $g2a = rosterSection($branch, 'B', 'G2');

    rosterFreeze(rosterEnroll($branch, $year, $g1a, 'Abel'), $term, ['MATH' => 80]);
    rosterFreeze(rosterEnroll($branch, $year, $g2a, 'Hana'), $term, ['MATH' => 90]);

    Sanctum::actingAs(directorOf($branch));

    $bySection = $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/terms/{$term->id}/roster?section_id={$g1a->id}")
        ->assertOk()->json('data.rows');
    expect($bySection)->toHaveCount(1)
        ->and($bySection[0]['full_name'])->toContain('Abel');

    $gradeId = GradeLevel::where('code', 'G2')->value('id');
    $byGrade = $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/terms/{$term->id}/roster?grade_level_id={$gradeId}")
        ->assertOk()->json('data.rows');
    expect($byGrade)->toHaveCount(1)
        ->and($byGrade[0]['full_name'])->toContain('Hana');
});

it('scopes homeroom teachers to their own sections and denies outsiders', function () {
    $branch = makeBranch();
    $year = rosterYear($branch);
    $term = $year->terms()->first();
    $own = rosterSection($branch, 'A');
    $other = rosterSection($branch, 'B');

    rosterFreeze(rosterEnroll($branch, $year, $own, 'Abel'), $term, ['MATH' => 80]);
    rosterFreeze(rosterEnroll($branch, $year, $other, 'Hana'), $term, ['MATH' => 90]);

    // Homeroom teacher of section A.
    $teacher = memberOf($branch, Role::Teacher);
    $employee = Employee::create([
        'user_id' => $teacher->id,
        'school_id' => $branch->school_id,
        'branch_id' => $branch->id,
        'first_name' => 'Alemu',
        'father_name' => 'Bekele',
        'gender' => 'male',
    ]);
    SectionHomeroom::create([
        'section_id' => $own->id, 'academic_year_id' => $year->id, 'employee_id' => $employee->id,
    ]);

    Sanctum::actingAs($teacher);
    $rows = $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/terms/{$term->id}/roster")
        ->assertOk()->json('data.rows');
    expect($rows)->toHaveCount(1)
        ->and($rows[0]['section_id'])->toBe($own->id);

    // A teacher with no homeroom gets an empty sheet, not a 403.
    Sanctum::actingAs(memberOf($branch, Role::Teacher));
    $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/terms/{$term->id}/roster")
        ->assertOk()
        ->assertJsonCount(0, 'data.rows');

    // A parent-lane user (no staff membership) is denied outright.
    Sanctum::actingAs(User::factory()->create());
    $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/terms/{$term->id}/roster")
        ->assertForbidden();
});

it('denies every roster endpoint across the tenant boundary', function () {
    $branchA = makeBranch();
    $branchB = makeBranch('AA-0002');
    $year = rosterYear($branchA);
    $term = $year->terms()->first();

    Sanctum::actingAs(directorOf($branchB));

    $this->withHeaders(branchContext($branchB))
        ->getJson("/api/v1/terms/{$term->id}/roster")
        ->assertForbidden();
    $this->withHeaders(branchContext($branchB))
        ->getJson("/api/v1/academic-years/{$year->id}/roster")
        ->assertForbidden();
    $this->withHeaders(branchContext($branchB))
        ->getJson("/api/v1/terms/{$term->id}/marklist-analysis")
        ->assertForbidden();
});

// ───────────────────────── year roster ─────────────────────────

it('averages the year over term averages and ranks within the section', function () {
    $branch = makeBranch();
    $year = rosterYear($branch);
    $section = rosterSection($branch);
    [$sem1, $sem2] = $year->terms()->orderBy('sequence')->get();

    $abel = rosterEnroll($branch, $year, $section, 'Abel');
    $hana = rosterEnroll($branch, $year, $section, 'Hana', 'female');
    $sara = rosterEnroll($branch, $year, $section, 'Sara', 'female');

    // Abel: 80 / 90 → 85. Hana: 85 / 85 → 85 (tie). Sara: only sem1 70 → 70.
    rosterFreeze($abel, $sem1, ['MATH' => 80]);
    rosterFreeze($abel, $sem2, ['MATH' => 90]);
    rosterFreeze($hana, $sem1, ['MATH' => 85]);
    rosterFreeze($hana, $sem2, ['MATH' => 85]);
    rosterFreeze($sara, $sem1, ['MATH' => 70]);

    Sanctum::actingAs(directorOf($branch));

    $res = $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/academic-years/{$year->id}/roster?section_id={$section->id}")
        ->assertOk()
        ->json();

    expect($res['meta']['has_semester_groups'])->toBeFalse()
        ->and($res['meta']['terms'])->toHaveCount(2)
        ->and($res['data']['students'])->toHaveCount(3);

    $byName = collect($res['data']['students'])->keyBy(fn ($s) => explode(' ', $s['full_name'])[0]);

    // Competition ranking: the 85s share rank 1, Sara is 3rd of 3.
    expect($byName['Abel']['year']['average'])->toEqualWithDelta(85.0, 0.01)
        ->and($byName['Abel']['year']['rank'])->toBe(1)
        ->and($byName['Hana']['year']['rank'])->toBe(1)
        ->and($byName['Sara']['year']['average'])->toEqualWithDelta(70.0, 0.01)
        ->and($byName['Sara']['year']['rank'])->toBe(3)
        ->and($byName['Sara']['year']['rank_of'])->toBe(3)
        // A missing term never zeroes the year — mean over present terms only.
        ->and($byName['Sara']['terms'])->toHaveCount(1);
});

it('groups quarters into semester sub-averages via the semester tag', function () {
    $branch = makeBranch();
    $year = rosterYear($branch);
    $section = rosterSection($branch);

    // Replace the default semesters with 4 tagged quarters (forceDelete —
    // the (year, sequence) unique index also covers soft-deleted rows).
    $year->terms->each->forceDelete();
    $quarters = collect([1, 2, 3, 4])->map(fn (int $i) => $year->terms()->create([
        'school_id' => $year->school_id,
        'branch_id' => $year->branch_id,
        'name' => "Quarter {$i}",
        'sequence' => $i,
        'is_quarter' => true,
        'semester' => $i <= 2 ? 1 : 2,
    ]));

    $abel = rosterEnroll($branch, $year, $section, 'Abel');
    rosterFreeze($abel, $quarters[0], ['MATH' => 80]);
    rosterFreeze($abel, $quarters[1], ['MATH' => 90]);
    rosterFreeze($abel, $quarters[2], ['MATH' => 60]);
    rosterFreeze($abel, $quarters[3], ['MATH' => 70]);

    Sanctum::actingAs(directorOf($branch));

    $res = $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/academic-years/{$year->id}/roster?section_id={$section->id}")
        ->assertOk()
        ->json();

    expect($res['meta']['has_semester_groups'])->toBeTrue();

    $student = $res['data']['students'][0];
    $semesters = collect($student['semesters'])->keyBy('semester');
    expect($student['terms'])->toHaveCount(4)
        ->and($semesters[1]['average'])->toEqualWithDelta(85.0, 0.01)
        ->and($semesters[2]['average'])->toEqualWithDelta(65.0, 0.01)
        ->and($student['year']['average'])->toEqualWithDelta(75.0, 0.01);
});

// ───────────────────────── marklist analysis ─────────────────────────

it('unpacks a subject with gender split, ranks and default ranges', function () {
    $branch = makeBranch();
    $year = rosterYear($branch);
    $section = rosterSection($branch);
    $term = $year->terms()->first();

    rosterFreeze(rosterEnroll($branch, $year, $section, 'Abel'), $term, ['MATH' => 80, 'ENG' => 40]);
    rosterFreeze(rosterEnroll($branch, $year, $section, 'Hana', 'female'), $term, ['MATH' => 95, 'ENG' => 70]);
    // No MATH line at all — excluded from the subject analysis.
    rosterFreeze(rosterEnroll($branch, $year, $section, 'Sara', 'female'), $term, ['ENG' => 60]);

    Sanctum::actingAs(directorOf($branch));

    $mathId = Subject::where('code', 'MATH')->value('id');
    $gradeId = $section->grade_level_id;

    $res = $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/terms/{$term->id}/marklist-analysis?grade_level_id={$gradeId}&subject_id={$mathId}")
        ->assertOk()
        ->json('data');

    expect($res['subject']['code'])->toBe('MATH')
        ->and($res['students'])->toHaveCount(2)
        ->and($res['summary']['count'])->toBe(2)
        ->and($res['summary']['male'])->toBe(1)
        ->and($res['summary']['female'])->toBe(1)
        ->and($res['summary']['max'])->toEqualWithDelta(95.0, 0.01)
        ->and($res['summary']['min'])->toEqualWithDelta(80.0, 0.01);

    // Ranked best-first.
    expect($res['students'][0]['full_name'])->toContain('Hana')
        ->and($res['students'][0]['rank'])->toBe(1)
        ->and($res['students'][1]['rank'])->toBe(2);

    // Grading-scale bands arrive as ready-made ranges (grade was pinned).
    expect($res['default_ranges'])->not->toBeNull()
        ->and($res['default_ranges'][0])->toHaveKeys(['min', 'max', 'label', 'is_passing']);

    // Overall mode falls back to the term average.
    $overall = $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/terms/{$term->id}/marklist-analysis?grade_level_id={$gradeId}")
        ->assertOk()
        ->json('data');
    expect($overall['subject'])->toBeNull()
        ->and($overall['students'])->toHaveCount(3);
});

<?php

use App\Actions\ComputeTermResultsAction;
use App\Actions\EnrollStudentAction;
use App\Actions\SaveAcademicYearAction;
use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\GeneratedDocument;
use App\Models\GradeLevel;
use App\Models\Section;
use App\Models\SectionHomeroom;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentTermResult;
use App\Models\Subject;
use App\Models\SubjectAssignment;
use App\Models\Term;
use App\Services\Documents\DocumentService;
use App\Services\Reports\YearReportCardService;
use Database\Seeders\GradeLevelSeeder;
use Database\Seeders\GradingScaleSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SubjectSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

/**
 * The report-card slice on top of the rosters hub: school/branch report-card
 * settings (skill checklist, per-page, subject ranks, grading criteria),
 * skill ratings through the conduct lane, per-subject ranks at freeze time,
 * and the batch/yearly report-card document types.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(GradeLevelSeeder::class);
    $this->seed(SubjectSeeder::class);
    $this->seed(GradingScaleSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Storage::fake();
    config()->set('services.cloudflare.account_id', 'acc-test');
    config()->set('services.cloudflare.api_token', 'token-test');
    Http::fake(['api.cloudflare.com/*' => Http::response('%PDF-1.4 fake-pdf', 200)]);
});

function rceYear(Branch $branch): AcademicYear
{
    return (new SaveAcademicYearAction)->execute($branch, ['name' => '2017 E.C.', 'status' => 'active']);
}

function rceSection(Branch $branch, string $name = 'A'): Section
{
    return $branch->sections()->create([
        'school_id' => $branch->school_id,
        'grade_level_id' => GradeLevel::where('code', 'G1')->value('id'),
        'name' => $name,
    ]);
}

function rceEnroll(Branch $branch, AcademicYear $year, Section $section, string $first): StudentEnrollment
{
    $student = $branch->students()->create([
        'school_id' => $branch->school_id,
        'first_name' => $first,
        'father_name' => 'Tesfaye',
        'gender' => 'male',
    ]);

    return app(EnrollStudentAction::class)->execute($student, [
        'academic_year_id' => $year->id,
        'section_id' => $section->id,
        'grade_level_id' => $section->grade_level_id,
    ]);
}

/** Freeze one result row directly — most tests here only READ frozen rows. */
function rceFreeze(StudentEnrollment $enrollment, Term $term, array $subjects, ?int $rank = null, ?int $rankOf = null): StudentTermResult
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

/** One configured skill list, applied at school scope. */
function rceSkillList(): array
{
    return [
        ['key' => 'handwriting', 'group' => 'habits', 'label' => ['en' => 'Handwriting', 'am' => 'የእጅ ጽሑፍ', 'om' => 'Barreeffama harkaa']],
        ['key' => 'polite', 'group' => 'character', 'label' => ['en' => 'Is polite', 'am' => 'ትሑት ነው', 'om' => 'Naamusa qaba']],
    ];
}

// ───────────────────────── settings ─────────────────────────

it('stores report-card settings at school scope and overrides them per branch', function () {
    $branch = makeBranch();
    Sanctum::actingAs(schoolPrincipal($branch));

    $this->patchJson("/api/v1/schools/{$branch->school_id}/settings", [
        'report_card_skills' => rceSkillList(),
        'report_card_per_page' => 4,
        'report_card_subject_ranks' => true,
        'report_card_grading_criteria' => true,
    ])
        ->assertOk()
        ->assertJsonPath('data.report_card_per_page', 4)
        ->assertJsonPath('data.report_card_subject_ranks', true)
        ->assertJsonPath('data.report_card_skills.0.key', 'handwriting');

    // Junk ratings scale/groups are rejected; duplicate keys collapse.
    $this->patchJson("/api/v1/schools/{$branch->school_id}/settings", [
        'report_card_skills' => [
            ['key' => 'x!', 'group' => 'habits', 'label' => ['en' => 'Bad', 'am' => 'Bad', 'om' => 'Bad']],
        ],
    ])->assertUnprocessable();

    // Branch override wins for the branch; clearing it re-inherits.
    $settings = $this->patchJson("/api/v1/branches/{$branch->id}/settings", [
        'report_card_per_page' => 1,
        'report_card_subject_ranks' => false,
    ])->assertOk()->json('data');

    expect($settings['effective']['report_card_per_page'])->toBe(1)
        ->and($settings['effective']['report_card_subject_ranks'])->toBeFalse()
        ->and($settings['effective']['report_card_grading_criteria'])->toBeTrue()
        ->and($settings['school_defaults']['report_card_per_page'])->toBe(4);

    $cleared = $this->patchJson("/api/v1/branches/{$branch->id}/settings", [
        'report_card_per_page' => null,
    ])->assertOk()->json('data');

    expect($cleared['effective']['report_card_per_page'])->toBe(4)
        ->and($cleared['overrides']['report_card_per_page'])->toBeNull();
});

it('carries the branch-effective report-card policy in the roster meta', function () {
    $branch = makeBranch();
    $branch->school->update(['settings' => [
        'report_card_skills' => rceSkillList(),
        'report_card_subject_ranks' => true,
    ]]);

    $year = rceYear($branch);
    $section = rceSection($branch);
    $term = $year->terms()->first();
    rceFreeze(rceEnroll($branch, $year, $section, 'Abel'), $term, ['MATH' => 80]);

    Sanctum::actingAs(directorOf($branch));

    $meta = $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/terms/{$term->id}/roster?section_id={$section->id}")
        ->assertOk()
        ->json('meta.report_card');

    expect($meta['skills'])->toHaveCount(2)
        ->and($meta['skills'][0]['key'])->toBe('handwriting')
        ->and($meta['subject_ranks'])->toBeTrue()
        ->and($meta['per_page'])->toBe(1)
        ->and($meta['grading_criteria'])->toBeFalse();

    // The yearly roster carries the same block.
    $yearMeta = $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/academic-years/{$year->id}/roster?section_id={$section->id}")
        ->assertOk()
        ->json('meta.report_card');

    expect($yearMeta['skills'])->toHaveCount(2);
});

// ───────────────────────── skill ratings ─────────────────────────

it('saves skill ratings through the conduct lane and preserves them on conduct-only saves', function () {
    $branch = makeBranch();
    $branch->school->update(['settings' => ['report_card_skills' => rceSkillList()]]);

    $year = rceYear($branch);
    $section = rceSection($branch);
    $term = $year->terms()->first();
    $enrollment = rceEnroll($branch, $year, $section, 'Abel');
    rceFreeze($enrollment, $term, ['MATH' => 80]);

    Sanctum::actingAs(directorOf($branch));

    // Unknown keys are dropped silently; configured keys stick.
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/terms/{$term->id}/conduct", [
            'rows' => [[
                'student_enrollment_id' => $enrollment->id,
                'conduct' => 'A',
                'skills' => ['handwriting' => 'E', 'polite' => 'VG', 'not_configured' => 'S'],
            ]],
        ])->assertOk();

    $row = StudentTermResult::firstWhere('student_enrollment_id', $enrollment->id);
    expect($row->skills)->toEqualCanonicalizing(['handwriting' => 'E', 'polite' => 'VG'])
        ->and($row->conduct)->toBe('A');

    // A rating outside the fixed scale is rejected.
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/terms/{$term->id}/conduct", [
            'rows' => [[
                'student_enrollment_id' => $enrollment->id,
                'skills' => ['handwriting' => 'A+'],
            ]],
        ])->assertUnprocessable();

    // The inline conduct quick-save (no skills key) must not wipe ratings.
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/terms/{$term->id}/conduct", [
            'rows' => [['student_enrollment_id' => $enrollment->id, 'conduct' => 'B']],
        ])->assertOk();

    expect($row->refresh()->skills)->toEqualCanonicalizing(['handwriting' => 'E', 'polite' => 'VG'])
        ->and($row->conduct)->toBe('B');

    // Ratings survive a recompute, like conduct.
    app(ComputeTermResultsAction::class)->execute($term);
    expect($row->refresh()->skills)->toEqualCanonicalizing(['handwriting' => 'E', 'polite' => 'VG']);
});

// ───────────────────────── per-subject ranks ─────────────────────────

it('stamps per-subject section ranks into the breakdown at freeze time', function () {
    $branch = makeBranch();
    $year = rceYear($branch);
    $section = rceSection($branch);
    $term = $year->terms()->first();

    $abel = rceEnroll($branch, $year, $section, 'Abel');
    $hana = rceEnroll($branch, $year, $section, 'Hana');

    $assignment = SubjectAssignment::create([
        'school_id' => $branch->school_id,
        'branch_id' => $branch->id,
        'academic_year_id' => $year->id,
        'term_id' => $term->id,
        'section_id' => $section->id,
        'subject_id' => Subject::where('code', 'MATH')->value('id'),
        'is_active' => true,
    ]);
    $assessment = $assignment->assessments()->create([
        'type' => 'final_exam', 'name' => 'Final', 'max_score' => 100, 'weight' => 100,
    ]);
    $assessment->results()->create(['student_id' => $abel->student_id, 'score' => 72]);
    $assessment->results()->create(['student_id' => $hana->student_id, 'score' => 91]);

    app(ComputeTermResultsAction::class)->execute($term);

    $abelRow = StudentTermResult::firstWhere('student_enrollment_id', $abel->id);
    $hanaRow = StudentTermResult::firstWhere('student_enrollment_id', $hana->id);

    expect($hanaRow->breakdown[0]['rank'])->toBe(1)
        ->and($hanaRow->breakdown[0]['rank_of'])->toBe(2)
        ->and($abelRow->breakdown[0]['rank'])->toBe(2)
        ->and($abelRow->breakdown[0]['rank_of'])->toBe(2);
});

// ───────────────────────── batch + yearly documents ─────────────────────────

it('renders the semester batch PDF for a selection and blocks foreign students', function () {
    $branch = makeBranch();
    // 4-per-page exercises the densest template path (2×2 wallet grid).
    $branch->school->update(['settings' => ['report_card_per_page' => 4]]);
    $year = rceYear($branch);
    $section = rceSection($branch);
    $term = $year->terms()->first();

    $abel = rceEnroll($branch, $year, $section, 'Abel');
    $hana = rceEnroll($branch, $year, $section, 'Hana');
    rceFreeze($abel, $term, ['MATH' => 80], rank: 1, rankOf: 2);
    rceFreeze($hana, $term, ['MATH' => 60], rank: 2, rankOf: 2);

    Sanctum::actingAs(directorOf($branch));

    $doc = $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/documents', [
            'type' => 'report_card_batch',
            'params' => ['term_id' => $term->id, 'student_ids' => [$abel->student_id, $hana->student_id]],
        ])->assertOk()->json('data');

    expect($doc['status'])->toBe('ready');
    $stored = GeneratedDocument::find($doc['id']);
    expect(Storage::exists($stored->disk_path))->toBeTrue();

    // A student of ANOTHER branch poisons the whole batch.
    $other = makeBranch('AA-0002');
    $otherYear = rceYear($other);
    $foreign = rceEnroll($other, $otherYear, rceSection($other), 'Kebede');
    rceFreeze($foreign, $otherYear->terms()->first(), ['MATH' => 50]);

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/documents', [
            'type' => 'report_card_batch',
            'params' => ['term_id' => $term->id, 'student_ids' => [$abel->student_id, $foreign->student_id]],
        ])->assertForbidden();
});

it('lets a homeroom teacher batch ONLY their own section', function () {
    $branch = makeBranch();
    $year = rceYear($branch);
    $own = rceSection($branch, 'A');
    $other = rceSection($branch, 'B');
    $term = $year->terms()->first();

    $mine = rceEnroll($branch, $year, $own, 'Abel');
    $notMine = rceEnroll($branch, $year, $other, 'Hana');
    rceFreeze($mine, $term, ['MATH' => 80]);
    rceFreeze($notMine, $term, ['MATH' => 70]);

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

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/documents', [
            'type' => 'report_card_batch',
            'params' => ['term_id' => $term->id, 'student_ids' => [$mine->student_id]],
        ])->assertOk();

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/documents', [
            'type' => 'report_card_batch',
            'params' => ['term_id' => $term->id, 'student_ids' => [$mine->student_id, $notMine->student_id]],
        ])->assertForbidden();
});

it('renders the yearly card on both sides with year averages, skills and quarter groups', function () {
    $branch = makeBranch();
    $branch->school->update(['settings' => ['report_card_skills' => rceSkillList()]]);

    $year = rceYear($branch);
    $section = rceSection($branch);

    // Four tagged quarters instead of the default semesters.
    $year->terms->each->forceDelete();
    $quarters = collect([1, 2, 3, 4])->map(fn (int $i) => $year->terms()->create([
        'school_id' => $year->school_id,
        'branch_id' => $year->branch_id,
        'name' => "Quarter {$i}",
        'sequence' => $i,
        'is_quarter' => true,
        'semester' => $i <= 2 ? 1 : 2,
    ]));

    $abel = rceEnroll($branch, $year, $section, 'Abel');
    rceFreeze($abel, $quarters[0], ['MATH' => 80, 'ENG' => 70]);
    rceFreeze($abel, $quarters[1], ['MATH' => 90, 'ENG' => 60]);

    // Ratings on the first quarter power the skills panel.
    $row = StudentTermResult::firstWhere('term_id', $quarters[0]->id);
    $row->update(['skills' => ['handwriting' => 'E']]);

    Sanctum::actingAs(directorOf($branch));

    foreach (['inside', 'cover', 'both'] as $side) {
        $doc = $this->withHeaders(branchContext($branch))
            ->postJson('/api/v1/documents', [
                'type' => 'year_report_card',
                'subject_id' => $abel->student_id,
                'params' => ['academic_year_id' => $year->id, 'side' => $side],
            ])->assertOk()->json('data');

        expect($doc['status'])->toBe('ready');
    }

    // Each side (and 'both') is its own document — separate cache entries.
    expect(GeneratedDocument::where('type', 'year_report_card')->count())->toBe(3);

    // Batch variant renders the same sheet for a selection.
    $batch = $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/documents', [
            'type' => 'year_report_card_batch',
            'params' => [
                'academic_year_id' => $year->id,
                'side' => 'inside',
                'student_ids' => [$abel->student_id],
            ],
        ])->assertOk()->json('data');

    expect($batch['status'])->toBe('ready');

    // An invalid side never reaches the renderer.
    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/documents', [
            'type' => 'year_report_card',
            'subject_id' => $abel->student_id,
            'params' => ['academic_year_id' => $year->id, 'side' => 'back'],
        ])->assertUnprocessable();
});

it('never exposes comments, skill ratings or enrollment ids on the PUBLIC roster', function () {
    $branch = makeBranch();
    $branch->school->update(['settings' => ['report_card_skills' => rceSkillList()]]);
    $year = rceYear($branch);
    $section = rceSection($branch);
    $term = $year->terms()->first();

    $row = rceFreeze(rceEnroll($branch, $year, $section, 'Abel'), $term, ['MATH' => 80]);
    $row->update(['comment' => 'Struggles with attention', 'skills' => ['handwriting' => 'NI']]);

    $document = GeneratedDocument::create([
        'school_id' => $branch->school_id,
        'branch_id' => $branch->id,
        'type' => 'roster',
        'params' => ['scope' => 'term', 'term_id' => $term->id, 'section_id' => $section->id],
        'version_hash' => 'test',
        'status' => GeneratedDocument::STATUS_READY,
    ]);

    // No auth — the QR lane. Marks stay; the working data must not.
    $payload = $this->getJson("/api/v1/public/rosters/{$document->public_token}")
        ->assertOk()
        ->json('data');

    $first = $payload['data']['rows'][0];
    expect($first['average'])->toEqual(80)
        ->and($first)->not->toHaveKeys(['comment', 'skills', 'student_enrollment_id'])
        ->and($payload['meta'])->not->toHaveKey('report_card');

    // The YEAR scope strips its term lines the same way.
    $yearDoc = GeneratedDocument::create([
        'school_id' => $branch->school_id,
        'branch_id' => $branch->id,
        'type' => 'roster',
        'params' => ['scope' => 'year', 'academic_year_id' => $year->id, 'section_id' => $section->id],
        'version_hash' => 'test',
        'status' => GeneratedDocument::STATUS_READY,
    ]);

    $yearPayload = $this->getJson("/api/v1/public/rosters/{$yearDoc->public_token}")
        ->assertOk()
        ->json('data');

    expect($yearPayload['data']['students'][0]['terms'][0])
        ->not->toHaveKeys(['comment', 'skills', 'student_enrollment_id']);
});

it('backfills per-subject ranks on cards whose frozen rows predate the rank release', function () {
    $branch = makeBranch();
    $branch->school->update(['settings' => ['report_card_subject_ranks' => true]]);
    $year = rceYear($branch);
    $section = rceSection($branch);
    $term = $year->terms()->first();

    // rceFreeze writes breakdown lines WITHOUT rank — old-history rows.
    $abel = rceEnroll($branch, $year, $section, 'Abel');
    $hana = rceEnroll($branch, $year, $section, 'Hana');
    rceFreeze($abel, $term, ['MATH' => 72, 'ENG' => 90]);
    rceFreeze($hana, $term, ['MATH' => 91]);

    $payload = DocumentService::builder('report_card')
        ->payload(Student::find($abel->student_id), ['term_id' => $term->id]);

    expect($payload['show_subject_ranks'])->toBeTrue();

    $subjects = collect($payload['card']['subjects'])->keyBy('code');
    // MATH: Hana 91 > Abel 72 → Abel 2 of 2. ENG: only Abel → 1 of 1.
    expect($subjects['MATH']['rank'])->toBe(2)
        ->and($subjects['MATH']['rank_of'])->toBe(2)
        ->and($subjects['ENG']['rank'])->toBe(1)
        ->and($subjects['ENG']['rank_of'])->toBe(1);

    // The batch fills the same way.
    $batch = DocumentService::builder('report_card_batch')
        ->payload(null, ['term_id' => $term->id, 'student_ids' => [$hana->student_id]]);
    $hanaMath = collect($batch['cards'][0]['subjects'])->firstWhere('code', 'MATH');
    expect($hanaMath['rank'])->toBe(1)->and($hanaMath['rank_of'])->toBe(2);

    // A rank the freeze already stamped is never overwritten.
    $row = StudentTermResult::firstWhere('student_enrollment_id', $abel->id);
    $breakdown = $row->breakdown;
    $breakdown[0]['rank'] = 9;
    $breakdown[0]['rank_of'] = 40;
    $row->update(['breakdown' => $breakdown]);

    $again = DocumentService::builder('report_card')
        ->payload(Student::find($abel->student_id), ['term_id' => $term->id]);
    $frozen = collect($again['card']['subjects'])->firstWhere('code', $breakdown[0]['code']);
    expect($frozen['rank'])->toBe(9)->and($frozen['rank_of'])->toBe(40);
});

it('builds the yearly card data: per-subject year averages and section year ranks', function () {
    $branch = makeBranch();
    $year = rceYear($branch);
    $section = rceSection($branch);
    [$sem1, $sem2] = $year->terms()->orderBy('sequence')->get();

    $abel = rceEnroll($branch, $year, $section, 'Abel');
    $hana = rceEnroll($branch, $year, $section, 'Hana');
    rceFreeze($abel, $sem1, ['MATH' => 80]);
    rceFreeze($abel, $sem2, ['MATH' => 90]);
    rceFreeze($hana, $sem1, ['MATH' => 95]);
    rceFreeze($hana, $sem2, ['MATH' => 99]);

    $built = app(YearReportCardService::class)
        ->cards($year->refresh(), [$abel->student_id]);

    expect($built['terms'])->toHaveCount(2)
        ->and($built['cards'])->toHaveCount(1);

    $card = $built['cards'][0];
    $math = $card['subjects'][0];

    expect($math['year_avg'])->toEqualWithDelta(85.0, 0.01)
        ->and($math['per_term'][$sem1->id]['total'])->toEqualWithDelta(80.0, 0.01)
        // Year rank spans the WHOLE section cohort, not just the requested set.
        ->and($card['year']['average'])->toEqualWithDelta(85.0, 0.01)
        ->and($card['year']['rank'])->toBe(2)
        ->and($card['year']['rank_of'])->toBe(2);
});

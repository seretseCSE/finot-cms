<?php

use App\Actions\EnrollStudentAction;
use App\Actions\SaveAcademicYearAction;
use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\GradeLevel;
use App\Models\ParentProfile;
use App\Models\Section;
use App\Models\SectionHomeroom;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentGuardian;
use App\Models\StudentPromotion;
use App\Models\StudentTermResult;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Database\Seeders\GradeLevelSeeder;
use Database\Seeders\GradingScaleSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SubjectSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(GradeLevelSeeder::class);
    $this->seed(SubjectSeeder::class);
    $this->seed(GradingScaleSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function trxYear(Branch $branch, string $name = '2017 E.C.'): AcademicYear
{
    return (new SaveAcademicYearAction)->execute($branch, ['name' => $name, 'status' => 'active']);
}

function trxSection(Branch $branch, string $name = 'A', string $gradeCode = 'G1'): Section
{
    return $branch->sections()->create([
        'school_id' => $branch->school_id,
        'grade_level_id' => GradeLevel::where('code', $gradeCode)->value('id'),
        'name' => $name,
    ]);
}

function trxEnroll(Branch $branch, AcademicYear $year, Section $section, string $first, string $gender = 'male', ?Student $student = null): StudentEnrollment
{
    $student ??= $branch->students()->create([
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

/** Freeze one result row directly (transcript endpoints only READ them). */
function trxFreeze(StudentEnrollment $enrollment, Term $term, float $math): StudentTermResult
{
    return StudentTermResult::create([
        'student_id' => $enrollment->student_id,
        'student_enrollment_id' => $enrollment->id,
        'term_id' => $term->id,
        'school_id' => $enrollment->school_id,
        'branch_id' => $enrollment->branch_id,
        'academic_year_id' => $enrollment->academic_year_id,
        'section_id' => $enrollment->section_id,
        'grade_level_id' => $enrollment->grade_level_id,
        'total' => $math,
        'average' => $math,
        'subject_count' => 1,
        'breakdown' => [[
            'subject_id' => Subject::where('code', 'MATH')->value('id'),
            'code' => 'MATH', 'name' => 'Mathematics',
            'total' => $math, 'letter' => null, 'band_label' => null,
            'is_passing' => $math >= 50,
        ]],
        'grading' => null,
        'computed_at' => now(),
    ]);
}

// ───────────────────────── register ─────────────────────────

it('lists the register with readiness counts across all years', function () {
    $branch = makeBranch();
    $year1 = trxYear($branch, '2016 E.C.');
    // Activating year2 completes year1 (one operating year per branch).
    $year2 = trxYear($branch, '2017 E.C.');
    $sec1 = trxSection($branch, 'A');
    $sec2 = trxSection($branch, 'B');

    $abelY1 = trxEnroll($branch, $year1, $sec1, 'Abel');
    $abel = $abelY1->student;
    $abelY2 = trxEnroll($branch, $year2, $sec2, 'Abel', 'male', $abel);
    $hanaY2 = trxEnroll($branch, $year2, $sec2, 'Hana', 'female');

    // Abel frozen in both years (3 terms total); Hana never frozen.
    trxFreeze($abelY1, $year1->terms()->orderBy('sequence')->first(), 80);
    trxFreeze($abelY1, $year1->terms()->orderBy('sequence')->skip(1)->first(), 90);
    trxFreeze($abelY2, $year2->terms()->first(), 85);

    Sanctum::actingAs(directorOf($branch));

    $res = $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/academic-years/{$year2->id}/transcript-register")
        ->assertOk()
        ->json();

    expect($res['meta']['students'])->toBe(2)
        ->and($res['meta']['year']['id'])->toBe($year2->id);

    $rows = collect($res['data'])->keyBy(fn ($r) => explode(' ', $r['full_name'])[0]);
    expect($rows['Abel']['years_count'])->toBe(2)
        ->and($rows['Abel']['terms_count'])->toBe(3)
        ->and($rows['Abel']['last_computed_at'])->not->toBeNull()
        ->and($rows['Hana']['years_count'])->toBe(0)
        ->and($rows['Hana']['terms_count'])->toBe(0)
        ->and($rows['Hana']['last_computed_at'])->toBeNull();
});

it('narrows the register by grade and section', function () {
    $branch = makeBranch();
    $year = trxYear($branch);
    $g1 = trxSection($branch, 'A', 'G1');
    $g2 = trxSection($branch, 'B', 'G2');
    trxEnroll($branch, $year, $g1, 'Abel');
    trxEnroll($branch, $year, $g2, 'Hana', 'female');

    Sanctum::actingAs(directorOf($branch));

    $gradeId = GradeLevel::where('code', 'G1')->value('id');
    $byGrade = $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/academic-years/{$year->id}/transcript-register?grade_level_id={$gradeId}")
        ->assertOk()->json('data');
    expect($byGrade)->toHaveCount(1)
        ->and($byGrade[0]['full_name'])->toContain('Abel');

    $bySection = $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/academic-years/{$year->id}/transcript-register?section_id={$g2->id}")
        ->assertOk()->json('data');
    expect($bySection)->toHaveCount(1)
        ->and($bySection[0]['full_name'])->toContain('Hana');
});

it('scopes homeroom teachers to their own sections on the register', function () {
    $branch = makeBranch();
    $year = trxYear($branch);
    $own = trxSection($branch, 'A');
    $other = trxSection($branch, 'B');
    trxEnroll($branch, $year, $own, 'Abel');
    trxEnroll($branch, $year, $other, 'Hana', 'female');

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
        ->getJson("/api/v1/academic-years/{$year->id}/transcript-register")
        ->assertOk()->json('data');
    expect($rows)->toHaveCount(1)
        ->and($rows[0]['section_id'])->toBe($own->id);

    // No homeroom → empty register, not 403.
    Sanctum::actingAs(memberOf($branch, Role::Teacher));
    $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/academic-years/{$year->id}/transcript-register")
        ->assertOk()
        ->assertJsonCount(0, 'data');

    // No grading permission at all → denied.
    Sanctum::actingAs(User::factory()->create());
    $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/academic-years/{$year->id}/transcript-register")
        ->assertForbidden();
});

it('denies the register and batch across the tenant boundary', function () {
    $branchA = makeBranch();
    $branchB = makeBranch('AA-0002');
    $year = trxYear($branchA);
    $abel = trxEnroll($branchA, $year, trxSection($branchA), 'Abel');

    Sanctum::actingAs(directorOf($branchB));

    $this->withHeaders(branchContext($branchB))
        ->getJson("/api/v1/academic-years/{$year->id}/transcript-register")
        ->assertForbidden();
    $this->withHeaders(branchContext($branchB))
        ->getJson("/api/v1/reports/transcripts?academic_year_id={$year->id}&student_ids[]={$abel->student_id}")
        ->assertForbidden();
});

// ───────────────────────── batch ─────────────────────────

it('returns batch transcripts in request order matching the single endpoint', function () {
    $branch = makeBranch();
    $year = trxYear($branch);
    $section = trxSection($branch);
    $abel = trxEnroll($branch, $year, $section, 'Abel');
    $hana = trxEnroll($branch, $year, $section, 'Hana', 'female');
    $sara = trxEnroll($branch, $year, $section, 'Sara', 'female');
    trxFreeze($abel, $year->terms()->first(), 80);
    trxFreeze($hana, $year->terms()->first(), 90);
    // Sara has nothing frozen — still gets a transcript shell.

    Sanctum::actingAs(directorOf($branch));

    $ids = [$hana->student_id, $abel->student_id, $sara->student_id];
    $params = "academic_year_id={$year->id}&".collect($ids)->map(fn ($id) => "student_ids[]={$id}")->implode('&');

    $batch = $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/reports/transcripts?{$params}")
        ->assertOk()
        ->json('data');

    expect($batch)->toHaveCount(3)
        ->and($batch[0]['student']['id'])->toBe($hana->student_id)
        ->and($batch[1]['student']['id'])->toBe($abel->student_id)
        ->and($batch[2]['student']['id'])->toBe($sara->student_id)
        ->and($batch[2]['years'])->toBeEmpty();

    // Byte-for-byte the same as the single-student endpoint (minus timestamps).
    $single = $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/reports/students/{$abel->student_id}/transcript")
        ->assertOk()
        ->json('data');
    expect(collect($batch[1])->except('generated_at')->all())
        ->toEqual(collect($single)->except('generated_at')->all());
});

it('validates batch input', function () {
    $branch = makeBranch();
    $year = trxYear($branch);
    Sanctum::actingAs(directorOf($branch));

    // Missing year.
    $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/reports/transcripts?student_ids[]=1')
        ->assertUnprocessable();

    // Empty ids.
    $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/reports/transcripts?academic_year_id={$year->id}")
        ->assertUnprocessable();

    // Over the 60-id cap.
    $params = collect(range(1, 61))->map(fn ($id) => "student_ids[]={$id}")->implode('&');
    $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/reports/transcripts?academic_year_id={$year->id}&{$params}")
        ->assertUnprocessable();
});

it('denies the whole batch when one student is out of scope', function () {
    $branchA = makeBranch();
    $branchB = makeBranch('AA-0002');
    $yearA = trxYear($branchA);
    $yearB = trxYear($branchB);
    $sectionA = trxSection($branchA);
    $inScope = trxEnroll($branchA, $yearA, $sectionA, 'Abel');
    $foreign = trxEnroll($branchB, $yearB, trxSection($branchB), 'Hana', 'female');

    Sanctum::actingAs(directorOf($branchA));

    $this->withHeaders(branchContext($branchA))
        ->getJson("/api/v1/reports/transcripts?academic_year_id={$yearA->id}&student_ids[]={$inScope->student_id}&student_ids[]={$foreign->student_id}")
        ->assertForbidden();

    // Homeroom teacher including a student outside their homeroom → 403.
    $other = trxSection($branchA, 'B');
    $outside = trxEnroll($branchA, $yearA, $other, 'Sara', 'female');

    $teacher = memberOf($branchA, Role::Teacher);
    $employee = Employee::create([
        'user_id' => $teacher->id,
        'school_id' => $branchA->school_id,
        'branch_id' => $branchA->id,
        'first_name' => 'Alemu',
        'father_name' => 'Bekele',
        'gender' => 'male',
    ]);
    SectionHomeroom::create([
        'section_id' => $sectionA->id, 'academic_year_id' => $yearA->id, 'employee_id' => $employee->id,
    ]);

    Sanctum::actingAs($teacher);
    $this->withHeaders(branchContext($branchA))
        ->getJson("/api/v1/reports/transcripts?academic_year_id={$yearA->id}&student_ids[]={$inScope->student_id}&student_ids[]={$outside->student_id}")
        ->assertForbidden();

    // …but their own homeroom students work.
    $this->withHeaders(branchContext($branchA))
        ->getJson("/api/v1/reports/transcripts?academic_year_id={$yearA->id}&student_ids[]={$inScope->student_id}")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

// ───────────────────────── year narrowing (partial transcripts) ─────────────────────────

it('narrows the transcript to selected years and stamps it partial', function () {
    $branch = makeBranch();
    $year1 = trxYear($branch, '2016 E.C.');
    $year2 = trxYear($branch, '2017 E.C.');
    $sec1 = trxSection($branch, 'A', 'G1');
    $sec2 = trxSection($branch, 'B', 'G2');

    $abelY1 = trxEnroll($branch, $year1, $sec1, 'Abel');
    $abelY2 = trxEnroll($branch, $year2, $sec2, 'Abel', 'male', $abelY1->student);
    trxFreeze($abelY1, $year1->terms()->orderBy('sequence')->first(), 80);
    trxFreeze($abelY2, $year2->terms()->orderBy('sequence')->first(), 90);

    Sanctum::actingAs(directorOf($branch));

    // Default: the COMPLETE record — both years, not partial.
    $full = $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/reports/students/{$abelY1->student_id}/transcript")
        ->assertOk()
        ->json('data');
    expect($full['years'])->toHaveCount(2)
        ->and($full['is_partial'])->toBeFalse()
        ->and($full['available_years'])->toHaveCount(2)
        ->and($full['issued_by']['school_name'])->toBe('Unity Academy');

    // Narrowed to one year: partial, but the picker still sees both years.
    $partial = $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/reports/students/{$abelY1->student_id}/transcript?academic_year_ids[]={$year1->id}")
        ->assertOk()
        ->json('data');
    expect($partial['years'])->toHaveCount(1)
        ->and($partial['years'][0]['academic_year_id'])->toBe($year1->id)
        ->and($partial['is_partial'])->toBeTrue()
        ->and($partial['available_years'])->toHaveCount(2);

    // The batch endpoint accepts the same narrowing for every sheet.
    $batch = $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/reports/transcripts?academic_year_id={$year2->id}&student_ids[]={$abelY1->student_id}&academic_year_ids[]={$year1->id}")
        ->assertOk()
        ->json('data');
    expect($batch[0]['years'])->toHaveCount(1)
        ->and($batch[0]['is_partial'])->toBeTrue();
});

it('records the year-end outcome with the enrollment-status fallback', function () {
    $branch = makeBranch();
    $year = trxYear($branch);
    $section = trxSection($branch, 'A', 'G1');
    $abel = trxEnroll($branch, $year, $section, 'Abel');
    trxFreeze($abel, $year->terms()->orderBy('sequence')->first(), 80);

    Sanctum::actingAs(directorOf($branch));

    // Live enrollment, no board decision → honestly no outcome yet.
    $data = $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/reports/students/{$abel->student_id}/transcript")
        ->assertOk()->json('data');
    expect($data['years'][0]['outcome'])->toBeNull();

    // A terminal enrollment status fills the Status row even without a
    // promotion-board row (years closed before the board existed).
    $abel->update(['status' => 'promoted']);
    $data = $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/reports/students/{$abel->student_id}/transcript")
        ->assertOk()->json('data');
    expect($data['years'][0]['outcome']['decision'])->toBe('promoted')
        ->and($data['years'][0]['outcome']['label'])->toBe('Promoted');

    // A board decision wins over the fallback and names the target grade.
    $toGrade = GradeLevel::where('code', 'G2')->first();
    StudentPromotion::create([
        'student_id' => $abel->student_id,
        'academic_year_id' => $year->id,
        'from_enrollment_id' => $abel->id,
        'from_grade_level_id' => $abel->grade_level_id,
        'to_grade_level_id' => $toGrade->id,
        'from_branch_id' => $branch->id,
        'decision' => 'promoted',
        'decided_at' => now(),
    ]);
    $data = $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/reports/students/{$abel->student_id}/transcript")
        ->assertOk()->json('data');
    expect($data['years'][0]['outcome']['to_grade_level'])->toBe($toGrade->name);
});

it('builds the masthead contact line with the branch → school → principal chain', function () {
    $branch = makeBranch();
    $year = trxYear($branch);
    $abel = trxEnroll($branch, $year, trxSection($branch), 'Abel');
    trxFreeze($abel, $year->terms()->orderBy('sequence')->first(), 80);

    Sanctum::actingAs(directorOf($branch));
    $url = "/api/v1/reports/students/{$abel->student_id}/transcript";

    // Nothing filled anywhere and no principal → empty contact line.
    $issued = $this->withHeaders(branchContext($branch))->getJson($url)->json('data.issued_by');
    expect($issued['phone'])->toBeNull()->and($issued['address'])->toBeNull();

    // School-level values fill the gaps…
    $branch->school->update(['phone' => '0911000000', 'address' => 'Bole, Addis Ababa']);
    $issued = $this->withHeaders(branchContext($branch))->getJson($url)->json('data.issued_by');
    expect($issued['phone'])->toBe('0911000000')
        ->and($issued['address'])->toBe('Bole, Addis Ababa');

    // …but the branch's own values always win.
    $branch->update(['phone' => '0922000000', 'sub_city' => 'Yeka', 'city' => 'Addis Ababa']);
    $issued = $this->withHeaders(branchContext($branch))->getJson($url)->json('data.issued_by');
    expect($issued['phone'])->toBe('0922000000')
        ->and($issued['address'])->toBe('Yeka, Addis Ababa');
});

it('serves the public transcript page behind the QR and kills it on revoke', function () {
    Storage::fake();
    config()->set('services.cloudflare.account_id', 'acc-test');
    config()->set('services.cloudflare.api_token', 'token-test');
    Http::fake(['api.cloudflare.com/*' => Http::response('%PDF-1.4 fake-pdf', 200)]);

    $branch = makeBranch();
    $year = trxYear($branch);
    $abel = trxEnroll($branch, $year, trxSection($branch), 'Abel');
    trxFreeze($abel, $year->terms()->orderBy('sequence')->first(), 80);

    Sanctum::actingAs(directorOf($branch));

    // Issue the official document (what the print page does on load).
    $doc = $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/documents', ['type' => 'transcript', 'subject_id' => $abel->student_id])
        ->assertOk()->json('data');

    // The QR target renders the FULL article without auth.
    $public = $this->getJson("/api/v1/public/transcripts/{$doc['public_token']}")
        ->assertOk()->json('data');
    expect($public['transcript']['student']['id'])->toBe($abel->student_id)
        ->and($public['transcript']['years'])->toHaveCount(1)
        ->and($public['download_url'])->not->toBeNull();

    // Revoking kills the page (and never leaks whether marks existed).
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/documents/{$doc['id']}/revoke")->assertOk();
    $this->getJson("/api/v1/public/transcripts/{$doc['public_token']}")->assertStatus(410);

    // Unknown tokens are a plain 404.
    $this->getJson('/api/v1/public/transcripts/not-a-token')->assertNotFound();
});

it('lets the family print the official transcript PDF (relationship lane)', function () {
    Storage::fake();
    config()->set('services.cloudflare.account_id', 'acc-test');
    config()->set('services.cloudflare.api_token', 'token-test');
    Http::fake(['api.cloudflare.com/*' => Http::response('%PDF-1.4 fake-pdf', 200)]);

    $branch = makeBranch();
    $year = trxYear($branch);
    $abel = trxEnroll($branch, $year, trxSection($branch), 'Abel');
    trxFreeze($abel, $year->terms()->orderBy('sequence')->first(), 80);

    // Guardian WITH grades access may issue the PDF…
    $guardianUser = User::factory()->create();
    $parent = ParentProfile::create(['user_id' => $guardianUser->id, 'first_name' => 'Guardian', 'father_name' => 'Tesfaye']);
    $link = StudentGuardian::create([
        'student_id' => $abel->student_id, 'parent_id' => $parent->id,
        'relationship' => 'father', 'is_active' => true, 'can_view_grades' => true,
    ]);

    Sanctum::actingAs($guardianUser);
    $this->postJson('/api/v1/documents', ['type' => 'transcript', 'subject_id' => $abel->student_id])
        ->assertOk();

    // …but not once the grades gate is off (per-link permission, ADR-012).
    $link->update(['can_view_grades' => false]);
    $this->postJson('/api/v1/documents', ['type' => 'transcript', 'subject_id' => $abel->student_id])
        ->assertForbidden();

    // A stranger gets nothing.
    Sanctum::actingAs(User::factory()->create());
    $this->postJson('/api/v1/documents', ['type' => 'transcript', 'subject_id' => $abel->student_id])
        ->assertForbidden();
});

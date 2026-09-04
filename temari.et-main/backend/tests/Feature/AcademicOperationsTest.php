<?php

use App\Actions\ComputeTermResultsAction;
use App\Actions\EnrollStudentAction;
use App\Actions\SaveAcademicYearAction;
use App\Enums\EnrollmentStatus;
use App\Enums\FeeType;
use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\FeeStructure;
use App\Models\GradeLevel;
use App\Models\Invoice;
use App\Models\Room;
use App\Models\School;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentPromotion;
use App\Models\StudentTermResult;
use App\Models\Subject;
use App\Models\SubjectAssignment;
use App\Models\User;
use Database\Seeders\GradeLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SubjectSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(GradeLevelSeeder::class);
    $this->seed(SubjectSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function opsYear(Branch $branch, string $name = '2017 E.C.', string $status = 'active'): AcademicYear
{
    return (new SaveAcademicYearAction())->execute($branch, ['name' => $name, 'status' => $status]);
}

function opsSection(Branch $branch, string $gradeCode = 'G1', string $name = 'A', ?int $capacity = null): Section
{
    return $branch->sections()->create([
        'school_id' => $branch->school_id,
        'grade_level_id' => GradeLevel::where('code', $gradeCode)->value('id'),
        'name' => $name,
        'capacity' => $capacity,
    ]);
}

function opsStudent(Branch $branch, string $first = 'Abel', string $gender = 'male'): Student
{
    return $branch->students()->create([
        'school_id' => $branch->school_id,
        'first_name' => $first,
        'father_name' => 'Tesfaye',
        'gender' => $gender,
    ]);
}

function opsEnroll(Student $student, AcademicYear $year, ?Section $section = null, ?int $gradeLevelId = null): StudentEnrollment
{
    return app(EnrollStudentAction::class)->execute($student, [
        'academic_year_id' => $year->id,
        'section_id' => $section?->id,
        'grade_level_id' => $gradeLevelId ?? $section?->grade_level_id,
    ]);
}

function opsRegistrationFee(Branch $branch, AcademicYear $year, float $amount = 500): FeeStructure
{
    return FeeStructure::create([
        'school_id' => $branch->school_id,
        'branch_id' => $branch->id,
        'academic_year_id' => $year->id,
        'name' => 'Registration',
        'type' => FeeType::Registration,
        'amount' => $amount,
    ]);
}

// ───────────────────────── registration-fee gate ─────────────────────────

it('creates enrollments as pending when a registration fee applies and activates on payment', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));
    $year = opsYear($branch);
    $section = opsSection($branch);
    opsRegistrationFee($branch, $year);

    $student = opsStudent($branch);
    $enrollment = opsEnroll($student, $year, $section);

    expect($enrollment->status)->toBe(EnrollmentStatus::Pending);

    $invoice = Invoice::firstWhere('student_id', $student->id);
    expect((float) $invoice->amount)->toBe(500.0);

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/invoices/{$invoice->id}/payments", [
            'amount' => 500, 'method' => 'cash',
        ])->assertCreated();

    expect($enrollment->refresh()->status)->toBe(EnrollmentStatus::Active);
});

it('activates immediately when no registration fee is configured', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));
    $year = opsYear($branch);
    $section = opsSection($branch);

    $enrollment = opsEnroll(opsStudent($branch), $year, $section);

    expect($enrollment->status)->toBe(EnrollmentStatus::Active);
});

it('lets staff provisionally activate under the soft gate but not the hard gate', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));
    $year = opsYear($branch);
    $section = opsSection($branch);
    opsRegistrationFee($branch, $year);

    $enrollment = opsEnroll(opsStudent($branch), $year, $section);
    expect($enrollment->status)->toBe(EnrollmentStatus::Pending);

    // Hard gate: refuse.
    $branch->school->update(['settings' => ['registration_gate' => 'hard']]);
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/enrollments/{$enrollment->id}/activate")
        ->assertStatus(422);

    // Soft gate (default): allowed, logged as provisional.
    $branch->school->update(['settings' => ['registration_gate' => 'soft']]);
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/enrollments/{$enrollment->id}/activate")
        ->assertOk()
        ->assertJsonPath('data.status', 'active');
});

it('granting a scholarship the registration invoice lifts the gate', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));
    $year = opsYear($branch);
    $section = opsSection($branch);
    opsRegistrationFee($branch, $year);

    $enrollment = opsEnroll(opsStudent($branch), $year, $section);
    $invoice = Invoice::firstWhere('student_id', $enrollment->student_id);

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/invoices/{$invoice->id}/discount", [
            'discount_type' => 'full_scholarship', 'scholarship_reason' => 'Scholarship',
        ])->assertOk();

    expect($enrollment->refresh()->status)->toBe(EnrollmentStatus::Active);
});

it('lets a branch override the school policy (gate + pass mark)', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));
    $year = opsYear($branch);
    $section = opsSection($branch);
    opsRegistrationFee($branch, $year);

    // Branch overrides the (soft) school default with a HARD gate + 60 mark.
    $this->withHeaders(branchContext($branch))
        ->patchJson("/api/v1/branches/{$branch->id}/settings", [
            'registration_gate' => 'hard',
            'promotion_threshold' => 60,
        ])
        ->assertOk()
        ->assertJsonPath('data.effective.registration_gate', 'hard')
        ->assertJsonPath('data.overrides.promotion_threshold', 60)
        ->assertJsonPath('data.school_defaults.registration_gate', 'soft');

    $enrollment = opsEnroll(opsStudent($branch), $year, $section);
    expect($enrollment->status)->toBe(EnrollmentStatus::Pending);

    // Hard branch gate refuses provisional activation despite the soft school.
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/enrollments/{$enrollment->id}/activate")
        ->assertStatus(422);

    // The promotion board reads the BRANCH threshold.
    $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/promotions/board?academic_year_id={$year->id}")
        ->assertOk()
        ->assertJsonPath('meta.threshold', 60);

    // Clearing the override falls back to the school default.
    $this->withHeaders(branchContext($branch))
        ->patchJson("/api/v1/branches/{$branch->id}/settings", ['registration_gate' => null])
        ->assertOk()
        ->assertJsonPath('data.effective.registration_gate', 'soft');
});

// ───────────────────────── term results ─────────────────────────

function opsContinuousAssessment(Branch $branch, Section $section, AcademicYear $year, array $studentScores): void
{
    $term = $year->terms()->first();
    $subject = Subject::where('code', 'MATH')->first();

    $assignment = SubjectAssignment::create([
        'school_id' => $branch->school_id,
        'branch_id' => $branch->id,
        'academic_year_id' => $year->id,
        'section_id' => $section->id,
        'subject_id' => $subject->id,
        'term_id' => $term->id,
        'periods_per_week' => 5,
    ]);

    $assessment = $assignment->assessments()->create([
        'school_id' => $branch->school_id,
        'branch_id' => $branch->id,
        'term_id' => $term->id,
        'type' => 'final',
        'name' => 'Final exam',
        'max_score' => 100,
        'weight' => 100,
    ]);

    foreach ($studentScores as $studentId => $score) {
        $assessment->results()->create([
            'student_id' => $studentId,
            'score' => $score,
            'recorded_by' => null,
        ]);
    }
}

it('computes term results with averages and section ranks', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));
    $year = opsYear($branch);
    $section = opsSection($branch);

    $abel = opsStudent($branch, 'Abel');
    $marta = opsStudent($branch, 'Marta', 'female');
    $e1 = opsEnroll($abel, $year, $section);
    $e2 = opsEnroll($marta, $year, $section);

    opsContinuousAssessment($branch, $section, $year, [$abel->id => 70, $marta->id => 90]);

    $term = $year->terms()->first();
    app(ComputeTermResultsAction::class)->execute($term);

    $r1 = StudentTermResult::firstWhere('student_enrollment_id', $e1->id);
    $r2 = StudentTermResult::firstWhere('student_enrollment_id', $e2->id);

    expect((float) $r1->average)->toBe(70.0)
        ->and($r1->rank)->toBe(2)
        ->and((float) $r2->average)->toBe(90.0)
        ->and($r2->rank)->toBe(1)
        ->and($r2->rank_of)->toBe(2);

    $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/terms/{$term->id}/results?section_id={$section->id}")
        ->assertOk()
        ->assertJsonPath('data.0.rank', 1);
});

// ───────────────────────── promotion board + rollover ─────────────────────────

it('suggests decisions from the threshold and executes the rollover', function () {
    $branch = makeBranch();
    $director = directorOf($branch);
    Sanctum::actingAs($director);

    $year = opsYear($branch);
    $nextYear = opsYear($branch, '2018 E.C.', 'planned');
    $section = opsSection($branch, 'G1', 'A');
    opsSection($branch, 'G2', 'A'); // target grade section exists

    $abel = opsStudent($branch, 'Abel');
    $marta = opsStudent($branch, 'Marta', 'female');
    $e1 = opsEnroll($abel, $year, $section);
    $e2 = opsEnroll($marta, $year, $section);

    opsContinuousAssessment($branch, $section, $year, [$abel->id => 42, $marta->id => 88]);
    app(ComputeTermResultsAction::class)->execute($year->terms()->first());

    $board = $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/promotions/board?academic_year_id={$year->id}")
        ->assertOk()
        ->json();

    $rows = collect($board['data'])->keyBy('enrollment_id');
    expect($rows[$e1->id]['suggestion'])->toBe('repeated')
        ->and($rows[$e2->id]['suggestion'])->toBe('promoted')
        ->and($board['meta']['threshold'])->toBe(50);

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/promotions/decisions', [
            'academic_year_id' => $year->id,
            'decisions' => [
                ['enrollment_id' => $e1->id, 'decision' => 'repeated'],
                ['enrollment_id' => $e2->id, 'decision' => 'promoted'],
            ],
        ])->assertOk();

    $result = $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/promotions/rollover', [
            'academic_year_id' => $year->id,
            'to_academic_year_id' => $nextYear->id,
        ])->assertOk()->json('data');

    expect($result['executed'])->toBe(2);

    expect($e1->refresh()->status)->toBe(EnrollmentStatus::Repeated)
        ->and($e2->refresh()->status)->toBe(EnrollmentStatus::Promoted);

    $marta2 = StudentEnrollment::where('student_id', $marta->id)
        ->where('academic_year_id', $nextYear->id)->first();
    expect($marta2)->not->toBeNull()
        ->and(GradeLevel::find($marta2->grade_level_id)->code)->toBe('G2')
        ->and($marta2->section_id)->not->toBeNull(); // mapped 1A → 2A

    $abel2 = StudentEnrollment::where('student_id', $abel->id)
        ->where('academic_year_id', $nextYear->id)->first();
    expect(GradeLevel::find($abel2->grade_level_id)->code)->toBe('G1');

    $promotion = StudentPromotion::firstWhere('from_enrollment_id', $e2->id);
    expect($promotion->executed_at)->not->toBeNull()
        ->and($promotion->to_enrollment_id)->toBe($marta2->id);

    // The board must keep listing executed rows: the rollover stamps its
    // sources non-live, and if they drop out the year reads as empty and the
    // revert lane has no row to act on.
    $board = $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/promotions/board?academic_year_id={$year->id}")
        ->assertOk()->json('data');

    $byEnrollment = collect($board)->keyBy('enrollment_id');
    expect($byEnrollment)->toHaveCount(2)
        ->and($byEnrollment[$e2->id]['decision']['executed_at'])->not->toBeNull()
        ->and($byEnrollment[$e2->id]['enrollment_status'])->toBe('promoted')
        ->and($byEnrollment[$e1->id]['enrollment_status'])->toBe('repeated');
});

it('reverts an executed rollover — enrollment removed, bills voided, decision back to decided, re-runnable', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));
    $year = opsYear($branch);
    $nextYear = opsYear($branch, '2018 E.C.', 'planned');
    $section = opsSection($branch, 'G1', 'A');
    opsSection($branch, 'G2', 'A');
    opsRegistrationFee($branch, $nextYear); // next year's fee gates the new enrollment

    $student = opsStudent($branch);
    $from = opsEnroll($student, $year, $section);

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/promotions/decisions', [
            'academic_year_id' => $year->id,
            'decisions' => [['enrollment_id' => $from->id, 'decision' => 'promoted']],
        ])->assertOk();
    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/promotions/rollover', [
            'academic_year_id' => $year->id,
            'to_academic_year_id' => $nextYear->id,
        ])->assertOk();

    $to = StudentEnrollment::where('student_id', $student->id)
        ->where('academic_year_id', $nextYear->id)->firstOrFail();
    $invoice = Invoice::where('student_id', $student->id)
        ->where('academic_year_id', $nextYear->id)->firstOrFail();
    expect($to->status)->toBe(EnrollmentStatus::Pending) // fee-gated
        ->and($invoice->status->value)->toBe('unpaid');

    $result = $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/promotions/revert', ['academic_year_id' => $year->id])
        ->assertOk()->json('data');

    expect($result['reverted'])->toBe(1)->and($result['errors'])->toBe([]);

    // The new-year enrollment is gone, its bill dead, the source live again.
    expect(StudentEnrollment::withTrashed()->find($to->id)->deleted_at)->not->toBeNull()
        ->and($invoice->refresh()->status->value)->toBe('void')
        ->and($from->refresh()->status)->toBe(EnrollmentStatus::Active)
        ->and($from->exited_on)->toBeNull();

    // The decision survives as "decided, not executed" — fix and re-run.
    $promotion = StudentPromotion::firstWhere('from_enrollment_id', $from->id);
    expect($promotion->executed_at)->toBeNull()
        ->and($promotion->to_enrollment_id)->toBeNull()
        ->and($promotion->decided_at)->not->toBeNull()
        ->and($promotion->decision->value)->toBe('promoted');

    $rerun = $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/promotions/rollover', [
            'academic_year_id' => $year->id,
            'to_academic_year_id' => $nextYear->id,
        ])->assertOk()->json('data');
    expect($rerun['executed'])->toBe(1);
});

it('refuses to revert students whose new year already holds attendance or received money', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));
    $year = opsYear($branch);
    $nextYear = opsYear($branch, '2018 E.C.', 'planned');
    $section = opsSection($branch, 'G1', 'A');
    opsSection($branch, 'G2', 'A');

    $abel = opsStudent($branch, 'Abel');
    $marta = opsStudent($branch, 'Marta', 'female');
    $e1 = opsEnroll($abel, $year, $section);
    $e2 = opsEnroll($marta, $year, $section);

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/promotions/decisions', [
            'academic_year_id' => $year->id,
            'decisions' => [
                ['enrollment_id' => $e1->id, 'decision' => 'promoted'],
                ['enrollment_id' => $e2->id, 'decision' => 'promoted'],
            ],
        ])->assertOk();
    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/promotions/rollover', [
            'academic_year_id' => $year->id,
            'to_academic_year_id' => $nextYear->id,
        ])->assertOk();

    $abelNext = StudentEnrollment::where('student_id', $abel->id)
        ->where('academic_year_id', $nextYear->id)->firstOrFail();

    // Abel already has a school day marked in the new year.
    AttendanceRecord::create([
        'school_id' => $branch->school_id,
        'branch_id' => $branch->id,
        'section_id' => $abelNext->section_id,
        'student_id' => $abel->id,
        'academic_year_id' => $nextYear->id,
        'date' => now()->toDateString(),
        'status' => 'present',
    ]);

    // Marta's family already paid something for the new year.
    Invoice::create([
        'school_id' => $branch->school_id,
        'branch_id' => $branch->id,
        'student_id' => $marta->id,
        'academic_year_id' => $nextYear->id,
        'title' => 'Tuition — Meskerem',
        'amount' => 800,
        'amount_paid' => 800,
        'status' => 'paid',
    ]);

    $result = $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/promotions/revert', ['academic_year_id' => $year->id])
        ->assertOk()->json('data');

    expect($result['reverted'])->toBe(0)->and($result['skipped'])->toBe(2);
    $messages = collect($result['errors'])->pluck('message', 'student');
    expect($messages['Abel Tesfaye'])->toContain('Attendance')
        ->and($messages['Marta Tesfaye'])->toContain('payment');

    // Nothing moved: both new-year enrollments and both sources stand.
    expect($abelNext->refresh()->deleted_at)->toBeNull()
        ->and($e1->refresh()->status)->toBe(EnrollmentStatus::Promoted);

    // A named single-student revert of an untouched student still works.
    // (fresh student, no records in the new year)
    $sara = opsStudent($branch, 'Sara', 'female');
    $e3 = opsEnroll($sara, $year, $section);
    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/promotions/decisions', [
            'academic_year_id' => $year->id,
            'decisions' => [['enrollment_id' => $e3->id, 'decision' => 'promoted']],
        ])->assertOk();
    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/promotions/rollover', [
            'academic_year_id' => $year->id,
            'to_academic_year_id' => $nextYear->id,
        ])->assertOk();

    $single = $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/promotions/revert', [
            'academic_year_id' => $year->id,
            'enrollment_ids' => [$e3->id],
        ])->assertOk()->json('data');

    expect($single['reverted'])->toBe(1)
        ->and($e3->refresh()->status)->toBe(EnrollmentStatus::Active)
        // The named revert never touched the blocked students.
        ->and($e1->refresh()->status)->toBe(EnrollmentStatus::Promoted);
});

// ───────────────────────── section assignment ─────────────────────────

it('proposes a gender and ability balanced distribution and commits it', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));
    $year = opsYear($branch);
    $a = opsSection($branch, 'G1', 'A');
    $b = opsSection($branch, 'G1', 'B');
    $gradeId = $a->grade_level_id;

    $enrollments = [];
    foreach (range(1, 6) as $i) {
        $student = opsStudent($branch, "S{$i}", $i % 2 === 0 ? 'female' : 'male');
        $enrollments[] = opsEnroll($student, $year, null, $gradeId);
    }

    $proposal = $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/section-assignments/propose', [
            'academic_year_id' => $year->id,
            'grade_level_id' => $gradeId,
        ])->assertOk()->json('data');

    $bySection = collect($proposal['assignments'])->groupBy('section_id');
    expect($bySection->get($a->id))->toHaveCount(3)
        ->and($bySection->get($b->id))->toHaveCount(3);

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/section-assignments/commit', [
            'academic_year_id' => $year->id,
            'assignments' => $proposal['assignments'],
        ])->assertOk();

    expect(StudentEnrollment::where('section_id', $a->id)->count())->toBe(3);
});

it('refuses a commit that would overflow a section capacity', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));
    $year = opsYear($branch);
    $a = opsSection($branch, 'G1', 'A', capacity: 1);

    $e1 = opsEnroll(opsStudent($branch, 'S1'), $year, null, $a->grade_level_id);
    $e2 = opsEnroll(opsStudent($branch, 'S2'), $year, null, $a->grade_level_id);

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/section-assignments/commit', [
            'academic_year_id' => $year->id,
            'assignments' => [
                ['enrollment_id' => $e1->id, 'section_id' => $a->id],
                ['enrollment_id' => $e2->id, 'section_id' => $a->id],
            ],
        ])->assertStatus(422);
});

it('bulk-assigns hand-picked students to a section, skipping mismatches, and unassigns back to the pool', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));
    $year = opsYear($branch);
    $a = opsSection($branch, 'G1', 'A');
    $b = opsSection($branch, 'G2', 'B');

    $s1 = opsStudent($branch, 'S1');
    // Give S1 their own account so the family feed has an audience.
    $s1->update(['user_id' => User::create([
        'name' => 'S1 Tesfaye', 'phone' => '0911000199', 'preferred_language' => 'en',
    ])->id]);
    $s2 = opsStudent($branch, 'S2', 'female');
    $s3 = opsStudent($branch, 'S3'); // enrolled in ANOTHER grade → skipped
    opsEnroll($s1, $year, null, $a->grade_level_id);
    opsEnroll($s2, $year, null, $a->grade_level_id);
    opsEnroll($s3, $year, null, $b->grade_level_id);
    $s4 = opsStudent($branch, 'S4'); // not enrolled at all → skipped

    $meta = $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/section-assignments/students', [
            'academic_year_id' => $year->id,
            'student_ids' => [$s1->id, $s2->id, $s3->id, $s4->id],
            'section_id' => $a->id,
        ])->assertOk()->json('meta');

    expect($meta['updated'])->toBe(2)
        ->and(collect($meta['skipped'])->pluck('reason')->sort()->values()->all())
        ->toBe(['grade_mismatch', 'not_enrolled']);
    expect(StudentEnrollment::where('section_id', $a->id)->count())->toBe(2);

    // The family feed learns about the new section (in-app pipeline).
    expect(DB::table('notifications')->where('event', 'academics.section_assigned')->exists())->toBeTrue();

    // Unassign returns them to the pool — and stays silent.
    $meta = $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/section-assignments/students', [
            'academic_year_id' => $year->id,
            'student_ids' => [$s1->id, $s2->id],
            'section_id' => null,
        ])->assertOk()->json('meta');

    expect($meta['updated'])->toBe(2);
    expect(StudentEnrollment::where('section_id', $a->id)->count())->toBe(0);
});

it('forbids bulk section assignment without sections.update authority', function () {
    $branch = makeBranch();
    $year = opsYear($branch);
    $section = opsSection($branch, 'G1', 'A');
    $student = opsStudent($branch, 'S1');
    opsEnroll($student, $year, null, $section->grade_level_id);

    Sanctum::actingAs(memberOf($branch));

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/section-assignments/students', [
            'academic_year_id' => $year->id,
            'student_ids' => [$student->id],
            'section_id' => $section->id,
        ])->assertForbidden();
});

// ───────────────────────── transfers ─────────────────────────

it('runs the full transfer flow: candidate lookup → request → approve', function () {
    Storage::fake(config('filesystems.default'));
    $branchA = makeBranch('AA-0001');
    $schoolB = School::create(['name' => 'Rift Valley Academy']);
    $branchB = $schoolB->branches()->create(['name' => 'Main', 'code' => 'AA-0002']);

    $yearA = opsYear($branchA);
    $sectionA = opsSection($branchA, 'G1', 'A');
    $student = opsStudent($branchA, 'Hanna', 'female');
    $fromEnrollment = opsEnroll($student, $yearA, $sectionA);

    $yearB = (new SaveAcademicYearAction())->execute($branchB, ['name' => '2017 E.C.', 'status' => 'active']);

    // Receiving branch (B) finds the candidate by exact public ID.
    $directorB = directorOf($branchB);
    Sanctum::actingAs($directorB);

    $candidate = $this->withHeaders(branchContext($branchB))
        ->getJson('/api/v1/transfer-requests/candidate?query='.$student->refresh()->public_id)
        ->assertOk()
        ->json('data');

    expect($candidate['full_name'])->toContain('Hanna')
        ->and($candidate['school_name'])->toBe('Unity Academy');

    // Multipart: supporting documents travel with the request itself.
    $storeResponse = $this->withHeaders(branchContext($branchB))
        ->post('/api/v1/transfer-requests', [
            'student_id' => $candidate['student_id'],
            'to_academic_year_id' => $yearB->id,
            'to_grade_level_id' => GradeLevel::where('code', 'G2')->value('id'),
            'reason' => 'Family moved to the area',
            'documents' => [
                ['file' => UploadedFile::fake()->create('report-card.pdf', 100, 'application/pdf'), 'name' => 'Report card'],
            ],
        ], ['Accept' => 'application/json'])->assertCreated();

    $transferId = $storeResponse->json('data.id');
    expect($storeResponse->json('data.attachments'))->toHaveCount(1)
        ->and($storeResponse->json('data.attachments.0.name'))->toBe('Report card');

    // The receiving side cannot approve its own request.
    $this->withHeaders(branchContext($branchB))
        ->postJson("/api/v1/transfer-requests/{$transferId}/approve")
        ->assertForbidden();

    // Sending branch (A) approves — the handover happens atomically.
    Sanctum::actingAs(directorOf($branchA));
    $this->withHeaders(branchContext($branchA))
        ->postJson("/api/v1/transfer-requests/{$transferId}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    expect($fromEnrollment->refresh()->status)->toBe(EnrollmentStatus::TransferredOut);

    $newEnrollment = StudentEnrollment::where('student_id', $student->id)
        ->where('branch_id', $branchB->id)->first();
    expect($newEnrollment)->not->toBeNull()
        ->and($newEnrollment->status)->toBe(EnrollmentStatus::Active);

    expect(StudentPromotion::where('student_id', $student->id)->where('decision', 'transferred')->exists())->toBeTrue();

    // Both sides can read the letter afterwards; the first open mints the
    // public verification token the QR code points at.
    $publicToken = $this->withHeaders(branchContext($branchA))
        ->getJson("/api/v1/transfer-requests/{$transferId}/letter")
        ->assertOk()
        ->assertJsonPath('data.to_school', 'Rift Valley Academy')
        ->json('data.public_token');

    expect($publicToken)->not->toBeNull();

    // The public copy resolves without auth; a wrong token never does.
    $this->getJson("/api/v1/public/transfer-letters/{$publicToken}")
        ->assertOk()
        ->assertJsonPath('data.reference', sprintf('TR-%05d', $transferId));
    $this->getJson('/api/v1/public/transfer-letters/not-a-real-token')
        ->assertNotFound();
});

it('lets the sending branch reject a transfer with a note', function () {
    $branchA = makeBranch('AA-0001');
    $schoolB = School::create(['name' => 'Rift Valley Academy']);
    $branchB = $schoolB->branches()->create(['name' => 'Main', 'code' => 'AA-0002']);

    $yearA = opsYear($branchA);
    $student = opsStudent($branchA, 'Hanna', 'female');
    opsEnroll($student, $yearA, opsSection($branchA));
    $yearB = (new SaveAcademicYearAction())->execute($branchB, ['name' => '2017 E.C.', 'status' => 'active']);

    Sanctum::actingAs(directorOf($branchB));
    $transferId = $this->withHeaders(branchContext($branchB))
        ->postJson('/api/v1/transfer-requests', [
            'student_id' => $student->id,
            'to_academic_year_id' => $yearB->id,
            'to_grade_level_id' => GradeLevel::where('code', 'G1')->value('id'),
            'reason' => 'Parent request',
        ])->assertCreated()->json('data.id');

    Sanctum::actingAs(directorOf($branchA));
    $this->withHeaders(branchContext($branchA))
        ->postJson("/api/v1/transfer-requests/{$transferId}/reject", [
            'decision_note' => 'Outstanding fee balance of 2,400 ETB',
        ])->assertOk()
        ->assertJsonPath('data.status', 'rejected');

    expect($student->enrollments()->first()->status)->toBe(EnrollmentStatus::Active);
});

// ───────────────────────── timetable generation ─────────────────────────

it('generates a conflict-free draft timetable and publishes it', function () {
    config(['queue.default' => 'sync']);

    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));
    $year = opsYear($branch);
    $term = $year->terms()->first();
    $section = opsSection($branch);
    $headers = branchContext($branch);

    // Two teachers, three subjects — physics requires a lab.
    $makeTeacher = fn (string $name) => Employee::create([
        'user_id' => User::factory()->create()->id,
        'school_id' => $branch->school_id,
        'branch_id' => $branch->id,
        'first_name' => $name,
        'father_name' => 'Bekele',
        'gender' => 'male',
    ]);
    $alemu = $makeTeacher('Alemu');
    $chaltu = $makeTeacher('Chaltu');

    Room::create([
        'school_id' => $branch->school_id,
        'branch_id' => $branch->id,
        'name' => 'Science lab',
        'type' => 'lab',
    ]);

    foreach ([['MATH', 5, $alemu], ['ENG', 4, $alemu], ['PHY', 3, $chaltu]] as [$code, $ppw, $teacher]) {
        SubjectAssignment::create([
            'school_id' => $branch->school_id,
            'branch_id' => $branch->id,
            'academic_year_id' => $year->id,
            'section_id' => $section->id,
            'subject_id' => Subject::where('code', $code)->value('id'),
            'term_id' => $term->id,
            'employee_id' => $teacher->id,
            'periods_per_week' => $ppw,
        ]);
    }

    $this->withHeaders($headers)->postJson("/api/v1/terms/{$term->id}/periods/defaults")->assertOk();

    $versionId = $this->withHeaders($headers)
        ->postJson("/api/v1/terms/{$term->id}/timetable-versions", ['name' => 'Semester grid'])
        ->assertCreated()->json('data.id');

    $this->withHeaders($headers)
        ->postJson("/api/v1/timetable-versions/{$versionId}/generate")
        ->assertOk();

    $grid = $this->withHeaders($headers)
        ->getJson("/api/v1/timetable-versions/{$versionId}")
        ->assertOk()->json('data');

    expect($grid['version']['status'])->toBe('draft')
        ->and(count($grid['slots']))->toBe(12); // 5 + 4 + 3 periods placed

    // No two slots share a cell (single section).
    $cells = collect($grid['slots'])->map(fn ($s) => $s['day_of_week'].'-'.$s['period_number']);
    expect($cells->duplicates())->toBeEmpty();

    // Physics requires a lab — the solver booked the branch's science lab.
    $physicsAssignment = collect($grid['assignments'])->firstWhere('subject.code', 'PHY');
    $physicsSlots = collect($grid['slots'])
        ->where('subject_assignment_id', $physicsAssignment['id']);
    expect($physicsSlots)->toHaveCount(3)
        ->and($physicsSlots->every(fn ($s) => $s['room_id'] !== null))->toBeTrue();

    // Non-lab subjects stay in the section's own classroom.
    $mathAssignment = collect($grid['assignments'])->firstWhere('subject.code', 'MATH');
    expect(
        collect($grid['slots'])
            ->where('subject_assignment_id', $mathAssignment['id'])
            ->every(fn ($s) => $s['room_id'] === null),
    )->toBeTrue();

    $this->withHeaders($headers)
        ->postJson("/api/v1/timetable-versions/{$versionId}/publish")
        ->assertOk()
        ->assertJsonPath('data.status', 'published');
});

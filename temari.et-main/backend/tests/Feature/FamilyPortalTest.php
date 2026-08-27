<?php

use App\Actions\EnrollStudentAction;
use App\Actions\SaveAcademicYearAction;
use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AssessmentResult;
use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\FeeStructure;
use App\Models\GradeLevel;
use App\Models\Holiday;
use App\Models\Invoice;
use App\Models\Notification;
use App\Models\ParentProfile;
use App\Models\Payment;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentGuardian;
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

/** A live year spanning today — the recurring engine needs a real window. */
function familyYear(Branch $branch): AcademicYear
{
    return (new SaveAcademicYearAction)->execute($branch, [
        'name' => '2018 E.C.', 'status' => 'active',
        'starts_on' => now()->subMonths(2)->toDateString(),
        'ends_on' => now()->addMonths(4)->toDateString(),
    ]);
}

/**
 * One enrolled child + a guardian holding the given link flags.
 *
 * @return array{0: User, 1: Student, 2: StudentEnrollment, 3: AcademicYear, 4: Section}
 */
function familySetup(Branch $branch, array $flags = []): array
{
    $year = familyYear($branch);

    $section = $branch->sections()->create([
        'school_id' => $branch->school_id,
        'grade_level_id' => GradeLevel::where('code', 'G1')->value('id'),
        'name' => 'A',
    ]);

    $student = $branch->students()->create([
        'school_id' => $branch->school_id,
        'first_name' => 'Liya', 'father_name' => 'Tesfaye', 'gender' => 'female',
    ]);

    $enrollment = app(EnrollStudentAction::class)->execute($student, [
        'academic_year_id' => $year->id,
        'section_id' => $section->id,
        'grade_level_id' => $section->grade_level_id,
    ]);

    $guardianUser = User::factory()->create();
    $parent = ParentProfile::create(['user_id' => $guardianUser->id]);
    StudentGuardian::create(array_merge([
        'student_id' => $student->id, 'parent_id' => $parent->id,
        'relationship' => 'mother', 'is_active' => true,
        'can_view_grades' => true, 'can_view_attendance' => true, 'can_pay_fees' => true,
    ], $flags));

    return [$guardianUser, $student, $enrollment, $year, $section];
}

/** A subject assignment with a named teacher on the year's first term. */
function familySubject(Branch $branch, AcademicYear $year, Section $section): SubjectAssignment
{
    $teacher = Employee::create([
        'user_id' => User::factory()->create()->id,
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => 'Alemu', 'father_name' => 'Bekele', 'gender' => 'male',
    ]);

    return SubjectAssignment::create([
        'school_id' => $branch->school_id,
        'branch_id' => $branch->id,
        'academic_year_id' => $year->id,
        'section_id' => $section->id,
        'subject_id' => Subject::where('code', 'MATH')->value('id'),
        'term_id' => $year->terms()->first()->id,
        'employee_id' => $teacher->id,
        'periods_per_week' => 5,
    ]);
}

// ───────────────────────── aggregated child home ─────────────────────────

it('serves the aggregated child home with every section for a full link', function () {
    $branch = makeBranch();
    [$guardian, $student] = familySetup($branch);

    Sanctum::actingAs($guardian);

    $home = $this->getJson("/api/v1/me/children/{$student->id}/home")
        ->assertOk()
        ->json('data');

    expect($home['attendance'])->not->toBeNull()
        ->and($home['results'])->toHaveKey('latest')
        ->and($home['fees']['open_count'])->toBe(0)
        ->and($home['classwork']['due_count'])->toBe(0);
});

it('nulls the home sections a guardian link does not allow', function () {
    $branch = makeBranch();
    [$guardian, $student] = familySetup($branch, [
        'can_view_grades' => false, 'can_view_attendance' => false, 'can_pay_fees' => false,
    ]);

    Sanctum::actingAs($guardian);

    $home = $this->getJson("/api/v1/me/children/{$student->id}/home")
        ->assertOk()
        ->json('data');

    expect($home['attendance'])->toBeNull()
        ->and($home['results'])->toBeNull()
        ->and($home['fees'])->toBeNull();
});

it('denies the child home to an unlinked parent', function () {
    $branch = makeBranch();
    [, $student] = familySetup($branch);

    $stranger = User::factory()->create();
    ParentProfile::create(['user_id' => $stranger->id]);
    Sanctum::actingAs($stranger);

    $this->getJson("/api/v1/me/children/{$student->id}/home")->assertForbidden();
});

// ───────────────────────── payments: history + upcoming ─────────────────────────

it('lists payment history with receipt tokens for the family', function () {
    $branch = makeBranch();
    [$guardian, $student, , $year] = familySetup($branch);

    $invoice = Invoice::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'student_id' => $student->id, 'academic_year_id' => $year->id,
        'title' => 'Tuition — Meskerem', 'amount' => 1500, 'amount_paid' => 1500, 'status' => 'paid',
    ]);
    Payment::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'invoice_id' => $invoice->id, 'student_id' => $student->id,
        'amount' => 1500, 'method' => 'cash', 'paid_at' => now()->toDateString(),
        'receipt_number' => 'RCT-000123', 'receipt_token' => 'tok-abc123',
    ]);

    Sanctum::actingAs($guardian);

    $rows = $this->getJson("/api/v1/me/children/{$student->id}/payments")
        ->assertOk()
        ->json('data');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['receipt_number'])->toBe('RCT-000123')
        ->and($rows[0]['receipt_token'])->toBe('tok-abc123')
        ->and($rows[0]['invoice_title'])->toBe('Tuition — Meskerem');
});

it('previews upcoming recurring periods before any invoice exists', function () {
    $branch = makeBranch();
    [$guardian, $student, , $year] = familySetup($branch);

    FeeStructure::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'academic_year_id' => $year->id,
        'name' => 'Monthly tuition', 'type' => 'monthly', 'amount' => 800,
        'auto_generate' => true, 'is_active' => true,
    ]);

    Sanctum::actingAs($guardian);

    $rows = $this->getJson("/api/v1/me/children/{$student->id}/upcoming-fees")
        ->assertOk()
        ->json('data');

    expect(count($rows))->toBeGreaterThan(0)
        ->and($rows[0]['fee'])->toBe('Monthly tuition')
        ->and($rows[0]['amount'])->toBe('800.00')
        ->and($rows[0]['due_date'])->toBeGreaterThan(now()->toDateString());
});

it('blocks payment surfaces when the link cannot pay fees', function () {
    $branch = makeBranch();
    [$guardian, $student] = familySetup($branch, ['can_pay_fees' => false]);

    Sanctum::actingAs($guardian);

    $this->getJson("/api/v1/me/children/{$student->id}/payments")->assertForbidden();
    $this->getJson("/api/v1/me/children/{$student->id}/upcoming-fees")->assertForbidden();
});

// ───────────────────────── live marks (result card) ─────────────────────────

it('exposes teacher and sign-off state on the family result card', function () {
    $branch = makeBranch();
    [$guardian, $student, , $year, $section] = familySetup($branch);
    $assignment = familySubject($branch, $year, $section);

    $assessment = Assessment::create([
        'subject_assignment_id' => $assignment->id,
        'type' => 'test', 'name' => 'Test 1', 'max_score' => 20, 'weight' => 20,
        'conducted_on' => now()->subDays(3)->toDateString(),
    ]);
    AssessmentResult::create([
        'assessment_id' => $assessment->id, 'student_id' => $student->id, 'score' => 17,
    ]);

    Sanctum::actingAs($guardian);

    $card = $this->getJson("/api/v1/me/children/{$student->id}/result-card?term_id={$assignment->term_id}")
        ->assertOk()
        ->json('data');

    expect($card['subjects'][0]['teacher'])->toBe('Alemu Bekele')
        ->and($card['subjects'][0]['marklist_status'])->toBe('draft')
        ->and($card['subjects'][0]['assessments'][0]['score'])->toBe(17)
        ->and($card['subjects'][0]['assessed_weight'])->toBe(20);
});

// ───────────────────────── calendar + teachers ─────────────────────────

it('builds the family agenda from holidays and planned assessments', function () {
    $branch = makeBranch();
    [, $student, , $year, $section] = familySetup($branch);
    $assignment = familySubject($branch, $year, $section);

    $studentUser = User::factory()->create();
    $student->update(['user_id' => $studentUser->id]);

    Holiday::create([
        'school_id' => $branch->school_id,
        'name' => 'Adwa Victory Day',
        'date' => now()->addDays(5)->toDateString(),
    ]);
    Assessment::create([
        'subject_assignment_id' => $assignment->id,
        'type' => 'test', 'name' => 'Mid-term', 'max_score' => 30, 'weight' => 30,
        'conducted_on' => now()->addDays(10)->toDateString(),
    ]);

    Sanctum::actingAs($studentUser);

    $events = $this->getJson('/api/v1/me/student/calendar')
        ->assertOk()
        ->json('data');

    $types = collect($events)->pluck('type');
    expect($types)->toContain('holiday')
        ->and($types)->toContain('assessment');
});

it('lists the subject teachers of the student class', function () {
    $branch = makeBranch();
    [$guardian, $student, , $year, $section] = familySetup($branch);
    familySubject($branch, $year, $section);

    Sanctum::actingAs($guardian);

    $rows = $this->getJson("/api/v1/me/children/{$student->id}/teachers")
        ->assertOk()
        ->json('data');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['teacher'])->toBe('Alemu Bekele')
        ->and($rows[0]['subject'])->not->toBeNull();
});

// ───────────────────────── absence excuses ─────────────────────────

it('runs the absence-excuse flow: parent files, staff approves, absence reads excused', function () {
    $branch = makeBranch();
    [$guardian, $student, $enrollment, $year, $section] = familySetup($branch);
    $term = $year->terms()->first();

    $absentDay = now()->subDay()->toDateString();
    $record = AttendanceRecord::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'section_id' => $section->id, 'student_id' => $student->id,
        'academic_year_id' => $year->id, 'term_id' => $term->id,
        'date' => $absentDay, 'status' => 'absent', 'source' => 'manual',
    ]);

    $director = directorOf($branch);

    // Parent files the excuse.
    Sanctum::actingAs($guardian);
    $excuseId = $this->postJson("/api/v1/me/children/{$student->id}/absence-excuses", [
        'starts_on' => $absentDay, 'ends_on' => $absentDay,
        'reason' => 'Sick with the flu.',
    ])
        ->assertCreated()
        ->json('data.id');

    // The branch's deciders were notified through the pipeline.
    expect(Notification::query()
        ->where('user_id', $director->id)
        ->where('event', 'attendance.excuse_filed')
        ->exists())->toBeTrue();

    // The family sees it pending.
    $mine = $this->getJson("/api/v1/me/children/{$student->id}/absence-excuses")
        ->assertOk()
        ->json('data');
    expect($mine[0]['status'])->toBe('pending');

    // Staff approves — the absent day flips to excused.
    Sanctum::actingAs($director);
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/absence-excuses/{$excuseId}/decide", ['decision' => 'approved'])
        ->assertOk()
        ->assertJsonPath('data.excused_days', 1);

    expect($record->refresh()->status->value)->toBe('excused');

    // The family was told.
    expect(Notification::query()
        ->where('user_id', $guardian->id)
        ->where('event', 'attendance.excuse_decided')
        ->exists())->toBeTrue();

    // A decided excuse cannot be re-decided.
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/absence-excuses/{$excuseId}/decide", ['decision' => 'rejected'])
        ->assertUnprocessable();
});

it('keeps teachers and unlinked parents out of the excuse flow', function () {
    $branch = makeBranch();
    [$guardian, $student] = familySetup($branch);

    // Filing needs the attendance flag on the link.
    $limited = User::factory()->create();
    $limitedParent = ParentProfile::create(['user_id' => $limited->id]);
    StudentGuardian::create([
        'student_id' => $student->id, 'parent_id' => $limitedParent->id,
        'relationship' => 'father', 'is_active' => true,
        'can_view_grades' => true, 'can_view_attendance' => false, 'can_pay_fees' => true,
    ]);
    Sanctum::actingAs($limited);
    $this->postJson("/api/v1/me/children/{$student->id}/absence-excuses", [
        'starts_on' => now()->toDateString(), 'ends_on' => now()->toDateString(),
        'reason' => 'Test',
    ])->assertForbidden();

    // A plain teacher cannot decide (attendance.record is supervisory).
    Sanctum::actingAs($guardian);
    $excuseId = $this->postJson("/api/v1/me/children/{$student->id}/absence-excuses", [
        'starts_on' => now()->toDateString(), 'ends_on' => now()->toDateString(),
        'reason' => 'Family emergency.',
    ])->assertCreated()->json('data.id');

    Sanctum::actingAs(memberOf($branch, Role::Teacher));
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/absence-excuses/{$excuseId}/decide", ['decision' => 'approved'])
        ->assertForbidden();
});

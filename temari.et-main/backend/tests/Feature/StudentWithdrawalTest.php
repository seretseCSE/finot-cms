<?php

use App\Enums\PromotionDecision;
use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\GradeLevel;
use App\Models\Invoice;
use App\Models\SchoolProgram;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentPromotion;
use App\Models\StudentWithdrawal;
use Database\Seeders\GradeLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

/**
 * Mid-year withdrawal (leaving school / moving OUTSIDE Temari): closes the
 * live enrollment, snapshots the outstanding balance, writes the promotion
 * audit row and backs the QR-verified clearance letter. Outstanding fees are
 * noted, never a blocker.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(GradeLevelSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function withdrawalSetup(Branch $branch): array
{
    $year = AcademicYear::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'name' => '2018 E.C.', 'status' => 'active',
    ]);
    $student = Student::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => 'Lelise', 'father_name' => 'Bekele', 'gender' => 'female',
    ]);
    $enrollment = StudentEnrollment::create([
        'student_id' => $student->id, 'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'academic_year_id' => $year->id, 'school_program_id' => SchoolProgram::defaultFor($branch)->id,
        'grade_level_id' => GradeLevel::first()->id, 'status' => 'active', 'enrolled_on' => now(),
    ]);

    return [$year, $student, $enrollment];
}

it('withdraws a live enrollment, freezing reason, audit row and outstanding balance', function () {
    $branch = makeBranch();
    [$year, $student, $enrollment] = withdrawalSetup($branch);

    // 1000 gross, 300 already paid ⇒ 700 outstanding on the letter.
    Invoice::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'student_id' => $student->id, 'academic_year_id' => $year->id,
        'title' => 'Tuition', 'amount' => 1000, 'amount_paid' => 300, 'status' => 'partial',
    ]);

    Sanctum::actingAs(directorOf($branch));

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/enrollments/{$enrollment->id}/withdraw", [
            'reason' => 'Family relocating to Bahir Dar',
            'destination' => 'Bahir Dar Academy',
        ])
        ->assertCreated()
        ->assertJsonPath('data.outstanding_amount', '700.00');

    expect($enrollment->refresh()->status->value)->toBe('withdrawn')
        ->and($enrollment->exited_on)->not->toBeNull();

    $withdrawal = StudentWithdrawal::where('enrollment_id', $enrollment->id)->firstOrFail();
    expect((float) $withdrawal->outstanding_amount)->toBe(700.0)
        ->and($withdrawal->destination)->toBe('Bahir Dar Academy');

    // The movement lands in the same audit table transfers and year-end use.
    expect(StudentPromotion::where('from_enrollment_id', $enrollment->id)->firstOrFail()->decision)
        ->toBe(PromotionDecision::Withdrawn);

    // Withdrawing again must fail — the enrollment is no longer live.
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/enrollments/{$enrollment->id}/withdraw", ['reason' => 'again'])
        ->assertUnprocessable();
});

it('serves the clearance letter to staff and the public QR copy without auth', function () {
    $branch = makeBranch();
    [, , $enrollment] = withdrawalSetup($branch);

    Sanctum::actingAs(directorOf($branch));
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/enrollments/{$enrollment->id}/withdraw", ['reason' => 'Moving abroad'])
        ->assertCreated();

    $letter = $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/enrollments/{$enrollment->id}/withdrawal-letter")
        ->assertOk()
        ->json('data');

    expect($letter['reference'])->toStartWith('WD-')
        ->and($letter['public_token'])->not->toBeNull()
        ->and($letter['reason'])->toBe('Moving abroad');

    // The QR target works with NO session at all.
    $this->flushHeaders();
    app('auth')->forgetGuards();
    $this->getJson("/api/v1/public/withdrawal-letters/{$letter['public_token']}")
        ->assertOk()
        ->assertJsonPath('data.reference', $letter['reference']);
});

it('refuses withdrawal to teachers and to other schools', function () {
    $branchA = makeBranch('AA-0001');
    $branchB = makeBranch('BB-0001');
    [, , $enrollment] = withdrawalSetup($branchA);

    // Teachers hold no student-movement authority.
    Sanctum::actingAs(memberOf($branchA));
    $this->withHeaders(branchContext($branchA))
        ->postJson("/api/v1/enrollments/{$enrollment->id}/withdraw", ['reason' => 'x'])
        ->assertForbidden();

    // Another school's director cannot touch the enrollment either.
    Sanctum::actingAs(directorOf($branchB));
    $this->withHeaders(branchContext($branchB))
        ->postJson("/api/v1/enrollments/{$enrollment->id}/withdraw", ['reason' => 'x'])
        ->assertForbidden();
});

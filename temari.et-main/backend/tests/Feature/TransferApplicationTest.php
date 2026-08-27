<?php

use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\GradeLevel;
use App\Models\ParentProfile;
use App\Models\SchoolProgram;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentGuardian;
use App\Models\StudentTransferRequest;
use App\Models\TransferApplication;
use App\Models\User;
use App\Services\Sms\SmsClient;
use Database\Seeders\GradeLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

/**
 * Parent/student-initiated transfer applications (the NEMIS order): family →
 * destination school accepts (materializing the standard transfer request) →
 * current school approves. Tracking lives in the relationship lane; every
 * stage notifies the family.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(GradeLevelSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    app()->instance(SmsClient::class, Mockery::spy(SmsClient::class));
});

function appYear(Branch $branch, string $name = '2017 E.C.'): AcademicYear
{
    return AcademicYear::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'name' => $name, 'status' => 'active',
    ]);
}

function appStudent(Branch $branch, string $first = 'Abel'): Student
{
    return $branch->students()->create([
        'school_id' => $branch->school_id,
        'first_name' => $first, 'father_name' => 'Tesfaye', 'gender' => 'male',
    ]);
}

function appEnroll(Student $student, AcademicYear $year, string $gradeCode = 'G1'): void
{
    StudentEnrollment::create([
        'student_id' => $student->id, 'school_id' => $year->school_id, 'branch_id' => $year->branch_id,
        'academic_year_id' => $year->id,
        'school_program_id' => SchoolProgram::defaultFor($year->branch)->id,
        'grade_level_id' => GradeLevel::where('code', $gradeCode)->value('id'),
        'status' => 'active', 'enrolled_on' => now(),
    ]);
}

/** A guardian USER linked to the student, ready to act in the /me lane. */
function applicationGuardian(Student $student): User
{
    $user = User::factory()->create(['phone' => '0911'.random_int(100000, 999999)]);
    $parent = ParentProfile::create([
        'user_id' => $user->id, 'first_name' => 'Guardian', 'father_name' => 'Test',
    ]);
    StudentGuardian::create([
        'student_id' => $student->id, 'parent_id' => $parent->id,
        'relationship' => 'father', 'is_active' => true, 'can_receive_sms' => true,
    ]);

    return $user;
}

it('runs the full family-initiated flow: apply → accept → current school approves', function () {
    $branchA = makeBranch('AA-0001');
    $branchB = makeBranch('BB-0001');
    $yearA = appYear($branchA);
    $yearB = appYear($branchB);
    $student = appStudent($branchA, 'Meles');
    appEnroll($student, $yearA);
    $guardian = applicationGuardian($student);

    // 1. The guardian browses destinations and applies to school B.
    Sanctum::actingAs($guardian);
    $destinations = $this->getJson('/api/v1/me/transfer-applications/destinations?q=')
        ->assertOk()
        ->json('data');
    expect(collect($destinations)->pluck('id'))->toContain($branchB->school_id);

    $applicationId = $this->postJson('/api/v1/me/transfer-applications', [
        'student_id' => $student->id,
        'to_branch_id' => $branchB->id,
        'reason' => 'We are moving closer to school B.',
    ])->assertCreated()->json('data.id');

    // Duplicate applications are blocked while one is live.
    $this->postJson('/api/v1/me/transfer-applications', [
        'student_id' => $student->id,
        'to_branch_id' => $branchB->id,
        'reason' => 'again',
    ])->assertUnprocessable();

    // Tracking shows the submitted application.
    $tracking = $this->getJson('/api/v1/me/transfers')->assertOk()->json('data');
    expect($tracking['applications'][0]['id'])->toBe($applicationId)
        ->and($tracking['applications'][0]['status'])->toBe('submitted');

    // 2. The DESTINATION school sees it in its inbox (limited profile) and accepts.
    Sanctum::actingAs(directorOf($branchB));
    $inbox = $this->withHeaders(branchContext($branchB))
        ->getJson('/api/v1/transfer-applications')
        ->assertOk()
        ->json('data');
    expect($inbox[0]['id'])->toBe($applicationId)
        ->and($inbox[0]['student']['full_name'])->toContain('Meles');

    $requestId = $this->withHeaders(branchContext($branchB))
        ->postJson("/api/v1/transfer-applications/{$applicationId}/accept", [
            'to_academic_year_id' => $yearB->id,
            'to_grade_level_id' => GradeLevel::where('code', 'G2')->value('id'),
        ])
        ->assertOk()
        ->json('data.transfer_request_id');

    expect(TransferApplication::findOrFail($applicationId)->status->value)->toBe('accepted');

    // 3. The CURRENT school still holds the final say.
    Sanctum::actingAs(directorOf($branchA));
    $this->withHeaders(branchContext($branchA))
        ->postJson("/api/v1/transfer-requests/{$requestId}/approve")
        ->assertOk();

    expect(StudentTransferRequest::findOrFail($requestId)->status->value)->toBe('approved');

    // The family was texted at each stage (accepted + approved at minimum).
    app(SmsClient::class)->shouldHaveReceived('send')->atLeast()->twice();

    // Tracking now shows the materialized request's status.
    Sanctum::actingAs($guardian);
    $tracking = $this->getJson('/api/v1/me/transfers')->assertOk()->json('data');
    expect($tracking['applications'][0]['request_status'])->toBe('approved');
});

it('refuses applications from accounts with no link to the student', function () {
    $branchA = makeBranch('AA-0001');
    $branchB = makeBranch('BB-0001');
    $year = appYear($branchA);
    $student = appStudent($branchA);
    appEnroll($student, $year);

    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/me/transfer-applications', [
        'student_id' => $student->id,
        'to_branch_id' => $branchB->id,
        'reason' => 'not my child',
    ])->assertForbidden();

    // And the /me tracking of a stranger stays empty.
    expect($this->getJson('/api/v1/me/transfers')->assertOk()->json('data.applications'))->toBeEmpty();
});

it('lets the applicant withdraw before acceptance, and the destination decline with a note', function () {
    $branchA = makeBranch('AA-0001');
    $branchB = makeBranch('BB-0001');
    $yearA = appYear($branchA);
    appYear($branchB);
    $student = appStudent($branchA);
    appEnroll($student, $yearA);
    $guardian = applicationGuardian($student);

    Sanctum::actingAs($guardian);
    $first = $this->postJson('/api/v1/me/transfer-applications', [
        'student_id' => $student->id, 'to_branch_id' => $branchB->id, 'reason' => 'moving',
    ])->assertCreated()->json('data.id');

    $this->postJson("/api/v1/me/transfer-applications/{$first}/withdraw")->assertOk();
    expect(TransferApplication::findOrFail($first)->status->value)->toBe('withdrawn');

    // A fresh application can then be declined by the destination.
    $second = $this->postJson('/api/v1/me/transfer-applications', [
        'student_id' => $student->id, 'to_branch_id' => $branchB->id, 'reason' => 'moving again',
    ])->assertCreated()->json('data.id');

    // Only the destination decides; the sending school's director cannot.
    Sanctum::actingAs(directorOf($branchA));
    $this->withHeaders(branchContext($branchA))
        ->postJson("/api/v1/transfer-applications/{$second}/decline", ['decline_note' => 'no'])
        ->assertForbidden();

    Sanctum::actingAs(directorOf($branchB));
    $this->withHeaders(branchContext($branchB))
        ->postJson("/api/v1/transfer-applications/{$second}/decline", ['decline_note' => 'No seats available in that grade.'])
        ->assertOk();

    expect(TransferApplication::findOrFail($second)->refresh()->decline_note)->not->toBeNull();
});

<?php

use App\Enums\InvoiceStatus;
use App\Jobs\SendFeeNotifications;
use App\Mail\InvoiceNoticeMail;
use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\FeeStructure;
use App\Models\GradeLevel;
use App\Models\Invoice;
use App\Models\ParentProfile;
use App\Models\SchoolProgram;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentGuardian;
use App\Models\User;
use App\Services\FeeNotifier;
use App\Services\Sms\SmsClient;
use Database\Seeders\GradeLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(GradeLevelSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->sms = Mockery::spy(SmsClient::class);
    app()->instance(SmsClient::class, $this->sms);
});

function notifyYear(Branch $branch): AcademicYear
{
    return AcademicYear::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'name' => '2018 E.C.', 'status' => 'active',
    ]);
}

function notifyFee(Branch $branch, AcademicYear $year): FeeStructure
{
    return FeeStructure::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'academic_year_id' => $year->id, 'name' => 'Tuition', 'type' => 'monthly',
        'amount' => 1000, 'is_active' => true, 'due_on' => now()->addWeek()->toDateString(),
    ]);
}

/** A billed student with one linked, reachable guardian. */
function billedStudent(Branch $branch, AcademicYear $year, FeeStructure $fee, bool $smsAllowed = true): Student
{
    $student = Student::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => 'Abel'.uniqid(), 'father_name' => 'Test', 'gender' => 'male',
    ]);
    StudentEnrollment::create([
        'student_id' => $student->id, 'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'academic_year_id' => $year->id, 'school_program_id' => SchoolProgram::defaultFor($branch)->id,
        'grade_level_id' => GradeLevel::first()->id, 'status' => 'active', 'enrolled_on' => now(),
    ]);
    Invoice::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'student_id' => $student->id, 'academic_year_id' => $year->id,
        'fee_structure_id' => $fee->id, 'title' => $fee->name, 'amount' => $fee->amount,
        'amount_paid' => 0, 'status' => InvoiceStatus::Unpaid->value,
        'due_date' => $fee->due_on,
    ]);

    $parent = ParentProfile::create([
        'user_id' => User::factory()->create()->id,
        'first_name' => 'Guardian', 'father_name' => 'Of'.$student->first_name,
    ]);
    StudentGuardian::create([
        'student_id' => $student->id, 'parent_id' => $parent->id,
        'relationship' => 'father', 'is_active' => true, 'can_receive_sms' => $smsAllowed,
    ]);

    return $student;
}

it('previews recipient counts per audience and channel', function () {
    $branch = makeBranch();
    $year = notifyYear($branch);
    $fee = notifyFee($branch, $year);
    billedStudent($branch, $year, $fee);
    billedStudent($branch, $year, $fee);
    Sanctum::actingAs(directorOf($branch));

    $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/fee-structures/{$fee->id}/notify-preview?parents=1&students=1")
        ->assertOk()
        ->assertJsonPath('data.invoices', 2)
        ->assertJsonPath('data.parents.recipients', 2)
        ->assertJsonPath('data.parents.sms', 2)
        ->assertJsonPath('data.parents.email', 2)
        // Neither student has a portal account of their own.
        ->assertJsonPath('data.students.recipients', 0);
});

it('queues the send and rejects an empty audience', function () {
    Queue::fake();

    $branch = makeBranch();
    $year = notifyYear($branch);
    $fee = notifyFee($branch, $year);
    billedStudent($branch, $year, $fee);
    Sanctum::actingAs(directorOf($branch));

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/fee-structures/{$fee->id}/notify", ['parents' => true])
        ->assertOk();

    Queue::assertPushed(SendFeeNotifications::class, fn ($job) => $job->feeStructureId === $fee->id && $job->parents && ! $job->students);

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/fee-structures/{$fee->id}/notify", [])
        ->assertStatus(422);
});

it('denies the notify lane to staff of another branch', function () {
    $branch = makeBranch();
    $other = makeBranch('AA-0002');
    $year = notifyYear($branch);
    $fee = notifyFee($branch, $year);
    Sanctum::actingAs(directorOf($other));

    $this->withHeaders(branchContext($other))
        ->postJson("/api/v1/fee-structures/{$fee->id}/notify", ['parents' => true])
        ->assertForbidden();
});

it('sends SMS and email per preference and skips sms-blocked guardian links', function () {
    Mail::fake();

    $branch = makeBranch();
    $year = notifyYear($branch);
    $fee = notifyFee($branch, $year);
    billedStudent($branch, $year, $fee, smsAllowed: true);
    billedStudent($branch, $year, $fee, smsAllowed: false);

    $sent = app(FeeNotifier::class)->send($fee, parents: true, students: false);

    expect($sent['sms'])->toBe(1)->and($sent['email'])->toBe(2);
    $this->sms->shouldHaveReceived('send')->once();
    Mail::assertSent(InvoiceNoticeMail::class, 2);
});

it('skips settled invoices entirely', function () {
    $branch = makeBranch();
    $year = notifyYear($branch);
    $fee = notifyFee($branch, $year);
    $student = billedStudent($branch, $year, $fee);
    Invoice::where('student_id', $student->id)->update([
        'status' => InvoiceStatus::Paid->value, 'amount_paid' => 1000,
    ]);
    Sanctum::actingAs(directorOf($branch));

    $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/fee-structures/{$fee->id}/notify-preview?parents=1")
        ->assertOk()
        ->assertJsonPath('data.invoices', 0)
        ->assertJsonPath('data.parents.recipients', 0);
});

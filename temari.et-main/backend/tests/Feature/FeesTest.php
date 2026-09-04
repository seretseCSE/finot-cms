<?php

use App\Actions\SaveAcademicYearAction;
use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Branch;
use App\Models\FeeStructure;
use App\Models\GradeLevel;
use App\Models\Invoice;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\SchoolProgram;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\Sms\SmsClient;
use App\Support\Ethiopia;
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

function enrolledIn(Branch $branch, AcademicYear $year, int $gradeLevelId, string $first): Student
{
    $student = Student::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => $first, 'father_name' => 'Test', 'gender' => 'male',
    ]);
    StudentEnrollment::create([
        'student_id' => $student->id, 'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'academic_year_id' => $year->id, 'school_program_id' => SchoolProgram::defaultFor($branch)->id,
        'grade_level_id' => $gradeLevelId, 'status' => 'active',
        'section_id' => $branch->sections()->create([
            'school_id' => $branch->school_id, 'grade_level_id' => $gradeLevelId, 'name' => "S{$first}",
        ])->id,
        'enrolled_on' => now(),
    ]);

    return $student;
}

function invoiceFor(Branch $branch, AcademicYear $year, Student $student, float $amount = 1000): Invoice
{
    return Invoice::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id, 'student_id' => $student->id,
        'academic_year_id' => $year->id, 'title' => 'Tuition', 'amount' => $amount,
        'amount_paid' => 0, 'status' => 'unpaid',
    ]);
}

it('lets a director create a fee structure', function () {
    $branch = makeBranch();
    $year = (new SaveAcademicYearAction())->execute($branch, ['name' => '2017 E.C.', 'status' => 'active']);
    Sanctum::actingAs(directorOf($branch));

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/fee-structures', [
            'academic_year_id' => $year->id,
            'name' => 'Tuition',
            'amount' => 1500,
            'type' => 'semester',
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Tuition')
        ->assertJsonPath('data.amount', '1500.00');
});

it('rejects a fee structure for an academic year in another branch', function () {
    $branchA = makeBranch('AA-0001');
    $branchB = makeBranch('AA-0002');
    $yearB = (new SaveAcademicYearAction())->execute($branchB, ['name' => '2017 E.C.']);
    Sanctum::actingAs(directorOf($branchA));

    $this->withHeaders(branchContext($branchA))
        ->postJson('/api/v1/fee-structures', [
            'academic_year_id' => $yearB->id, 'name' => 'X', 'amount' => 10, 'type' => 'semester',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('academic_year_id');
});

it('generates invoices for actively enrolled students and is idempotent', function () {
    $branch = makeBranch();
    $year = (new SaveAcademicYearAction())->execute($branch, ['name' => '2017 E.C.', 'status' => 'active']);
    $g1 = GradeLevel::where('code', 'G1')->value('id');
    enrolledIn($branch, $year, $g1, 'Abel');
    enrolledIn($branch, $year, $g1, 'Bini');

    $fee = FeeStructure::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id, 'academic_year_id' => $year->id,
        'name' => 'Tuition', 'amount' => 1000, 'type' => 'semester',
    ]);
    $fee->gradeLevels()->sync([$g1]);

    Sanctum::actingAs(directorOf($branch));

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/fee-structures/{$fee->id}/generate-invoices", [])
        ->assertOk()
        ->assertJsonPath('meta.created', 2);

    // Re-running creates nothing new.
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/fee-structures/{$fee->id}/generate-invoices", [])
        ->assertOk()
        ->assertJsonPath('meta.created', 0);

    expect(Invoice::where('fee_structure_id', $fee->id)->count())->toBe(2);
});

it('creates a manual invoice linked to a fee and blocks duplicates', function () {
    $branch = makeBranch();
    $year = (new SaveAcademicYearAction())->execute($branch, ['name' => '2017 E.C.', 'status' => 'active']);
    $student = Student::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => 'Abel', 'father_name' => 'G', 'gender' => 'male',
    ]);
    $fee = FeeStructure::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id, 'academic_year_id' => $year->id,
        'name' => 'Tuition', 'amount' => 1000, 'type' => 'semester',
    ]);
    Sanctum::actingAs(directorOf($branch));

    $payload = [
        'student_id' => $student->id, 'academic_year_id' => $year->id,
        'fee_structure_id' => $fee->id, 'title' => 'Tuition', 'amount' => 1000,
    ];

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/invoices', $payload)
        ->assertCreated();

    expect(Invoice::where('fee_structure_id', $fee->id)->where('student_id', $student->id)->count())->toBe(1);

    // The same fee × student × term must not be billed twice.
    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/invoices', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors('fee_structure_id');

    // A fee from another academic year cannot anchor the invoice.
    $otherYear = (new SaveAcademicYearAction())->execute($branch, ['name' => '2018 E.C.']);
    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/invoices', [...$payload, 'academic_year_id' => $otherYear->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('fee_structure_id');
});

it('filters invoices by the account payments landed in', function () {
    $branch = makeBranch();
    $year = (new SaveAcademicYearAction())->execute($branch, ['name' => '2017 E.C.', 'status' => 'active']);
    $student = Student::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => 'Abel', 'father_name' => 'G', 'gender' => 'male',
    ]);
    $bank = Bank::create(['code' => 'cbe-x', 'name' => 'CBE', 'type' => 'bank']);
    $account = BankAccount::create([
        'school_id' => $branch->school_id, 'bank_id' => $bank->id,
        'account_name' => 'Main', 'account_number' => '1000100063', 'is_active' => true,
    ]);
    $account->branches()->attach($branch->id, ['is_active' => true]);

    $paidInto = invoiceFor($branch, $year, $student, 500);
    invoiceFor($branch, $year, $student, 700); // no payment into the account

    Sanctum::actingAs(directorOf($branch));

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/invoices/{$paidInto->id}/payments", [
            'amount' => 500, 'method' => 'bank_transfer', 'bank_account_id' => $account->id,
        ])
        ->assertCreated();

    $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/invoices?bank_account_id={$account->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $paidInto->id);
});

it('records a payment and tracks the balance', function () {
    $branch = makeBranch();
    $year = (new SaveAcademicYearAction())->execute($branch, ['name' => '2017 E.C.', 'status' => 'active']);
    $student = Student::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => 'Abel', 'father_name' => 'G', 'gender' => 'male',
    ]);
    $invoice = invoiceFor($branch, $year, $student, 1000);
    Sanctum::actingAs(directorOf($branch));

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/invoices/{$invoice->id}/payments", ['amount' => 600, 'method' => 'wallet'])
        ->assertCreated()
        ->assertJsonPath('meta.invoice.status', 'partial')
        ->assertJsonPath('meta.invoice.balance', '400.00');

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/invoices/{$invoice->id}/payments", ['amount' => 400, 'method' => 'cash'])
        ->assertCreated()
        ->assertJsonPath('meta.invoice.status', 'paid')
        ->assertJsonPath('meta.invoice.balance', '0.00');

    expect((float) $invoice->refresh()->amount_paid)->toBe(1000.0);
});

it('rejects a payment that exceeds the outstanding balance', function () {
    $branch = makeBranch();
    $year = (new SaveAcademicYearAction())->execute($branch, ['name' => '2017 E.C.', 'status' => 'active']);
    $student = Student::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => 'Abel', 'father_name' => 'G', 'gender' => 'male',
    ]);
    $invoice = invoiceFor($branch, $year, $student, 500);
    Sanctum::actingAs(directorOf($branch));

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/invoices/{$invoice->id}/payments", ['amount' => 600, 'method' => 'cash'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('amount');

    expect(Payment::count())->toBe(0);
});

it('lets a finance officer record payments', function () {
    $branch = makeBranch();
    $year = (new SaveAcademicYearAction())->execute($branch, ['name' => '2017 E.C.', 'status' => 'active']);
    $student = Student::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => 'Abel', 'father_name' => 'G', 'gender' => 'male',
    ]);
    $invoice = invoiceFor($branch, $year, $student, 300);

    $finance = User::factory()->create();
    Membership::create([
        'user_id' => $finance->id, 'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'role' => Role::FinanceOfficer->value, 'scope' => Role::FinanceOfficer->scope()->value, 'is_active' => true,
    ]);
    Sanctum::actingAs($finance);

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/invoices/{$invoice->id}/payments", ['amount' => 300, 'method' => 'wallet'])
        ->assertCreated();
});

it('forbids recording a payment on an invoice in another branch', function () {
    $branchA = makeBranch('AA-0001');
    $branchB = makeBranch('AA-0002');
    $yearB = (new SaveAcademicYearAction())->execute($branchB, ['name' => '2017 E.C.', 'status' => 'active']);
    $studentB = Student::create([
        'school_id' => $branchB->school_id, 'branch_id' => $branchB->id,
        'first_name' => 'Z', 'father_name' => 'Q', 'gender' => 'female',
    ]);
    $invoiceB = invoiceFor($branchB, $yearB, $studentB, 200);
    Sanctum::actingAs(directorOf($branchA));

    $this->withHeaders(branchContext($branchA))
        ->postJson("/api/v1/invoices/{$invoiceB->id}/payments", ['amount' => 100, 'method' => 'cash'])
        ->assertForbidden();
});

it('forbids a teacher from viewing fee structures', function () {
    $branch = makeBranch();
    $teacher = User::factory()->create();
    Membership::create([
        'user_id' => $teacher->id, 'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'role' => Role::Teacher->value, 'scope' => Role::Teacher->scope()->value, 'is_active' => true,
    ]);
    Sanctum::actingAs($teacher);

    $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/fee-structures')
        ->assertForbidden();
});

it('stores a scheduled fee with penalty, notifications and applicable grades', function () {
    $branch = makeBranch();
    $year = (new SaveAcademicYearAction())->execute($branch, ['name' => '2017 E.C.', 'status' => 'active']);
    $g1 = GradeLevel::where('code', 'G1')->value('id');
    $g2 = GradeLevel::where('code', 'G2')->value('id');
    Sanctum::actingAs(directorOf($branch));

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/fee-structures', [
            'academic_year_id' => $year->id,
            'name' => 'Monthly tuition',
            'type' => 'monthly',
            'amount' => 1200,
            'grade_level_ids' => [$g1, $g2],
            'starts_on' => '2026-09-01',
            'due_on' => '2026-09-30',
            'notify_parents' => true,
            'notify_students' => true,
            'penalty_type' => 'incremental',
            'penalty_amount' => 50,
            'penalty_increment_days' => 7,
        ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'monthly')
        ->assertJsonPath('data.penalty_type', 'incremental')
        ->assertJsonPath('data.penalty_increment_days', 7)
        ->assertJsonCount(2, 'data.grade_levels');
});

it('keeps registration fees minimal: schedule and penalty fields are rejected', function () {
    $branch = makeBranch();
    $year = (new SaveAcademicYearAction())->execute($branch, ['name' => '2017 E.C.', 'status' => 'active']);
    Sanctum::actingAs(directorOf($branch));

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/fee-structures', [
            'academic_year_id' => $year->id,
            'name' => 'Registration',
            'type' => 'registration',
            'amount' => 500,
            'due_on' => '2026-09-30',
            'penalty_type' => 'fixed',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['due_on', 'penalty_type']);

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/fee-structures', [
            'academic_year_id' => $year->id,
            'name' => 'Registration',
            'type' => 'registration',
            'amount' => 500,
        ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'registration');
});

it('requires increment days for incremental penalties', function () {
    $branch = makeBranch();
    $year = (new SaveAcademicYearAction())->execute($branch, ['name' => '2017 E.C.', 'status' => 'active']);
    Sanctum::actingAs(directorOf($branch));

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/fee-structures', [
            'academic_year_id' => $year->id,
            'name' => 'One-time',
            'type' => 'one_time',
            'amount' => 800,
            'penalty_type' => 'incremental',
            'penalty_amount' => 25,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['penalty_increment_days']);
});

it('generates invoices only for the fee\'s applicable grades and stamps the due date', function () {
    $branch = makeBranch();
    $year = (new SaveAcademicYearAction())->execute($branch, ['name' => '2017 E.C.', 'status' => 'active']);
    $g1 = GradeLevel::where('code', 'G1')->value('id');
    $g2 = GradeLevel::where('code', 'G2')->value('id');
    enrolledIn($branch, $year, $g1, 'Abel');
    enrolledIn($branch, $year, $g2, 'Bini');

    $fee = FeeStructure::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id, 'academic_year_id' => $year->id,
        'name' => 'G1 fee', 'amount' => 700, 'type' => 'one_time', 'due_on' => '2026-10-15',
    ]);
    $fee->gradeLevels()->sync([$g1]);

    Sanctum::actingAs(directorOf($branch));

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/fee-structures/{$fee->id}/generate-invoices", [])
        ->assertOk()
        ->assertJsonPath('meta.created', 1);

    expect(Invoice::where('fee_structure_id', $fee->id)->first()->due_date->toDateString())->toBe('2026-10-15');
});

it('toggles reminder flags from the dedicated endpoint, except on registration fees', function () {
    $branch = makeBranch();
    $year = (new SaveAcademicYearAction())->execute($branch, ['name' => '2017 E.C.', 'status' => 'active']);
    Sanctum::actingAs(directorOf($branch));

    $fee = FeeStructure::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id, 'academic_year_id' => $year->id,
        'name' => 'Monthly', 'amount' => 100, 'type' => 'monthly', 'notify_parents' => true,
    ]);

    $this->withHeaders(branchContext($branch))
        ->patchJson("/api/v1/fee-structures/{$fee->id}/notifications", ['notify_parents' => false, 'notify_students' => true])
        ->assertOk()
        ->assertJsonPath('data.notify_parents', false)
        ->assertJsonPath('data.notify_students', true);

    $registration = FeeStructure::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id, 'academic_year_id' => $year->id,
        'name' => 'Registration', 'amount' => 100, 'type' => 'registration', 'notify_parents' => false,
    ]);

    $this->withHeaders(branchContext($branch))
        ->patchJson("/api/v1/fee-structures/{$registration->id}/notifications", ['notify_parents' => true])
        ->assertStatus(422);
});

it('refuses a manual invoice that would be born overdue', function () {
    $branch = makeBranch();
    $year = (new SaveAcademicYearAction())->execute($branch, ['name' => '2017 E.C.', 'status' => 'active']);
    $student = Student::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => 'Abel', 'father_name' => 'G', 'gender' => 'male',
    ]);
    Sanctum::actingAs(directorOf($branch));

    $payload = [
        'student_id' => $student->id, 'academic_year_id' => $year->id,
        'title' => 'Lab fee', 'amount' => 300,
    ];

    // Build both dates on ADDIS wall time — the clock NotPastDay judges on.
    // Using now() (UTC) here makes this test fail every day between 21:00 and
    // 24:00 UTC, when the UTC date is still the Addis day before.
    $today = Ethiopia::today();

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/invoices', [...$payload, 'due_date' => Ethiopia::now()->subDay()->toDateString()])
        ->assertStatus(422)
        ->assertJsonValidationErrors('due_date');

    // Due today (or later) is fine — billing late means due today at the earliest.
    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/invoices', [...$payload, 'due_date' => $today])
        ->assertCreated();
});

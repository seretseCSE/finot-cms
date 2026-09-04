<?php

use App\Actions\GenerateInvoicesAction;
use App\Actions\SaveAcademicYearAction;
use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\FeeStructure;
use App\Models\GradeLevel;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\StudentEnrollment;
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

function regFeesYear(Branch $branch): AcademicYear
{
    return (new SaveAcademicYearAction())->execute($branch, ['name' => '2017 E.C.', 'status' => 'active']);
}

function feeStructure(Branch $branch, AcademicYear $year, string $name, float $amount, array $gradeIds = []): FeeStructure
{
    $structure = FeeStructure::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'academic_year_id' => $year->id, 'name' => $name, 'type' => 'one_time',
        'amount' => $amount, 'is_active' => true,
    ]);
    if ($gradeIds !== []) {
        $structure->gradeLevels()->sync($gradeIds);
    }

    return $structure;
}

it('lists only the fee structures applicable to a year and grade', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));
    $year = regFeesYear($branch);
    $gradeOne = GradeLevel::where('code', 'G1')->value('id');
    $gradeTwo = GradeLevel::where('code', 'G2')->value('id');

    feeStructure($branch, $year, 'All grades fee', 500);            // empty pivot = all
    feeStructure($branch, $year, 'G1 only fee', 300, [$gradeOne]);
    feeStructure($branch, $year, 'G2 only fee', 400, [$gradeTwo]);

    $response = $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/fee-structures/applicable?academic_year_id={$year->id}&grade_level_id={$gradeOne}")
        ->assertOk();

    expect(collect($response->json('data'))->pluck('name')->all())
        ->toEqualCanonicalizing(['All grades fee', 'G1 only fee']);
});

it('issues, collects and grants a scholarship on fees during registration', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));
    $year = regFeesYear($branch);
    $grade = GradeLevel::where('code', 'G1')->first();

    $registration = feeStructure($branch, $year, 'Registration fee', 500);
    $tuition = feeStructure($branch, $year, 'Tuition', 2000);

    $response = $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/students', [
            'first_name' => 'Bini', 'father_name' => 'Worku', 'gender' => 'male',
            'academic_year_id' => $year->id,
            'grade_level_id' => $grade->id,
            'fee_structure_ids' => [$registration->id, $tuition->id],
            'pay_now' => [['fee_structure_id' => $registration->id, 'method' => 'cash']],
            'scholarships' => [['fee_structure_id' => $tuition->id, 'reason' => 'Merit scholarship']],
            'guardians' => [guardianPayload()],
        ])->assertCreated();

    $studentId = $response->json('data.id');
    $invoices = Invoice::where('student_id', $studentId)->get()->keyBy('fee_structure_id');

    expect($invoices)->toHaveCount(2);
    expect($invoices[$registration->id]->status->value)->toBe('paid');
    expect((float) $invoices[$registration->id]->amount_paid)->toBe(500.0);
    expect($invoices[$tuition->id]->status->value)->toBe('scholarship');
    expect($invoices[$tuition->id]->scholarship_reason)->toBe('Merit scholarship');
    expect($invoices[$tuition->id]->netAmount())->toBe(0.0);
});

it('rejects registration fees that do not apply to the enrollment', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));
    $year = regFeesYear($branch);
    $gradeOne = GradeLevel::where('code', 'G1')->first();
    $gradeTwo = GradeLevel::where('code', 'G2')->value('id');

    $wrongGradeFee = feeStructure($branch, $year, 'G2 fee', 700, [$gradeTwo]);

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/students', [
            'first_name' => 'Selam', 'father_name' => 'Bekele', 'gender' => 'female',
            'academic_year_id' => $year->id,
            'grade_level_id' => $gradeOne->id,
            'fee_structure_ids' => [$wrongGradeFee->id],
            'guardians' => [guardianPayload()],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('fee_structure_ids');
});

it('applies a percentage discount and judges payments against the net', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));
    $year = regFeesYear($branch);

    $invoice = Invoice::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'student_id' => Student::create([
            'school_id' => $branch->school_id, 'branch_id' => $branch->id,
            'first_name' => 'D', 'father_name' => 'E', 'gender' => 'male',
        ])->id,
        'academic_year_id' => $year->id, 'title' => 'Tuition', 'amount' => 1000,
        'amount_paid' => 0, 'status' => 'unpaid',
    ]);

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/invoices/{$invoice->id}/discount", [
            'discount_type' => 'percentage', 'discount_value' => 40, 'scholarship_reason' => 'Sibling discount',
        ])
        ->assertOk()
        ->assertJsonPath('data.net_amount', '600.00');

    // Paying more than the discounted amount is rejected…
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/invoices/{$invoice->id}/payments", ['amount' => 700, 'method' => 'cash'])
        ->assertStatus(422);

    // …and paying exactly the net settles the invoice.
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/invoices/{$invoice->id}/payments", ['amount' => 600, 'method' => 'cash'])
        ->assertCreated();

    expect($invoice->fresh()->status->value)->toBe('paid');
});

it('refuses payments on a scholarship invoice and refuses discounts below recorded payments', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));
    $year = regFeesYear($branch);

    $student = Student::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => 'F', 'father_name' => 'G', 'gender' => 'female',
    ]);

    $scholarshipInvoice = Invoice::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id, 'student_id' => $student->id,
        'academic_year_id' => $year->id, 'title' => 'Books', 'amount' => 300,
        'amount_paid' => 0, 'status' => 'unpaid',
    ]);

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/invoices/{$scholarshipInvoice->id}/discount", [
            'discount_type' => 'full_scholarship', 'scholarship_reason' => 'Scholarship',
        ])->assertOk();

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/invoices/{$scholarshipInvoice->id}/payments", ['amount' => 100, 'method' => 'cash'])
        ->assertStatus(422);

    // A part-paid invoice cannot be discounted below what has been collected.
    $partPaid = Invoice::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id, 'student_id' => $student->id,
        'academic_year_id' => $year->id, 'title' => 'Uniform', 'amount' => 1000,
        'amount_paid' => 0, 'status' => 'unpaid',
    ]);
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/invoices/{$partPaid->id}/payments", ['amount' => 800, 'method' => 'cash'])
        ->assertCreated();

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/invoices/{$partPaid->id}/discount", [
            'discount_type' => 'percentage', 'discount_value' => 50, 'scholarship_reason' => 'Late scholarship',
        ])->assertStatus(422);
});

it('generates a per-enrollment invoice idempotently', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));
    $year = regFeesYear($branch);
    $grade = GradeLevel::where('code', 'G1')->first();
    $fee = feeStructure($branch, $year, 'Tuition', 1500);

    // Registering the same selection twice (e.g. a retried request) must not
    // duplicate the invoice for the same fee structure + student.
    $response = $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/students', [
            'first_name' => 'H', 'father_name' => 'I', 'gender' => 'male',
            'academic_year_id' => $year->id, 'grade_level_id' => $grade->id,
            'fee_structure_ids' => [$fee->id],
            'guardians' => [guardianPayload()],
        ])->assertCreated();

    $studentId = $response->json('data.id');
    $enrollment = StudentEnrollment::firstWhere('student_id', $studentId);

    $action = app(GenerateInvoicesAction::class);
    $action->executeForEnrollment($fee, $enrollment);
    $action->executeForEnrollment($fee, $enrollment);

    expect(Invoice::where('student_id', $studentId)->count())->toBe(1);
});

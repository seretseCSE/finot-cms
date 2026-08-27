<?php

use App\Actions\EnrollStudentAction;
use App\Actions\GenerateInvoicesAction;
use App\Enums\Role as RoleEnum;
use App\Models\AcademicYear;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\FeeConcession;
use App\Models\FeeStructure;
use App\Models\GradeLevel;
use App\Models\Invoice;
use App\Models\ParentProfile;
use App\Models\SchoolProgram;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentGuardian;
use App\Models\User;
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

function concessionYear(Branch $branch): AcademicYear
{
    return AcademicYear::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'name' => '2018 E.C.', 'status' => 'active',
    ]);
}

function concessionStudent(Branch $branch, string $first = 'Abel'): Student
{
    return Student::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => $first, 'father_name' => 'Test', 'gender' => 'male',
    ]);
}

function activeEnrollment(Branch $branch, AcademicYear $year, Student $student): StudentEnrollment
{
    return StudentEnrollment::create([
        'student_id' => $student->id, 'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'academic_year_id' => $year->id, 'school_program_id' => SchoolProgram::defaultFor($branch)->id,
        'grade_level_id' => GradeLevel::first()->id, 'status' => 'active', 'enrolled_on' => now(),
    ]);
}

function tuitionFee(Branch $branch, AcademicYear $year, float $amount = 1000): FeeStructure
{
    return FeeStructure::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'academic_year_id' => $year->id, 'name' => 'Tuition', 'type' => 'monthly',
        'amount' => $amount, 'is_active' => true,
    ]);
}

function guardianOf(Student $student, ?User $user = null): ParentProfile
{
    $parent = ParentProfile::create([
        'user_id' => ($user ?? User::factory()->create())->id,
        'first_name' => 'Guardian', 'father_name' => 'Of'.$student->first_name,
    ]);
    StudentGuardian::create([
        'student_id' => $student->id, 'parent_id' => $parent->id,
        'relationship' => 'father', 'is_active' => true,
    ]);

    return $parent;
}

it('grants a manual student concession and stamps generated invoices', function () {
    $branch = makeBranch();
    $year = concessionYear($branch);
    $student = concessionStudent($branch);
    activeEnrollment($branch, $year, $student);
    Sanctum::actingAs(directorOf($branch));

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/fee-concessions', [
            'student_id' => $student->id,
            'category' => 'hardship',
            'discount_type' => 'percentage',
            'discount_value' => 25,
            'academic_year_id' => $year->id,
            'reason' => 'Family hardship',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'active');

    app(GenerateInvoicesAction::class)->execute(tuitionFee($branch, $year));

    $invoice = Invoice::where('student_id', $student->id)->firstOrFail();
    expect($invoice->discount_type->value)->toBe('percentage')
        ->and((float) $invoice->discount_value)->toBe(25.0)
        ->and($invoice->netAmount())->toBe(750.0)
        ->and($invoice->fee_concession_id)->not->toBeNull();
});

it('applies a guardian-level lifetime concession to every linked child', function () {
    $branch = makeBranch();
    $year = concessionYear($branch);
    $student = concessionStudent($branch);
    activeEnrollment($branch, $year, $student);
    $parent = guardianOf($student);
    Sanctum::actingAs(directorOf($branch));

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/fee-concessions', [
            'parent_id' => $parent->id,
            'category' => 'other',
            'discount_type' => 'fixed',
            'discount_value' => 200,
        ])
        ->assertCreated();

    app(GenerateInvoicesAction::class)->execute(tuitionFee($branch, $year));

    expect(Invoice::where('student_id', $student->id)->firstOrFail()->netAmount())->toBe(800.0);
});

it('never stacks concessions — the single largest cut wins', function () {
    $branch = makeBranch();
    $year = concessionYear($branch);
    $student = concessionStudent($branch);
    activeEnrollment($branch, $year, $student);
    $parent = guardianOf($student);
    Sanctum::actingAs(directorOf($branch));

    foreach ([
        ['student_id' => $student->id, 'category' => 'merit', 'discount_type' => 'percentage', 'discount_value' => 10],
        ['parent_id' => $parent->id, 'category' => 'other', 'discount_type' => 'percentage', 'discount_value' => 30],
    ] as $payload) {
        $this->withHeaders(branchContext($branch))
            ->postJson('/api/v1/fee-concessions', $payload)
            ->assertCreated();
    }

    app(GenerateInvoicesAction::class)->execute(tuitionFee($branch, $year));

    // 30% wins outright — never 10% + 30%.
    expect(Invoice::where('student_id', $student->id)->firstOrFail()->netAmount())->toBe(700.0);
});

it('settles the bill outright on a full-scholarship concession', function () {
    $branch = makeBranch();
    $year = concessionYear($branch);
    $student = concessionStudent($branch);
    activeEnrollment($branch, $year, $student);
    Sanctum::actingAs(directorOf($branch));

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/fee-concessions', [
            'student_id' => $student->id,
            'category' => 'scholarship',
            'discount_type' => 'full_scholarship',
            'reason' => 'Merit scholarship',
        ])
        ->assertCreated();

    app(GenerateInvoicesAction::class)->execute(tuitionFee($branch, $year));

    expect(Invoice::where('student_id', $student->id)->firstOrFail()->status->value)->toBe('scholarship');
});

it('files a pending sibling suggestion on enrollment when the policy is on', function () {
    $branch = makeBranch();
    $branch->school->update(['settings' => ['sibling_discount_percent' => 15, 'sibling_min_children' => 2]]);
    $year = concessionYear($branch);

    // First child enrolled; both children share one guardian.
    $first = concessionStudent($branch, 'Sara');
    activeEnrollment($branch, $year, $first);
    $parent = guardianOf($first);

    $second = concessionStudent($branch, 'Binyam');
    StudentGuardian::create([
        'student_id' => $second->id, 'parent_id' => $parent->id,
        'relationship' => 'father', 'is_active' => true,
    ]);

    app(EnrollStudentAction::class)->execute($second, [
        'academic_year_id' => $year->id,
        'grade_level_id' => GradeLevel::first()->id,
    ]);

    $suggestion = FeeConcession::where('student_id', $second->id)->first();
    expect($suggestion)->not->toBeNull()
        ->and($suggestion->status->value)->toBe('pending')
        ->and($suggestion->category->value)->toBe('sibling')
        ->and((float) $suggestion->discount_value)->toBe(15.0);

    // Pending suggestions never touch a bill.
    app(GenerateInvoicesAction::class)->execute(tuitionFee($branch, $year));
    expect(Invoice::where('student_id', $second->id)->firstOrFail()->netAmount())->toBe(1000.0);
});

it('honours a BRANCH override of the sibling policy over the school default', function () {
    $branch = makeBranch();
    // School policy off — the branch opts in on its own at 20%.
    $branch->update(['settings' => ['sibling_discount_percent' => 20, 'sibling_min_children' => 2]]);
    $year = concessionYear($branch);

    $first = concessionStudent($branch, 'Hana');
    activeEnrollment($branch, $year, $first);
    $parent = guardianOf($first);

    $second = concessionStudent($branch, 'Yonas');
    StudentGuardian::create([
        'student_id' => $second->id, 'parent_id' => $parent->id,
        'relationship' => 'father', 'is_active' => true,
    ]);

    app(EnrollStudentAction::class)->execute($second, [
        'academic_year_id' => $year->id,
        'grade_level_id' => GradeLevel::first()->id,
    ]);

    $suggestion = FeeConcession::where('student_id', $second->id)->firstOrFail();
    expect($suggestion->status->value)->toBe('pending')
        ->and((float) $suggestion->discount_value)->toBe(20.0);
});

it('files a pending staff-child suggestion when a guardian is an employee', function () {
    $branch = makeBranch();
    $branch->school->update(['settings' => ['staff_child_discount_percent' => 50]]);
    $year = concessionYear($branch);

    $staffUser = User::factory()->create();
    Employee::create([
        'user_id' => $staffUser->id, 'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => 'Staff', 'father_name' => 'Member', 'gender' => 'female', 'is_active' => true,
    ]);

    $child = concessionStudent($branch, 'Lily');
    guardianOf($child, $staffUser);

    app(EnrollStudentAction::class)->execute($child, [
        'academic_year_id' => $year->id,
        'grade_level_id' => GradeLevel::first()->id,
    ]);

    $suggestion = FeeConcession::where('student_id', $child->id)->where('category', 'staff_child')->first();
    expect($suggestion)->not->toBeNull()
        ->and($suggestion->status->value)->toBe('pending')
        ->and($suggestion->source)->toBe('auto_staff');
});

it('approves a suggestion so it applies, and revoking stops future bills only', function () {
    $branch = makeBranch();
    $year = concessionYear($branch);
    $student = concessionStudent($branch);
    activeEnrollment($branch, $year, $student);

    $concession = FeeConcession::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id, 'student_id' => $student->id,
        'category' => 'sibling', 'discount_type' => 'percentage', 'discount_value' => 20,
        'academic_year_id' => $year->id, 'status' => 'pending', 'source' => 'auto_sibling',
    ]);

    Sanctum::actingAs(directorOf($branch));

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/fee-concessions/{$concession->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'active');

    app(GenerateInvoicesAction::class)->execute(tuitionFee($branch, $year));
    $invoice = Invoice::where('student_id', $student->id)->firstOrFail();
    expect($invoice->netAmount())->toBe(800.0);

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/fee-concessions/{$concession->id}/revoke")
        ->assertOk()
        ->assertJsonPath('data.status', 'revoked');

    // Billed history stays; only future invoices lose the concession.
    expect($invoice->refresh()->netAmount())->toBe(800.0);

    app(GenerateInvoicesAction::class)->execute(tuitionFee($branch, $year, 500));
    $second = Invoice::where('student_id', $student->id)->where('amount', 500)->firstOrFail();
    expect($second->netAmount())->toBe(500.0)
        ->and($second->fee_concession_id)->toBeNull();
});

it('files a registration-time concession before the first bill so it lands discounted', function () {
    $branch = makeBranch();
    $year = concessionYear($branch);
    $fee = tuitionFee($branch, $year);
    Sanctum::actingAs(directorOf($branch));

    $studentId = $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/students', [
            'first_name' => 'Sara', 'father_name' => 'Test', 'gender' => 'female',
            'academic_year_id' => $year->id,
            'grade_level_id' => GradeLevel::first()->id,
            'fee_structure_ids' => [$fee->id],
            'guardians' => [guardianPayload()],
            'concession' => [
                'category' => 'hardship',
                'discount_type' => 'percentage',
                'discount_value' => 20,
                'reason' => 'Filed at registration',
            ],
        ])
        ->assertCreated()
        ->json('data.id');

    // The wizard-time concession existed before invoicing, so the very
    // first bill already carries the 20% cut.
    $invoice = Invoice::where('student_id', $studentId)->where('fee_structure_id', $fee->id)->firstOrFail();
    expect($invoice->netAmount())->toBe(800.0)
        ->and($invoice->fee_concession_id)->not->toBeNull();

    $concession = FeeConcession::where('student_id', $studentId)->firstOrFail();
    expect($concession->status->value)->toBe('active')
        ->and($concession->academic_year_id)->toBe($year->id);
});

it('rejects a registration-time concession from staff without the fees authority', function () {
    $branch = makeBranch();
    $year = concessionYear($branch);
    Sanctum::actingAs(memberOf($branch, RoleEnum::Registrar));

    // Registrars register students every day — but a standing discount is a
    // money decision they don't hold.
    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/students', [
            'first_name' => 'Sara', 'father_name' => 'Test', 'gender' => 'female',
            'academic_year_id' => $year->id,
            'grade_level_id' => GradeLevel::first()->id,
            'guardians' => [guardianPayload()],
            'concession' => [
                'category' => 'hardship',
                'discount_type' => 'percentage',
                'discount_value' => 20,
            ],
        ])
        ->assertForbidden();
});

it('re-prices open invoices on request, leaving paid history alone', function () {
    $branch = makeBranch();
    $year = concessionYear($branch);
    $student = concessionStudent($branch);
    activeEnrollment($branch, $year, $student);

    app(GenerateInvoicesAction::class)->execute(tuitionFee($branch, $year));
    $open = Invoice::where('student_id', $student->id)->firstOrFail();

    $settled = Invoice::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'student_id' => $student->id, 'academic_year_id' => $year->id,
        'title' => 'Old bill', 'amount' => 500, 'amount_paid' => 500, 'status' => 'paid',
    ]);

    Sanctum::actingAs(directorOf($branch));

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/fee-concessions', [
            'student_id' => $student->id,
            'category' => 'hardship',
            'discount_type' => 'percentage',
            'discount_value' => 50,
            'apply_to_open_invoices' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('meta.repriced_invoices', 1);

    expect($open->refresh()->netAmount())->toBe(500.0)
        ->and($open->fee_concession_id)->not->toBeNull();

    // Paid history is frozen — retro-application never rewrites it.
    expect($settled->refresh()->discount_type->value)->toBe('none')
        ->and((float) $settled->amount_paid)->toBe(500.0);
});

it('keeps concessions inside their school — another school sees and touches nothing', function () {
    $branchA = makeBranch('AA-0001');
    $branchB = makeBranch('BB-0001');
    $yearA = concessionYear($branchA);
    $studentA = concessionStudent($branchA);
    activeEnrollment($branchA, $yearA, $studentA);

    $concession = FeeConcession::create([
        'school_id' => $branchA->school_id, 'branch_id' => $branchA->id, 'student_id' => $studentA->id,
        'category' => 'merit', 'discount_type' => 'percentage', 'discount_value' => 10,
        'status' => 'pending', 'source' => 'manual',
    ]);

    Sanctum::actingAs(directorOf($branchB));

    $this->withHeaders(branchContext($branchB))
        ->getJson('/api/v1/fee-concessions')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->withHeaders(branchContext($branchB))
        ->postJson("/api/v1/fee-concessions/{$concession->id}/approve")
        ->assertForbidden();
});

it('requires the collection account for bank and wallet payments when one exists', function () {
    $branch = makeBranch();
    $year = concessionYear($branch);
    $student = concessionStudent($branch);
    activeEnrollment($branch, $year, $student);

    $bank = Bank::create(['code' => 'cbe', 'name' => 'CBE', 'type' => 'bank', 'is_active' => true]);
    $account = BankAccount::create([
        'school_id' => $branch->school_id, 'bank_id' => $bank->id,
        'account_name' => 'Unity Main', 'account_number' => '1000123', 'is_active' => true,
    ]);
    $account->branches()->attach($branch->id, ['is_active' => true]);

    $invoice = Invoice::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id, 'student_id' => $student->id,
        'academic_year_id' => $year->id, 'title' => 'Tuition', 'amount' => 1000,
        'amount_paid' => 0, 'status' => 'unpaid',
    ]);

    Sanctum::actingAs(directorOf($branch));

    // Bank transfer without naming where the money landed → rejected.
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/invoices/{$invoice->id}/payments", [
            'amount' => 400, 'method' => 'bank_transfer',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('bank_account_id');

    // Cash never takes an account.
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/invoices/{$invoice->id}/payments", [
            'amount' => 400, 'method' => 'cash', 'bank_account_id' => $account->id,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('bank_account_id');

    // Named account → recorded with the snapshot.
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/invoices/{$invoice->id}/payments", [
            'amount' => 400, 'method' => 'bank_transfer', 'bank_account_id' => $account->id,
        ])
        ->assertCreated()
        ->assertJsonPath('data.bank_account.id', $account->id);
});

it('filters invoices by overdue and finds them by student public id', function () {
    $branch = makeBranch();
    $year = concessionYear($branch);
    $student = concessionStudent($branch);
    $student->refresh(); // public_id is set by the model on create

    Invoice::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id, 'student_id' => $student->id,
        'academic_year_id' => $year->id, 'title' => 'Overdue tuition', 'amount' => 900,
        'amount_paid' => 0, 'status' => 'unpaid', 'due_date' => now()->subDays(10),
    ]);
    Invoice::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id, 'student_id' => $student->id,
        'academic_year_id' => $year->id, 'title' => 'Future tuition', 'amount' => 900,
        'amount_paid' => 0, 'status' => 'unpaid', 'due_date' => now()->addDays(10),
    ]);

    Sanctum::actingAs(directorOf($branch));

    $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/invoices?overdue=1')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Overdue tuition')
        ->assertJsonPath('data.0.is_overdue', true);

    $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/invoices?search='.urlencode((string) $student->public_id))
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/invoices/stats')
        ->assertOk()
        ->assertJsonPath('data.overdue_count', 1);
});

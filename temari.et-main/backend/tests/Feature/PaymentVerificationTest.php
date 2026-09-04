<?php

use App\Actions\SaveAcademicYearAction;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\ParentProfile;
use App\Models\Payment;
use App\Models\Student;
use App\Models\StudentGuardian;
use App\Models\User;
use App\Services\CheckEt\CheckEtClient;
use App\Services\CheckEt\CheckEtResult;
use App\Services\Sms\SmsClient;
use Database\Seeders\BankSeeder;
use Database\Seeders\GradeLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(GradeLevelSeeder::class);
    $this->seed(BankSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    app()->instance(SmsClient::class, Mockery::spy(SmsClient::class));
});

/** A guardian user + linked child with one open invoice at a branch. */
function verificationSetup(Branch $branch, float $amount = 1000, bool $canPayFees = true): array
{
    $year = (new SaveAcademicYearAction())->execute($branch, ['name' => '2017 E.C.', 'status' => 'active']);

    $student = Student::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => 'Kid', 'father_name' => 'Test', 'gender' => 'male',
    ]);

    $guardianUser = User::factory()->create();
    $parent = ParentProfile::create(['user_id' => $guardianUser->id]);
    StudentGuardian::create([
        'student_id' => $student->id, 'parent_id' => $parent->id,
        'relationship' => 'father', 'can_pay_fees' => $canPayFees, 'is_active' => true,
    ]);

    $invoice = Invoice::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'student_id' => $student->id, 'academic_year_id' => $year->id,
        'title' => 'Tuition', 'amount' => $amount, 'amount_paid' => 0, 'status' => 'unpaid',
    ]);

    return [$guardianUser, $student, $invoice];
}

function fakeCheckEt(CheckEtResult $result): void
{
    $fake = new class ($result) implements CheckEtClient {
        /** @var array<string, mixed>|null */
        public ?array $lastPayload = null;

        public function __construct(private readonly CheckEtResult $result)
        {
        }

        public function verify(array $payload): CheckEtResult
        {
            $this->lastPayload = $payload;

            return $this->result;
        }
    };

    app()->instance(CheckEtClient::class, $fake);
}

/** Another child + open invoice at the SAME branch, reusing the year. */
function siblingClaimSetup(Branch $branch, Invoice $existing): array
{
    $student = Student::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => 'Sibling', 'father_name' => 'Test', 'gender' => 'male',
    ]);
    $guardianUser = User::factory()->create();
    $parent = ParentProfile::create(['user_id' => $guardianUser->id]);
    StudentGuardian::create([
        'student_id' => $student->id, 'parent_id' => $parent->id,
        'relationship' => 'father', 'can_pay_fees' => true, 'is_active' => true,
    ]);
    $invoice = Invoice::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'student_id' => $student->id, 'academic_year_id' => $existing->academic_year_id,
        'title' => 'Tuition', 'amount' => 1000, 'amount_paid' => 0, 'status' => 'unpaid',
    ]);

    return [$guardianUser, $student, $invoice];
}

function checkEtSuccess(float $amount, array $overrides = []): CheckEtResult
{
    return CheckEtResult::fromResponse([
        'success' => true,
        'exists' => true,
        'duplicate' => false,
        'message' => null,
        'data' => array_replace_recursive([
            'bank' => 'cbe',
            'bank_name' => 'Commercial Bank of Ethiopia',
            'transaction_number' => 'FT26144SG2ST',
            'verification_method' => 'official',
            'receipt' => [
                'status' => 'completed',
                'amount' => $amount,
                'currency' => 'ETB',
                'transaction_date' => '2026-07-01T10:00:00+03:00',
                'payer_name' => 'Guardian Person',
                'receiver_name' => 'The School',
                'receiver_account' => '1000876543218',
            ],
        ], $overrides),
    ]);
}

it('verifies a clean payment and settles the invoice automatically', function () {
    $branch = makeBranch();
    [$guardian, $student, $invoice] = verificationSetup($branch, 1000);
    fakeCheckEt(checkEtSuccess(1000));

    Sanctum::actingAs($guardian);

    $this->postJson("/api/v1/me/children/{$student->id}/invoices/{$invoice->id}/verify-payment", [
        'bank' => 'cbe', 'transaction_number' => 'FT26144SG2ST',
    ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'verified')
        ->assertJsonPath('data.invoice.status', 'paid');

    $invoice->refresh();
    expect((float) $invoice->amount_paid)->toBe(1000.0);
    expect(Payment::where('invoice_id', $invoice->id)->count())->toBe(1);
    expect(Payment::first()->reference)->toBe('FT26144SG2ST');
});

it('records a partial payment when the receipt covers part of the balance', function () {
    $branch = makeBranch();
    [$guardian, $student, $invoice] = verificationSetup($branch, 1000);
    fakeCheckEt(checkEtSuccess(400));

    Sanctum::actingAs($guardian);

    $this->postJson("/api/v1/me/children/{$student->id}/invoices/{$invoice->id}/verify-payment", [
        'bank' => 'cbe', 'transaction_number' => 'FT111',
    ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'verified')
        ->assertJsonPath('data.invoice.status', 'partial');
});

it('rejects duplicate receipts as failed', function () {
    $branch = makeBranch();
    [$guardian, $student, $invoice] = verificationSetup($branch, 1000);
    fakeCheckEt(CheckEtResult::fromResponse([
        'success' => true, 'exists' => true, 'duplicate' => true,
        'data' => ['bank' => 'cbe', 'transaction_number' => 'FT-DUP', 'receipt' => ['amount' => 1000, 'status' => 'completed']],
    ]));

    Sanctum::actingAs($guardian);

    $this->postJson("/api/v1/me/children/{$student->id}/invoices/{$invoice->id}/verify-payment", [
        'bank' => 'cbe', 'transaction_number' => 'FT-DUP',
    ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'failed');

    expect(Payment::count())->toBe(0);
    expect($invoice->fresh()->status->value)->toBe('unpaid');
});

it('parks over-balance receipts for finance review', function () {
    $branch = makeBranch();
    [$guardian, $student, $invoice] = verificationSetup($branch, 1000);
    fakeCheckEt(checkEtSuccess(5000));

    Sanctum::actingAs($guardian);

    $this->postJson("/api/v1/me/children/{$student->id}/invoices/{$invoice->id}/verify-payment", [
        'bank' => 'cbe', 'transaction_number' => 'FT-BIG',
    ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'needs_review');

    expect(Payment::count())->toBe(0);
});

it('parks receipts whose receiving account is not a school account', function () {
    $branch = makeBranch();
    [$guardian, $student, $invoice] = verificationSetup($branch, 1000);

    // The school HAS a known account — and the receipt lands elsewhere.
    BankAccount::create([
        'school_id' => $branch->school_id,
        'bank_id' => Bank::where('code', 'cbe')->value('id'),
        'account_name' => 'School CBE', 'account_number' => '1000876543218', 'is_active' => true,
    ]);
    fakeCheckEt(checkEtSuccess(1000, ['receipt' => ['receiver_account' => '9999999999999']]));

    Sanctum::actingAs($guardian);

    $this->postJson("/api/v1/me/children/{$student->id}/invoices/{$invoice->id}/verify-payment", [
        'bank' => 'cbe', 'transaction_number' => 'FT-WRONG',
    ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'needs_review');
});

it('parks the claim when the provider is unavailable', function () {
    $branch = makeBranch();
    [$guardian, $student, $invoice] = verificationSetup($branch, 1000);
    fakeCheckEt(CheckEtResult::unavailable('down'));

    Sanctum::actingAs($guardian);

    $this->postJson("/api/v1/me/children/{$student->id}/invoices/{$invoice->id}/verify-payment", [
        'bank' => 'cbe', 'transaction_number' => 'FT-X',
    ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'needs_review');
});

it('denies verification without the can_pay_fees flag or the guardian link', function () {
    $branch = makeBranch();
    [$guardian, $student, $invoice] = verificationSetup($branch, 1000, canPayFees: false);
    fakeCheckEt(checkEtSuccess(1000));

    Sanctum::actingAs($guardian);
    $this->postJson("/api/v1/me/children/{$student->id}/invoices/{$invoice->id}/verify-payment", [
        'bank' => 'cbe', 'transaction_number' => 'FT-1',
    ])->assertForbidden();

    // A user with no link at all gets nothing either.
    Sanctum::actingAs(User::factory()->create());
    $this->postJson("/api/v1/me/children/{$student->id}/invoices/{$invoice->id}/verify-payment", [
        'bank' => 'cbe', 'transaction_number' => 'FT-1',
    ])->assertForbidden();
});

it('rejects verification on settled invoices', function () {
    $branch = makeBranch();
    [$guardian, $student, $invoice] = verificationSetup($branch, 1000);
    $invoice->update(['status' => 'paid', 'amount_paid' => 1000]);
    fakeCheckEt(checkEtSuccess(1000));

    Sanctum::actingAs($guardian);

    $this->postJson("/api/v1/me/children/{$student->id}/invoices/{$invoice->id}/verify-payment", [
        'bank' => 'cbe', 'transaction_number' => 'FT-1',
    ])->assertStatus(422);
});

it('lets finance staff list an invoice\'s verification claims', function () {
    $branch = makeBranch();
    [$guardian, $student, $invoice] = verificationSetup($branch, 1000);
    fakeCheckEt(checkEtSuccess(5000));

    Sanctum::actingAs($guardian);
    $this->postJson("/api/v1/me/children/{$student->id}/invoices/{$invoice->id}/verify-payment", [
        'bank' => 'cbe', 'transaction_number' => 'FT-REVIEW',
    ])->assertCreated();

    Sanctum::actingAs(directorOf($branch));
    $response = $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/invoices/{$invoice->id}/verifications")
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.status'))->toBe('needs_review');
});

it('exposes check.et evidence and fraud flags to the reviewer', function () {
    $branch = makeBranch();
    [$guardian, $student, $invoice] = verificationSetup($branch, 1000);
    // Over-balance receipt → parked for review, evidence snapshot preserved.
    fakeCheckEt(checkEtSuccess(5000));

    Sanctum::actingAs($guardian);
    $this->postJson("/api/v1/me/children/{$student->id}/invoices/{$invoice->id}/verify-payment", [
        'bank' => 'cbe', 'transaction_number' => 'FT26144SG2ST',
    ])->assertCreated();

    // The same transaction number claimed on ANOTHER child's invoice.
    [$otherGuardian, $otherStudent, $otherInvoice] = siblingClaimSetup($branch, $invoice);
    Sanctum::actingAs($otherGuardian);
    $this->postJson("/api/v1/me/children/{$otherStudent->id}/invoices/{$otherInvoice->id}/verify-payment", [
        'bank' => 'cbe', 'transaction_number' => 'FT26144SG2ST',
    ])->assertCreated();

    Sanctum::actingAs(directorOf($branch));
    $response = $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/invoices/{$invoice->id}/verifications")
        ->assertOk();

    $claim = $response->json('data.0');
    expect($claim['evidence']['amount'])->toBe('5000.00');
    expect($claim['evidence']['payer_name'])->toBe('Guardian Person');
    expect($claim['evidence']['receiver_account'])->toBe('1000876543218');
    expect($claim['duplicate_claims'])->toBe(1);
    expect($claim['duplicate_other_invoices'])->toBe([sprintf('INV-%06d', $otherInvoice->id)]);
    expect($claim['already_paid_with'])->toBeFalse();
});

it('confirms a parked claim: records the payment and stamps the review', function () {
    $branch = makeBranch();
    [$guardian, $student, $invoice] = verificationSetup($branch, 1000);
    fakeCheckEt(checkEtSuccess(5000));

    Sanctum::actingAs($guardian);
    $this->postJson("/api/v1/me/children/{$student->id}/invoices/{$invoice->id}/verify-payment", [
        'bank' => 'cbe', 'transaction_number' => 'FT-CONFIRM',
    ])->assertCreated();

    $verification = $invoice->verifications()->first();
    $director = directorOf($branch);
    Sanctum::actingAs($director);

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/invoices/{$invoice->id}/verifications/{$verification->id}/confirm", [
            'amount' => 1000, 'note' => 'Matched on the statement.',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'paid');

    $verification->refresh();
    expect($verification->status->value)->toBe('verified');
    expect($verification->reviewed_by)->toBe($director->id);
    expect($verification->review_note)->toBe('Matched on the statement.');
    $payment = Payment::find($verification->payment_id);
    // The reference is what check.et read from bank records, not raw input.
    expect($payment->reference)->toBe('FT26144SG2ST');
    expect((float) $payment->amount)->toBe(1000.0);

    // Confirming twice is impossible — the claim is no longer parked.
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/invoices/{$invoice->id}/verifications/{$verification->id}/confirm")
        ->assertStatus(422);
});

it('blocks confirming a claim whose transaction already backs a payment', function () {
    $branch = makeBranch();
    [$guardian, $student, $invoice] = verificationSetup($branch, 1000);
    fakeCheckEt(checkEtSuccess(5000));

    Sanctum::actingAs($guardian);
    $this->postJson("/api/v1/me/children/{$student->id}/invoices/{$invoice->id}/verify-payment", [
        'bank' => 'cbe', 'transaction_number' => 'FT-REUSED',
    ])->assertCreated();

    // The same transaction settled ANOTHER invoice already. NB: the stored
    // claim number is what check.et READ from bank records (FT26144SG2ST),
    // not the raw user input — the fraud guard compares against that.
    [, , $otherInvoice] = siblingClaimSetup($branch, $invoice);
    Payment::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'invoice_id' => $otherInvoice->id, 'student_id' => $otherInvoice->student_id,
        'amount' => 1000, 'method' => 'bank_transfer', 'reference' => 'FT26144SG2ST',
        'receipt_number' => 'RCT-TEST-900001', 'receipt_token' => str_repeat('a', 40),
        'paid_at' => now(),
    ]);

    $verification = $invoice->verifications()->first();
    Sanctum::actingAs(directorOf($branch));

    $response = $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/invoices/{$invoice->id}/verifications/{$verification->id}/confirm")
        ->assertStatus(422);
    expect($response->json('message'))->toContain('already backs a recorded payment');

    // The list flags it too, so the reviewer sees it before trying.
    $list = $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/invoices/{$invoice->id}/verifications")
        ->assertOk();
    expect($list->json('data.0.already_paid_with'))->toBeTrue();
});

it('rejects a parked claim with a reason the family can see', function () {
    $branch = makeBranch();
    [$guardian, $student, $invoice] = verificationSetup($branch, 1000);
    fakeCheckEt(checkEtSuccess(5000));

    Sanctum::actingAs($guardian);
    $this->postJson("/api/v1/me/children/{$student->id}/invoices/{$invoice->id}/verify-payment", [
        'bank' => 'cbe', 'transaction_number' => 'FT-REJECT',
    ])->assertCreated();

    $verification = $invoice->verifications()->first();
    Sanctum::actingAs(directorOf($branch));

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/invoices/{$invoice->id}/verifications/{$verification->id}/reject", [
            'reason' => 'No matching deposit on the statement.',
        ])
        ->assertOk();

    $verification->refresh();
    expect($verification->status->value)->toBe('failed');
    expect($verification->failure_reason)->toBe('No matching deposit on the statement.');
    expect($verification->payment_id)->toBeNull();
    expect((float) $invoice->refresh()->amount_paid)->toBe(0.0);

    // The family sees the reason in their payments list.
    Sanctum::actingAs($guardian);
    $mine = $this->getJson("/api/v1/me/children/{$student->id}/invoices")->assertOk();
    expect($mine->json('data.0.verifications.0.status'))->toBe('failed');
    expect($mine->json('data.0.verifications.0.failure_reason'))
        ->toBe('No matching deposit on the statement.');
});

it('denies review actions to staff of another branch', function () {
    $branch = makeBranch();
    $other = makeBranch('AA-0002');
    [$guardian, $student, $invoice] = verificationSetup($branch, 1000);
    fakeCheckEt(checkEtSuccess(5000));

    Sanctum::actingAs($guardian);
    $this->postJson("/api/v1/me/children/{$student->id}/invoices/{$invoice->id}/verify-payment", [
        'bank' => 'cbe', 'transaction_number' => 'FT-SCOPE',
    ])->assertCreated();

    $verification = $invoice->verifications()->first();
    Sanctum::actingAs(directorOf($other));

    $this->withHeaders(branchContext($other))
        ->postJson("/api/v1/invoices/{$invoice->id}/verifications/{$verification->id}/confirm")
        ->assertForbidden();
});

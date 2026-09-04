<?php

use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\FeeStructure;
use App\Models\GradeLevel;
use App\Models\Invoice;
use App\Models\InvoiceReminder;
use App\Models\ParentProfile;
use App\Models\SchoolProgram;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentGuardian;
use App\Models\User;
use App\Services\FeeReminderService;
use App\Services\PenaltyService;
use App\Services\RecurringBillingService;
use App\Services\Sms\SmsClient;
use App\Support\EthiopianDate;
use Carbon\CarbonImmutable;
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

function financeStudent(Branch $branch, AcademicYear $year, string $first, ?string $enrolledOn = null): Student
{
    $student = Student::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => $first, 'father_name' => 'Test', 'gender' => 'male',
    ]);
    StudentEnrollment::create([
        'student_id' => $student->id, 'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'academic_year_id' => $year->id, 'school_program_id' => SchoolProgram::defaultFor($branch)->id,
        'grade_level_id' => GradeLevel::where('code', 'G1')->value('id'),
        'status' => 'active', 'enrolled_on' => $enrolledOn ?? '2025-09-15',
    ]);

    return $student;
}

function monthlyFee(Branch $branch, AcademicYear $year, array $extra = []): FeeStructure
{
    return FeeStructure::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id, 'academic_year_id' => $year->id,
        'name' => 'Tuition', 'amount' => 1000, 'type' => 'monthly',
        'auto_generate' => true, 'billing_day' => 10,
        ...$extra,
    ]);
}

// ───────────────────────── Ethiopian calendar ─────────────────────────

it('converts between Gregorian and Ethiopian dates exactly', function () {
    expect(EthiopianDate::toGregorian(2017, 1, 1)->toDateString())->toBe('2024-09-11')
        ->and(EthiopianDate::fromGregorian(CarbonImmutable::parse('2026-07-12')))
        ->toBe(['year' => 2018, 'month' => 11, 'day' => 5])
        ->and(EthiopianDate::daysInMonth(2015, 13))->toBe(6)
        ->and(EthiopianDate::daysInMonth(2016, 13))->toBe(5)
        ->and(EthiopianDate::monthLabel(2018, 1))->toBe('Meskerem 2018');
});

// ───────────────────────── recurring billing ─────────────────────────

it('bills every started Ethiopian month once per student and self-heals gaps', function () {
    $branch = makeBranch();
    $year = activeYear($branch);
    financeStudent($branch, $year, 'Abel');
    financeStudent($branch, $year, 'Bini');
    $fee = monthlyFee($branch, $year);

    // Hidar 5 2018 = 2025-11-15 → Meskerem, Tikimt, Hidar have started.
    $today = CarbonImmutable::parse('2025-11-15');
    $totals = app(RecurringBillingService::class)->run($today);

    expect($totals['invoices'])->toBe(6);

    $abelInvoices = Invoice::query()
        ->where('fee_structure_id', $fee->id)
        ->whereHas('student', fn ($q) => $q->where('first_name', 'Abel'))
        ->orderBy('billing_month')
        ->get();

    expect($abelInvoices)->toHaveCount(3)
        ->and($abelInvoices->pluck('billing_month')->all())->toBe([1, 2, 3])
        ->and($abelInvoices->first()->title)->toBe('Tuition — Meskerem 2018')
        // Due on the Ethiopian 10th: Meskerem 10 2018 = 2025-09-20.
        ->and($abelInvoices->first()->due_date->toDateString())->toBe('2025-09-20');

    // Re-run: nothing new (idempotent per fee × period × student).
    expect(app(RecurringBillingService::class)->run($today)['invoices'])->toBe(0);

    // A student enrolling later is only billed the periods they attend.
    financeStudent($branch, $year, 'Chaltu', enrolledOn: '2025-11-10'); // joined in Hidar
    $totals = app(RecurringBillingService::class)->run($today);

    expect($totals['invoices'])->toBe(1)
        ->and(Invoice::whereHas('student', fn ($q) => $q->where('first_name', 'Chaltu'))
            ->value('billing_month'))->toBe(3);
});

it('prorates a mid-month joiner by days when the school opts in', function () {
    $branch = makeBranch();
    $branch->school->update(['settings' => ['fee_proration' => 'daily']]);
    $year = activeYear($branch);
    // Meskerem runs 2025-09-11 → 2025-10-10 (30 days); joining 2025-10-01
    // leaves 10 days.
    financeStudent($branch, $year, 'Late', enrolledOn: '2025-10-01');
    monthlyFee($branch, $year);

    app(RecurringBillingService::class)->run(CarbonImmutable::parse('2025-10-05'));

    $invoice = Invoice::whereNotNull('billing_month')->where('billing_month', 1)->first();
    expect($invoice->amount)->toBe('333.33'); // 1000 × 10/30
});

it('does not auto-bill fees with auto_generate off', function () {
    $branch = makeBranch();
    $year = activeYear($branch);
    financeStudent($branch, $year, 'Abel');
    monthlyFee($branch, $year, ['auto_generate' => false]);

    expect(app(RecurringBillingService::class)->run(CarbonImmutable::parse('2025-11-15'))['invoices'])->toBe(0);
});

// ───────────────────────── penalties ─────────────────────────

it('accrues fixed and incremental late penalties idempotently and includes them in the balance', function () {
    $branch = makeBranch();
    $year = activeYear($branch);
    $abel = financeStudent($branch, $year, 'Abel');
    $bini = financeStudent($branch, $year, 'Bini');

    $fixed = monthlyFee($branch, $year, ['penalty_type' => 'fixed', 'penalty_amount' => 50]);
    $incremental = FeeStructure::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id, 'academic_year_id' => $year->id,
        'name' => 'Transport', 'amount' => 500, 'type' => 'monthly',
        'penalty_type' => 'incremental', 'penalty_amount' => 20, 'penalty_increment_days' => 7,
    ]);

    $overdueFixed = $fixed->invoices()->create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id, 'student_id' => $abel->id,
        'academic_year_id' => $year->id, 'title' => 'Tuition — Meskerem 2018',
        'amount' => 1000, 'status' => 'unpaid', 'due_date' => '2025-09-20',
    ]);
    $overdueIncremental = $incremental->invoices()->create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id, 'student_id' => $bini->id,
        'academic_year_id' => $year->id, 'title' => 'Transport — Meskerem 2018',
        'amount' => 500, 'status' => 'unpaid', 'due_date' => '2025-09-20',
    ]);

    // 16 days late: fixed = 50 once; incremental = 20 × floor(16/7) = 40.
    $today = CarbonImmutable::parse('2025-10-06');
    app(PenaltyService::class)->apply($today);
    app(PenaltyService::class)->apply($today); // re-run never double-charges

    expect($overdueFixed->refresh()->penalty_amount)->toBe('50.00')
        ->and($overdueFixed->balance)->toBe('1050.00')
        ->and($overdueIncremental->refresh()->penalty_amount)->toBe('40.00');

    // Waiving zeroes the penalty and blocks re-accrual.
    Sanctum::actingAs(directorOf($branch));
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/invoices/{$overdueFixed->id}/waive-penalty")
        ->assertOk()
        ->assertJsonPath('data.penalty_amount', '0.00');

    app(PenaltyService::class)->apply($today);
    expect($overdueFixed->refresh()->penalty_amount)->toBe('0.00');

    // Settling requires base + remaining penalty; a payment covering the
    // full total flips the invoice to paid.
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/invoices/{$overdueIncremental->id}/payments", [
            'amount' => 540, 'method' => 'cash',
        ])
        ->assertCreated();
    expect($overdueIncremental->refresh()->status->value)->toBe('paid');
});

// ───────────────────────── reminder ladder ─────────────────────────

it('walks the reminder ladder once per stage per recipient', function () {
    $branch = makeBranch();
    $year = activeYear($branch);
    $student = financeStudent($branch, $year, 'Abel');

    // A guardian reachable by SMS.
    $guardianUser = User::factory()->create(['phone' => '+251911000001', 'notify_via_sms' => true, 'notify_via_email' => false]);
    $parent = ParentProfile::create([
        'user_id' => $guardianUser->id,
        'first_name' => 'Guardian', 'father_name' => 'OfAbel',
    ]);
    StudentGuardian::create([
        'student_id' => $student->id, 'parent_id' => $parent->id,
        'relationship' => 'father', 'is_active' => true, 'can_receive_sms' => true,
    ]);

    $fee = monthlyFee($branch, $year, ['notify_parents' => true]);
    $invoice = $fee->invoices()->create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id, 'student_id' => $student->id,
        'academic_year_id' => $year->id, 'title' => 'Tuition — Meskerem 2018',
        'amount' => 1000, 'status' => 'unpaid', 'due_date' => '2025-09-20',
    ]);

    $service = app(FeeReminderService::class);

    // 3 days before due → upcoming.
    $sent = $service->runForBranch($branch, CarbonImmutable::parse('2025-09-17'));
    expect($sent['sms'])->toBe(1);

    // Same day re-run → deduped by the ledger.
    expect($service->runForBranch($branch, CarbonImmutable::parse('2025-09-17'))['sms'])->toBe(0);

    // Due day → the `due` rung fires once.
    expect($service->runForBranch($branch, CarbonImmutable::parse('2025-09-20'))['sms'])->toBe(1);

    // 7 and 14 days past due → overdue_1, overdue_2; ladder stops after max.
    expect($service->runForBranch($branch, CarbonImmutable::parse('2025-09-27'))['sms'])->toBe(1);
    expect($service->runForBranch($branch, CarbonImmutable::parse('2025-10-04'))['sms'])->toBe(1);
    expect($service->runForBranch($branch, CarbonImmutable::parse('2026-01-01'))['sms'])->toBe(0);

    expect(InvoiceReminder::where('invoice_id', $invoice->id)->orderBy('id')->pluck('stage')->all())
        ->toBe(['upcoming', 'due', 'overdue_1', 'overdue_2']);

    // Paid invoices drop out of the ladder entirely.
    $invoice->update(['status' => 'paid', 'amount_paid' => 1000]);
    expect($service->runForBranch($branch, CarbonImmutable::parse('2025-10-11'))['sms'])->toBe(0);
});

// ───────────────────────── receipts ─────────────────────────

it('numbers receipts per branch and verifies them publicly by token', function () {
    $branch = makeBranch();
    $year = activeYear($branch);
    $student = financeStudent($branch, $year, 'Abel');
    $fee = monthlyFee($branch, $year);
    $invoice = $fee->invoices()->create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id, 'student_id' => $student->id,
        'academic_year_id' => $year->id, 'title' => 'Tuition — Meskerem 2018',
        'amount' => 1000, 'status' => 'unpaid',
    ]);

    Sanctum::actingAs(directorOf($branch));

    $paymentId = $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/invoices/{$invoice->id}/payments", ['amount' => 400, 'method' => 'cash'])
        ->assertCreated()
        ->assertJsonPath('data.receipt_number', 'RCT-AA-0001-000001')
        ->json('data.id');

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/invoices/{$invoice->id}/payments", ['amount' => 100, 'method' => 'cash'])
        ->assertCreated()
        ->assertJsonPath('data.receipt_number', 'RCT-AA-0001-000002');

    $receipt = $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/payments/{$paymentId}/receipt")
        ->assertOk()
        ->json('data');

    expect($receipt['receipt_number'])->toBe('RCT-AA-0001-000001')
        ->and($receipt['student']['full_name'])->toContain('Abel');

    // The QR target works logged-out and returns the same receipt.
    auth()->forgetGuards();
    $this->getJson("/api/v1/public/receipts/{$receipt['public_token']}")
        ->assertOk()
        ->assertJsonPath('data.receipt_number', 'RCT-AA-0001-000001');

    $this->getJson('/api/v1/public/receipts/not-a-real-token')->assertNotFound();
});

// ───────────────────────── receivables reports ─────────────────────────

it('serves the receivables overview, defaulters and daily collections in scope', function () {
    $branch = makeBranch();
    $year = activeYear($branch);
    $student = financeStudent($branch, $year, 'Abel');
    $fee = monthlyFee($branch, $year);

    $overdue = $fee->invoices()->create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id, 'student_id' => $student->id,
        'academic_year_id' => $year->id, 'title' => 'Tuition — Meskerem 2018',
        'amount' => 1000, 'status' => 'unpaid', 'due_date' => now()->subDays(40)->toDateString(),
    ]);

    Sanctum::actingAs(directorOf($branch));

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/invoices/{$overdue->id}/payments", ['amount' => 300, 'method' => 'cash'])
        ->assertCreated();

    $overview = $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/fee-reports/overview')
        ->assertOk()
        ->json('data');

    expect($overview['invoiced'])->toBe('1000.00')
        ->and($overview['collected'])->toBe('300.00')
        ->and($overview['outstanding'])->toBe('700.00')
        ->and($overview['students_owing'])->toBe(1)
        ->and(collect($overview['aging'])->firstWhere('bucket', '31-60')['amount'])->toBe('700.00');

    $defaulters = $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/fee-reports/defaulters')
        ->assertOk()
        ->json('data');

    expect($defaulters)->toHaveCount(1)
        ->and($defaulters[0]['balance'])->toBe('700.00')
        ->and($defaulters[0]['student_name'])->toContain('Abel');

    $daily = $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/fee-reports/daily-collections')
        ->assertOk()
        ->json('data');

    expect($daily['total'])->toBe('300.00')
        ->and($daily['methods'][0]['method'])->toBe('cash');

    // A teacher (no fees.reports.view) is refused.
    Sanctum::actingAs(memberOf($branch));
    $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/fee-reports/overview')
        ->assertForbidden();
});

// ───────────────────────── the books ─────────────────────────

it('runs the expense lane: record → four-eyes approve → books', function () {
    $branch = makeBranch();
    $year = activeYear($branch);
    $director = directorOf($branch);
    $director2 = directorOf($branch);

    Sanctum::actingAs($director);

    // First read provisions the default categories.
    $categories = $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/finance/categories?kind=expense')
        ->assertOk()
        ->json('data');
    expect(count($categories))->toBeGreaterThan(5);
    $rent = collect($categories)->firstWhere('name', 'Rent');

    $expenseId = $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/finance/expenses', [
            'finance_category_id' => $rent['id'], 'title' => 'Hamle rent',
            'amount' => 25000, 'expense_date' => now()->toDateString(), 'method' => 'cash',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->json('data.id');

    // The recorder cannot countersign their own expense.
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/finance/expenses/{$expenseId}/approve")
        ->assertStatus(422);

    Sanctum::actingAs($director2);
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/finance/expenses/{$expenseId}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    // Approved rows are immutable.
    $this->withHeaders(branchContext($branch))
        ->putJson("/api/v1/finance/expenses/{$expenseId}", [
            'finance_category_id' => $rent['id'], 'title' => 'X', 'amount' => 1,
            'expense_date' => now()->toDateString(), 'method' => 'cash',
        ])
        ->assertStatus(422);

    // …and appear in the cashbook and statement.
    $cashbook = $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/finance/cashbook')
        ->assertOk();
    expect($cashbook->json('meta.money_out'))->toBe('25000.00');

    $statement = $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/finance/statement')
        ->assertOk()
        ->json('data');
    expect($statement['expenses']['total'])->toBe('25000.00')
        ->and($statement['expenses']['categories'][0]['category'])->toBe('Rent');
});

it('keeps the books tenant-scoped: another school sees nothing', function () {
    $branchA = makeBranch('AA-0001');
    $branchB = makeBranch('AA-0002');
    $yearA = activeYear($branchA);

    Sanctum::actingAs(directorOf($branchA));
    $categories = $this->withHeaders(branchContext($branchA))
        ->getJson('/api/v1/finance/categories?kind=expense')->json('data');
    $this->withHeaders(branchContext($branchA))
        ->postJson('/api/v1/finance/expenses', [
            'finance_category_id' => $categories[0]['id'], 'title' => 'A-only',
            'amount' => 100, 'expense_date' => now()->toDateString(), 'method' => 'cash',
        ])->assertCreated();

    $expense = Expense::first();

    Sanctum::actingAs(directorOf($branchB));
    $this->withHeaders(branchContext($branchB))
        ->getJson('/api/v1/finance/expenses')
        ->assertOk()
        ->assertJsonCount(0, 'data');
    // Another school's category is rejected on write.
    $this->withHeaders(branchContext($branchB))
        ->postJson('/api/v1/finance/expenses', [
            'finance_category_id' => $categories[0]['id'], 'title' => 'Cross',
            'amount' => 100, 'expense_date' => now()->toDateString(), 'method' => 'cash',
        ])->assertStatus(422);
    // And their approver cannot touch school A's row.
    $this->withHeaders(branchContext($branchB))
        ->postJson("/api/v1/finance/expenses/{$expense->id}/approve")
        ->assertForbidden();
});

it('saves budgets and reports budget vs actual', function () {
    $branch = makeBranch();
    $year = activeYear($branch);
    $director = directorOf($branch);
    $approver = directorOf($branch);
    Sanctum::actingAs($director);

    $categories = $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/finance/categories?kind=expense')->json('data');
    $rent = collect($categories)->firstWhere('name', 'Rent');

    $this->withHeaders(branchContext($branch))
        ->putJson("/api/v1/finance/budgets?academic_year_id={$year->id}", [
            'items' => [['finance_category_id' => $rent['id'], 'amount' => 300000]],
        ])
        ->assertOk();

    // An approved rent expense inside the year window counts as actual.
    $expenseId = $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/finance/expenses', [
            'finance_category_id' => $rent['id'], 'title' => 'Rent Q1',
            'amount' => 75000, 'expense_date' => '2025-10-01', 'method' => 'cash',
        ])->json('data.id');
    Sanctum::actingAs($approver);
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/finance/expenses/{$expenseId}/approve")->assertOk();

    $rows = $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/finance/budgets?academic_year_id={$year->id}")
        ->assertOk()
        ->json();

    $rentRow = collect($rows['data'])->firstWhere('category', 'Rent');
    expect($rentRow['budget'])->toBe('300000.00')
        ->and($rentRow['actual'])->toBe('75000.00')
        ->and($rows['meta']['budget_total'])->toBe('300000.00');
});

it('refuses money entries dated in the future', function () {
    $branch = makeBranch();
    activeYear($branch);
    Sanctum::actingAs(directorOf($branch));

    $categories = $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/finance/categories?kind=expense')->assertOk()->json('data');
    $rent = collect($categories)->firstWhere('name', 'Rent');

    // An expense records money already spent — tomorrow hasn't happened.
    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/finance/expenses', [
            'finance_category_id' => $rent['id'], 'title' => 'Prepaid rent',
            'amount' => 1000, 'expense_date' => now()->addDays(2)->toDateString(), 'method' => 'cash',
        ])->assertStatus(422)->assertJsonValidationErrors('expense_date');
});

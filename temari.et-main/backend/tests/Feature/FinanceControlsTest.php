<?php

use App\Models\Budget;
use App\Models\Expense;
use App\Models\FinanceCategory;
use App\Models\School;
use App\Services\Sms\SmsClient;
use App\Support\FinanceControls;
use Database\Seeders\GradeLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(GradeLevelSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    app()->instance(SmsClient::class, Mockery::spy(SmsClient::class));
});

function enableSelfApproval(int $schoolId): void
{
    $school = School::findOrFail($schoolId);
    $school->update(['settings' => array_merge($school->settings ?? [], ['finance_self_approval' => true])]);
}

function expenseCategoryId(int $schoolId): int
{
    return (int) FinanceCategory::query()
        ->where('school_id', $schoolId)->where('kind', 'expense')->value('id')
        ?: FinanceCategory::create([
            'school_id' => $schoolId, 'kind' => 'expense', 'name' => 'Rent', 'is_active' => true,
        ])->id;
}

// ── Director finance gate ───────────────────────────────────────────────

it('denies directors the finance books by default — the Ethiopian director is an academic head', function () {
    $branch = makeBranch();
    activeYear($branch);
    $director = directorOf($branch, financeAccess: false);
    Sanctum::actingAs($director);

    $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/finance/cashbook')->assertForbidden();
    $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/finance/expenses')->assertForbidden();

    // Non-money student reads keep working.
    $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/students')->assertOk();
});

it('grants directors finance authority when the school flips director_finance_access', function () {
    $branch = makeBranch();
    activeYear($branch);
    $director = directorOf($branch); // helper enables the school setting
    Sanctum::actingAs($director);

    $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/finance/cashbook')->assertOk();
});

it('lets only school managers change the finance control settings — never directors', function () {
    $branch = makeBranch();
    $director = directorOf($branch, financeAccess: false);
    $principal = schoolPrincipal($branch);

    Sanctum::actingAs($director);
    $this->withHeaders(branchContext($branch))
        ->patchJson("/api/v1/schools/{$branch->school_id}/settings", ['director_finance_access' => true])
        ->assertForbidden();

    Sanctum::actingAs($principal);
    $this->withHeaders(['X-School-Id' => (string) $branch->school_id])
        ->patchJson("/api/v1/schools/{$branch->school_id}/settings", ['director_finance_access' => true])
        ->assertOk();

    Cache::flush();
    FinanceControls::flush();

    Sanctum::actingAs($director);
    $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/finance/cashbook')->assertOk();
});

// ── Expense self-approval setting ───────────────────────────────────────

it('blocks self-approval by default and allows it once the school opts in', function () {
    $branch = makeBranch();
    activeYear($branch);
    $recorder = directorOf($branch);
    Sanctum::actingAs($recorder);

    $expenseId = $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/finance/expenses', [
            'finance_category_id' => expenseCategoryId($branch->school_id),
            'title' => 'Chalk', 'amount' => 500,
            'expense_date' => '2025-10-01', 'method' => 'cash',
        ])->assertCreated()->json('data.id');

    // Four-eyes rule: the recorder cannot countersign their own expense.
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/finance/expenses/{$expenseId}/approve")
        ->assertUnprocessable();

    enableSelfApproval($branch->school_id);

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/finance/expenses/{$expenseId}/approve")
        ->assertOk();

    expect(Expense::find($expenseId)->status)->toBe('approved');
});

// ── Gapless budget window ───────────────────────────────────────────────

it('counts kremt (post-year-end) expenses toward the budget year until the next year starts', function () {
    $branch = makeBranch();
    $year = activeYear($branch); // ends 2026-07-08
    $director = directorOf($branch);
    $approver = directorOf($branch);
    $categoryId = expenseCategoryId($branch->school_id);

    Sanctum::actingAs($director);
    $this->withHeaders(branchContext($branch))
        ->putJson("/api/v1/finance/budgets?academic_year_id={$year->id}", [
            'items' => [['finance_category_id' => $categoryId, 'amount' => 10000]],
        ])->assertOk();

    // Dated AFTER ends_on — the old window would have orphaned it.
    $expenseId = $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/finance/expenses', [
            'finance_category_id' => $categoryId, 'title' => 'Summer paint',
            'amount' => 2000, 'expense_date' => now()->toDateString(), 'method' => 'cash',
        ])->json('data.id');
    Sanctum::actingAs($approver);
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/finance/expenses/{$expenseId}/approve")->assertOk();

    // A second, PENDING expense surfaces separately — never inside actual.
    Sanctum::actingAs($director);
    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/finance/expenses', [
            'finance_category_id' => $categoryId, 'title' => 'Pending desks',
            'amount' => 700, 'expense_date' => now()->toDateString(), 'method' => 'cash',
        ])->assertCreated();

    $rows = $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/finance/budgets?academic_year_id={$year->id}")
        ->assertOk()->json();

    $row = collect($rows['data'])->firstWhere('finance_category_id', $categoryId);
    expect($row['actual'])->toBe('2000.00')
        ->and($row['pending'])->toBe('700.00')
        ->and($rows['meta']['pending_total'])->toBe('700.00');

    expect(Budget::query()->count())->toBe(1);
});

// ── Cashbook filters ────────────────────────────────────────────────────

it('filters the cashbook by direction, source and search — totals follow the filters', function () {
    $branch = makeBranch();
    activeYear($branch);
    $director = directorOf($branch);
    $approver = directorOf($branch);
    $categoryId = expenseCategoryId($branch->school_id);

    Sanctum::actingAs($director);
    $expenseId = $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/finance/expenses', [
            'finance_category_id' => $categoryId, 'title' => 'Generator fuel',
            'amount' => 1500, 'expense_date' => '2026-07-01', 'method' => 'cash',
        ])->json('data.id');
    Sanctum::actingAs($approver);
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/finance/expenses/{$expenseId}/approve")->assertOk();

    Sanctum::actingAs($director);
    $incomeCategory = FinanceCategory::create([
        'school_id' => $branch->school_id, 'kind' => 'income', 'name' => 'Hall rental', 'is_active' => true,
    ]);
    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/finance/other-incomes', [
            'finance_category_id' => $incomeCategory->id, 'title' => 'Hall booking',
            'amount' => 900, 'received_on' => '2026-07-02', 'method' => 'cash',
        ])->assertCreated();

    $out = $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/finance/cashbook?from=2026-06-25&to=2026-07-05&direction=out')
        ->assertOk()->json();
    expect(collect($out['data'])->pluck('direction')->unique()->all())->toBe(['out'])
        ->and($out['meta']['money_in'])->toBe('0.00')
        ->and($out['meta']['money_out'])->toBe('1500.00');

    $searched = $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/finance/cashbook?from=2026-06-25&to=2026-07-05&search=hall')
        ->assertOk()->json();
    expect(count($searched['data']))->toBe(1)
        ->and($searched['data'][0]['description'])->toBe('Hall booking')
        ->and($searched['data'][0]['category'])->toBe('Hall rental');

    $byCategory = $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/finance/cashbook?from=2026-06-25&to=2026-07-05&finance_category_id={$categoryId}")
        ->assertOk()->json();
    expect(count($byCategory['data']))->toBe(1)
        ->and($byCategory['data'][0]['source'])->toBe('expense');
});

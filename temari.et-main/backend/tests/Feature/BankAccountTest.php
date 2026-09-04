<?php

use App\Actions\SaveAcademicYearAction;
use App\Models\Bank;
use App\Models\BankAccount;
use Database\Seeders\BankSeeder;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(BankSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('lists the seeded bank catalog with wallets flagged', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));

    $banks = $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/banks')
        ->assertOk()
        ->json('data');

    $telebirr = collect($banks)->firstWhere('code', 'telebirr');
    expect($telebirr['type'])->toBe('wallet');
    expect(collect($banks)->firstWhere('code', 'cbe')['name'])->toBe('Commercial Bank of Ethiopia');
});

it('creates a school account attached to the branch and shares it with sister branches', function () {
    $branchA = makeBranch('AA-0001');
    $branchB = $branchA->school->branches()->create(['name' => 'Branch B', 'code' => 'AA-0002', 'is_active' => true]);
    Sanctum::actingAs(directorOf($branchA));

    $bankId = Bank::where('code', 'cbe')->value('id');

    $account = $this->withHeaders(branchContext($branchA))
        ->postJson('/api/v1/bank-accounts', [
            'bank_id' => $bankId,
            'account_name' => 'Sunrise School',
            'account_number' => '1000123456789',
            'branch_ids' => [$branchB->id],
        ])
        ->assertCreated();

    expect($account->json('data.branches'))->toHaveCount(2);
    expect($account->json('data.attached_to_branch'))->toBeTrue();

    // Deactivating for THIS branch only leaves the other branch untouched.
    $id = $account->json('data.id');
    $this->withHeaders(branchContext($branchA))
        ->putJson("/api/v1/bank-accounts/{$id}", ['branch_active' => false])
        ->assertOk()
        ->assertJsonPath('data.branch_active', false);

    $pivot = BankAccount::find($id)->branches()->where('branches.id', $branchB->id)->first()->pivot;
    expect((bool) $pivot->is_active)->toBeTrue();
});

it('requires the fee collection account to be active for the branch', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));
    $year = (new SaveAcademicYearAction())->execute($branch, ['name' => '2017 E.C.']);

    $bankId = Bank::where('code', 'awash')->value('id');
    $accountId = $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/bank-accounts', [
            'bank_id' => $bankId, 'account_name' => 'School', 'account_number' => '013201234567',
        ])->json('data.id');

    // Fee pointing at usable accounts is accepted (one or many).
    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/fee-structures', [
            'name' => 'Monthly fee', 'type' => 'monthly', 'amount' => 1500,
            'academic_year_id' => $year->id, 'bank_account_ids' => [$accountId],
        ])
        ->assertCreated()
        ->assertJsonPath('data.bank_accounts.0.id', $accountId);

    $secondId = $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/bank-accounts', [
            'bank_id' => Bank::where('code', 'telebirr')->value('id'),
            'account_name' => 'School Telebirr',
            'account_number' => '0911223344',
        ])->json('data.id');

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/fee-structures', [
            'name' => 'Registration', 'type' => 'registration', 'amount' => 500,
            'academic_year_id' => $year->id, 'bank_account_ids' => [$accountId, $secondId],
        ])
        ->assertCreated()
        ->assertJsonCount(2, 'data.bank_accounts');

    // Disable for the branch → the account is no longer selectable.
    $this->withHeaders(branchContext($branch))
        ->putJson("/api/v1/bank-accounts/{$accountId}", ['branch_active' => false])
        ->assertOk();

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/fee-structures', [
            'name' => 'Tutoring fee', 'type' => 'monthly', 'amount' => 900,
            'academic_year_id' => $year->id, 'bank_account_ids' => [$accountId],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('bank_account_ids.0');
});

it('never exposes another school\'s accounts', function () {
    $branchA = makeBranch('AA-0001');
    $branchB = makeBranch('BB-0001'); // different school
    Sanctum::actingAs(directorOf($branchA));

    $bankId = Bank::where('code', 'cbe')->value('id');
    $this->withHeaders(branchContext($branchA))
        ->postJson('/api/v1/bank-accounts', [
            'bank_id' => $bankId, 'account_name' => 'A school', 'account_number' => '77001',
        ])->assertCreated();

    Sanctum::actingAs(directorOf($branchB));
    $accounts = $this->withHeaders(branchContext($branchB))
        ->getJson('/api/v1/bank-accounts')
        ->assertOk()
        ->json('data');

    expect($accounts)->toHaveCount(0);
});

<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\FinancialTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialTransactionBalanceUpdateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * It recalculates both old and new account balances when a transaction is updated.
     */
    public function test_it_recalculates_balances_when_transaction_changes_account_and_amount(): void
    {
        $user = User::factory()->create();

        $firstAccount = BankAccount::create([
            'account_number' => '1000001',
            'account_name' => 'Main Account',
            'bank_name' => 'Test Bank',
            'account_type' => 'current',
            'current_balance' => 0,
            'currency' => 'ETB',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $secondAccount = BankAccount::create([
            'account_number' => '1000002',
            'account_name' => 'Reserve Account',
            'bank_name' => 'Test Bank',
            'account_type' => 'current',
            'current_balance' => 0,
            'currency' => 'ETB',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $transaction = FinancialTransaction::create([
            'type' => 'income',
            'title' => 'Initial donation',
            'description' => null,
            'amount' => 1000,
            'currency' => 'ETB',
            'category' => 'donation',
            'source' => 'Member',
            'transaction_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'bank_account_id' => $firstAccount->id,
            'attachment_path' => null,
            'attachment_type' => null,
            'recorded_by' => $user->id,
            'approved_by' => null,
            'approved_at' => null,
        ]);

        $firstAccount->refresh();
        $this->assertSame('1000.00', (string) $firstAccount->current_balance);

        $transaction->update([
            'type' => 'expense',
            'amount' => 400,
            'bank_account_id' => $secondAccount->id,
        ]);

        $firstAccount->refresh();
        $secondAccount->refresh();

        $this->assertSame('0.00', (string) $firstAccount->current_balance);
        $this->assertSame('-400.00', (string) $secondAccount->current_balance);
    }
}

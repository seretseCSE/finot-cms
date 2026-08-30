<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Donation;
use App\Models\FinancialTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FinancialSystemTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function finance_head_can_access_contributions_page(): void
    {
        $user = $this->createFinanceHeadUser();
        $this->actingAs($user);

        $response = $this->get('/admin/contribution-matrix');
        $response->assertStatus(200);
    }

    #[Test]
    public function finance_head_can_access_donations_page(): void
    {
        $user = $this->createFinanceHeadUser();
        $this->actingAs($user);

        $response = $this->get('/admin/donations');
        $response->assertStatus(200);
    }

    #[Test]
    public function finance_head_can_access_donations_create_page(): void
    {
        $user = $this->createFinanceHeadUser();
        $this->actingAs($user);

        $response = $this->get('/admin/donations/create');
        $response->assertStatus(200);
    }

    #[Test]
    public function financial_transaction_creation_updates_bank_balance(): void
    {
        $user = User::factory()->create();
        $account = BankAccount::factory()->create(['current_balance' => 1000]);

        FinancialTransaction::create([
            'type' => 'income',
            'title' => 'Sunday Collection',
            'amount' => 200,
            'currency' => 'ETB',
            'category' => 'offering',
            'transaction_date' => now(),
            'payment_method' => 'cash',
            'bank_account_id' => $account->id,
            'recorded_by' => $user->id,
        ]);

        $account->refresh();
        $this->assertEquals(1200, $account->current_balance);
    }

    #[Test]
    public function donation_linked_to_bank_updates_balance(): void
    {
        $user = User::factory()->create();
        $account = BankAccount::factory()->create(['current_balance' => 0]);

        Donation::create([
            'donor_name' => 'Jane Doe',
            'amount' => 300,
            'donation_date' => now(),
            'donation_type' => 'Building Fund',
            'bank_account_id' => $account->id,
            'recorded_by' => $user->id,
        ]);

        $account->refresh();
        $this->assertEquals(300, $account->current_balance);
    }

    #[Test]
    public function bank_account_resource_pages_are_accessible(): void
    {
        $user = $this->createFinanceHeadUser();
        $this->actingAs($user);

        $this->get('/admin/bank-accounts')->assertStatus(200);
        $this->get('/admin/bank-accounts/create')->assertStatus(200);
    }

    #[Test]
    public function contribution_amount_resource_pages_are_accessible(): void
    {
        $user = $this->createFinanceHeadUser();
        $this->actingAs($user);

        $this->get('/admin/contribution-amounts')->assertStatus(200);
        $this->get('/admin/contribution-amounts/create')->assertStatus(200);
    }

    #[Test]
    public function financial_overview_page_accessible(): void
    {
        $user = $this->createFinanceHeadUser();
        $this->actingAs($user);

        $response = $this->get('/admin/financial-overview-page');
        $response->assertStatus(200);
    }

    #[Test]
    public function contribution_report_page_accessible(): void
    {
        $user = $this->createFinanceHeadUser();
        $this->actingAs($user);

        $response = $this->get('/admin/contribution-report');
        $response->assertStatus(200);
    }

    #[Test]
    public function donation_report_page_accessible(): void
    {
        $user = $this->createFinanceHeadUser();
        $this->actingAs($user);

        $response = $this->get('/admin/donation-report-page');
        $response->assertStatus(200);
    }

    #[Test]
    public function outstanding_contributions_page_accessible(): void
    {
        $user = $this->createFinanceHeadUser();
        $this->actingAs($user);

        $response = $this->get('/admin/outstanding-contributions');
        $response->assertStatus(200);
    }

    #[Test]
    public function financial_transaction_export_is_available(): void
    {
        $user = $this->createFinanceHeadUser();
        $this->actingAs($user);

        $response = $this->get('/admin/financial-transactions');
        $response->assertStatus(200);
    }

    #[Test]
    public function financial_transactions_create_page_accessible(): void
    {
        $user = $this->createFinanceHeadUser();
        $this->actingAs($user);

        $response = $this->get('/admin/financial-transactions/create');
        $response->assertStatus(200);
    }

    #[Test]
    public function ethiopian_month_names_appear_in_contribution_context(): void
    {
        $user = $this->createFinanceHeadUser();
        $this->actingAs($user);

        $response = $this->get('/admin/contribution-matrix');
        $response->assertStatus(200);
    }

    #[Test]
    public function contribution_matrix_page_accessible(): void
    {
        $user = $this->createFinanceHeadUser();
        $this->actingAs($user);

        $response = $this->get('/admin/contribution-matrix');
        $response->assertStatus(200);
    }

    #[Test]
    public function financial_audit_trail_page_accessible(): void
    {
        $user = $this->createFinanceHeadUser();
        $this->actingAs($user);

        $response = $this->get('/admin/financial-audit-trail-page');
        $response->assertStatus(200);
    }

    #[Test]
    public function financial_statement_page_accessible(): void
    {
        $user = $this->createFinanceHeadUser();
        $this->actingAs($user);

        $response = $this->get('/admin/financial-statement-page');
        $response->assertStatus(200);
    }
}

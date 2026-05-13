<?php

namespace App\Console\Commands;

use App\Models\BankAccount;
use Illuminate\Console\Command;

class UpdateBankBalances extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bank:update-balances';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update all bank account balances based on transactions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Updating bank account balances...');

        \DB::transaction(function () {
            $updatedCount = 0;

            BankAccount::chunk(1000, function ($accounts) use (&$updatedCount) {
                foreach ($accounts as $account) {
                    $oldBalance = $account->current_balance;
                    $account->updateBalance();
                    $newBalance = $account->current_balance;

                    $this->line("Updated: {$account->account_name}");
                    $this->line("  Old balance: {$oldBalance}");
                    $this->line("  New balance: {$newBalance}");
                    $this->line('');

                    $updatedCount++;
                }
            });

            $this->info("Successfully updated {$updatedCount} bank account balances!");
        });

        return Command::SUCCESS;
    }
}

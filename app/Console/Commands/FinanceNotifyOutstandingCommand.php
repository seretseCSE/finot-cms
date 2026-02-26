<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Contribution;
use App\Models\User;
use App\Notifications\OutstandingContribution;

class FinanceNotifyOutstandingCommand extends Command
{
    protected $signature = 'finance:notify-outstanding';
    protected $description = 'Notify outstanding contributions monthly';

    public function handle()
    {
        $outstanding = Contribution::where('status', 'pending')->get();
        $sent = 0;

        foreach ($outstanding->groupBy('member_id') as $memberId => $contributions) {
            $user = User::where('member_id', $memberId)->first();
            if ($user) {
                $total = $contributions->sum('amount');
                $user->notify(new OutstandingContribution($contributions, $total));
                $sent++;
            }
        }

        $this->info("Sent {$sent} outstanding contribution notifications.");
        return 0;
    }
}

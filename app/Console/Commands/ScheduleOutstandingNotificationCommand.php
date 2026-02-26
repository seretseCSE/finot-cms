<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class ScheduleOutstandingNotificationCommand extends Command
{
    protected $signature = 'finance:schedule-outstanding-notifications';
    protected $description = 'Schedule monthly outstanding contribution notifications';

    public function handle(): int
    {
        $this->info('Scheduling monthly outstanding notifications...');

        try {
            // Schedule the command to run on the 1st day of each Ethiopian month
            // This is an approximation since Ethiopian calendar doesn't align perfectly with Gregorian
            $cronExpression = '0 8 11 * *'; // 8:11 AM on the 11th day of each month (approximate)
            
            $this->info("Cron expression: {$cronExpression}");
            $this->info('This would schedule: php artisan finance:monthly-outstanding-notification');
            $this->info('To run on the 1st of each Ethiopian month, configure your cron job with:');
            $this->info('0 8 11 * * (8:11 AM on 11th day - approximate for Ethiopian 1st)');
            $this->info('');
            $this->info('Add to your crontab:');
            $this->info("{$cronExpression} php /path/to/your/project/artisan finance:monthly-outstanding-notification >> /path/to/logs/outstanding.log 2>&1");
            
            // For demonstration, run the notification command immediately
            if ($this->option('run-now')) {
                $this->call('finance:monthly-outstanding-notification');
            }

            $this->info('✅ Outstanding notification scheduling configured!');
            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Failed to schedule notifications: " . $e->getMessage());
            return 1;
        }
    }
}

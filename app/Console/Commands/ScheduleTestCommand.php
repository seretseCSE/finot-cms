<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class ScheduleTestCommand extends Command
{
    protected $signature = 'schedule:test {command-name : The command to test}';
    protected $description = 'Test a scheduled command';

    public function handle()
    {
        $commandName = $this->argument('command-name');
        
        $validCommands = [
            'attendance:auto-lock',
            'attendance:send-lock-reminders',
            'content:publish-scheduled',
            'rehearsals:send-reminders',
            'aid:auto-lock',
            'system:check-health',
            'notifications:purge-read',
            'logs:purge-security-audit',
            'logs:purge-session-logs',
            'logs:purge-error-logs',
            'logs:purge-export-logs',
            'finance:notify-outstanding',
            'media:auto-archive',
        ];

        if (!in_array($commandName, $validCommands)) {
            $this->error("Invalid command: {$commandName}");
            $this->line('Available commands:');
            foreach ($validCommands as $cmd) {
                $this->line("  - {$cmd}");
            }
            return 1;
        }

        $this->info("Testing command: {$commandName}");
        Artisan::call($commandName);
        $this->info('Command executed successfully.');
        return 0;
    }
}

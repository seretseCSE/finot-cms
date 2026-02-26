<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SystemCheckHealthCommand extends Command
{
    protected $signature = 'system:check-health';

    protected $description = 'Check system health and send alerts if thresholds exceeded';

    public function handle(): int
    {
        $this->info('Starting system health check...');

        $issues = [];

        // Check storage usage
        $totalSpace = disk_total_space('/');
        $freeSpace = disk_free_space('/');
        $usedSpace = $totalSpace - $freeSpace;
        $usedPercentage = ($totalSpace > 0) ? round(($usedSpace / $totalSpace) * 100, 2) : 0;

        if ($usedPercentage > 70) {
            $issues[] = 'Storage usage at ' . $usedPercentage . '%';
        }

        // Check database response time
        $responseTime = DB::select(DB::raw('1'))->first()->response_time ?? 0;
        if ($responseTime > 2000) {
            $issues[] = 'Database response time: ' . $responseTime . 'ms';
        }

        // Check error rate
        $errorCount = DB::table('error_logs')
            ->where('action_type', 'login_failed')
            ->where('created_at', '>', now()->subHour())
            ->count();
        $totalRequests = DB::table('error_logs')
            ->where('created_at', '>', now()->subHour())
            ->count();
        $errorRate = $totalRequests > 0 ? round(($errorCount / $totalRequests) * 100, 2) : 0;

        if ($errorRate > 10) {
            $issues[] = 'Error rate: ' . $errorRate . '/hr';
        }

        // Check failed logins
        $failedLogins = DB::table('audit_logs')
            ->where('action_type', 'login_failed')
            ->where('created_at', '>', now()->subHour())
            ->count();

        if ($failedLogins > 5) {
            $issues[] = 'Failed logins: ' . $failedLogins . ' in last hour';
        }

        if (empty($issues)) {
            $this->info('System health check completed. All systems normal.');
        } else {
            $this->error('System health issues detected:');
            foreach ($issues as $issue) {
                $this->error('  - ' . $issue);
            }
        }

        return Command::SUCCESS;
    }
}

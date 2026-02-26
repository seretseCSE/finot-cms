<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class LogsPurgeSecurityAuditCommand extends Command
{
    protected $signature = 'logs:purge-security-audit';

    protected $description = 'Purge security audit logs older than 30 days';

    public function handle(): int
    {
        $this->info('Starting security audit log purge...');

        $cutoffDate = now()->subDays(30);
        $deletedCount = AuditLog::where('tier', 'security')
            ->where('created_at', '<', $cutoffDate)
            ->delete();

        $this->info("Purged {$deletedCount} security audit logs older than 30 days.");

        // Log to Tier 1 audit trail
        Log::channel('audit')->info('Tier 1 Audit Log', [
            'tier' => 1,
            'action' => 'security_audit_purged',
            'entity_id' => null,
            'entity_type' => 'system',
            'old_value' => null,
            'new_value' => json_encode(['logs_purged' => $deletedCount]),
            'user_id' => null, // System action
            'timestamp' => now()->toDateTimeString(),
        ]);

        return Command::SUCCESS;
    }
}

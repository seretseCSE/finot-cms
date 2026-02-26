<?php

namespace App\Observers;

use App\Models\Contribution;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ContributionObserver
{
    /**
     * Handle the Contribution "created" event.
     */
    public function created(Contribution $contribution): void
    {
        $this->logAuditEvent('contribution_created', $contribution, null, $contribution->toArray());
    }

    /**
     * Handle the Contribution "updated" event.
     */
    public function updated(Contribution $contribution): void
    {
        $changes = $contribution->getChanges();
        $original = $contribution->getOriginal();
        
        // Only log if financial fields changed
        $financialFields = ['amount', 'month_name', 'payment_method'];
        $hasFinancialChanges = false;
        
        foreach ($financialFields as $field) {
            if (isset($changes[$field])) {
                $hasFinancialChanges = true;
                break;
            }
        }

        if ($hasFinancialChanges) {
            $oldValue = [];
            $newValue = [];
            
            foreach ($financialFields as $field) {
                if (isset($changes[$field])) {
                    $oldValue[$field] = $original[$field];
                    $newValue[$field] = $changes[$field];
                }
            }
            
            $this->logAuditEvent('contribution_updated', $contribution, $oldValue, $newValue);
        }
    }

    /**
     * Handle the Contribution "deleted" event.
     */
    public function deleted(Contribution $contribution): void
    {
        $this->logAuditEvent('contribution_deleted', $contribution, $contribution->toArray(), null);
    }

    /**
     * Handle the Contribution "force deleted" event.
     */
    public function forceDeleted(Contribution $contribution): void
    {
        $this->logAuditEvent('contribution_force_deleted', $contribution, $contribution->toArray(), null);
    }

    /**
     * Log audit event to Tier-2 audit trail
     */
    protected function logAuditEvent(string $action, Contribution $contribution, ?array $oldValue, ?array $newValue): void
    {
        Log::channel('audit')->warning('Tier 2 Audit Log', [
            'tier' => 2,
            'action' => $action,
            'entity_id' => $contribution->id,
            'entity_type' => 'contribution',
            'old_value' => $oldValue ? json_encode($oldValue) : null,
            'new_value' => $newValue ? json_encode($newValue) : null,
            'user_id' => Auth::id() ?? 'system',
            'ip_address' => request()->ip(),
            'timestamp' => now()->toDateTimeString(),
        ]);
    }
}

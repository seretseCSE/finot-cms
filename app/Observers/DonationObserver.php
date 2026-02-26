<?php

namespace App\Observers;

use App\Models\Donation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class DonationObserver
{
    /**
     * Handle the Donation "created" event.
     */
    public function created(Donation $donation): void
    {
        $this->logAuditEvent('donation_created', $donation, null, $donation->toArray());
    }

    /**
     * Handle the Donation "updated" event.
     */
    public function updated(Donation $donation): void
    {
        $changes = $donation->getChanges();
        $original = $donation->getOriginal();
        
        // Only log if financial fields changed
        $financialFields = ['amount', 'donation_type'];
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
            
            $this->logAuditEvent('donation_updated', $donation, $oldValue, $newValue);
        }
    }

    /**
     * Handle the Donation "deleted" event.
     */
    public function deleted(Donation $donation): void
    {
        $this->logAuditEvent('donation_deleted', $donation, $donation->toArray(), null);
    }

    /**
     * Handle the Donation "force deleted" event.
     */
    public function forceDeleted(Donation $donation): void
    {
        $this->logAuditEvent('donation_force_deleted', $donation, $donation->toArray(), null);
    }

    /**
     * Log audit event to Tier-2 audit trail
     */
    protected function logAuditEvent(string $action, Donation $donation, ?array $oldValue, ?array $newValue): void
    {
        Log::channel('audit')->warning('Tier 2 Audit Log', [
            'tier' => 2,
            'action' => $action,
            'entity_id' => $donation->id,
            'entity_type' => 'donation',
            'old_value' => $oldValue ? json_encode($oldValue) : null,
            'new_value' => $newValue ? json_encode($newValue) : null,
            'user_id' => Auth::id() ?? 'system',
            'ip_address' => request()->ip(),
            'timestamp' => now()->toDateTimeString(),
        ]);
    }
}

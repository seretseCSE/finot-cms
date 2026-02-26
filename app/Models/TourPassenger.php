<?php

namespace App\Models;

use App\Models\BaseModel;
use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TourPassenger extends BaseModel
{
    use HasFactory, HasAuditLog;

    protected $fillable = [
        'passenger_code',
        'tour_id',
        'full_name',
        'phone',
        'passenger_count',
        'receipt_image',
        'member_id',
        'registration_type',
        'status',
        'registration_date',
        'registered_by',
        'cancellation_reason',
    ];

    protected $casts = [
        'passenger_count' => 'integer',
        'registration_date' => 'date',
    ];

    protected $dates = [
        'registration_date',
    ];

    public function tour()
    {
        return $this->belongsTo(Tour::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function registeredBy()
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    /**
     * Get formatted registration date in Ethiopian
     */
    public function getEthiopianRegistrationDateAttribute(): string
    {
        return app(\App\Helpers\EthiopianDateHelper::class)
            ->toEthiopian($this->registration_date)['month_name_am'] . ' ' . 
            app(\App\Helpers\EthiopianDateHelper::class)
            ->toEthiopian($this->registration_date)['day'] . ', ' .
            app(\App\Helpers\EthiopianDateHelper::class)
            ->toEthiopian($this->registration_date)['year'];
    }

    /**
     * Get status badge color
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'Pending' => 'yellow',
            'Confirmed' => 'green',
            'Cancelled' => 'red',
            default => 'gray',
        };
    }

    /**
     * Get registration type badge color
     */
    public function getRegistrationTypeColorAttribute(): string
    {
        return match($this->registration_type) {
            'Public' => 'blue',
            'Internal' => 'purple',
            default => 'gray',
        };
    }

    /**
     * Get receipt URL
     */
    public function getReceiptUrlAttribute(): ?string
    {
        if (!$this->receipt_image) {
            return null;
        }

        return asset('storage/receipts/tours/' . $this->tour_id . '/' . $this->receipt_image);
    }

    /**
     * Confirm passenger registration
     */
    public function confirm(): void
    {
        $this->update(['status' => 'Confirmed']);

        // Log to audit trail
        Log::channel('audit')->info('Tier 1 Audit Log', [
            'tier' => 1,
            'action' => 'tour_passenger_confirmed',
            'entity_id' => $this->id,
            'entity_type' => 'tour_passenger',
            'old_value' => json_encode(['status' => 'Pending']),
            'new_value' => json_encode(['status' => 'Confirmed']),
            'user_id' => Auth::id(),
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Cancel passenger registration
     */
    public function cancel(string $reason): void
    {
        $this->update([
            'status' => 'Cancelled',
            'cancellation_reason' => $reason,
        ]);

        // Log to audit trail
        Log::channel('audit')->info('Tier 1 Audit Log', [
            'tier' => 1,
            'action' => 'tour_passenger_cancelled',
            'entity_id' => $this->id,
            'entity_type' => 'tour_passenger',
            'old_value' => json_encode(['status' => $this->getOriginal('status')]),
            'new_value' => json_encode(['status' => 'Cancelled', 'reason' => $reason]),
            'user_id' => Auth::id(),
            'timestamp' => now()->toDateTimeString(),
        ]);
    }
}

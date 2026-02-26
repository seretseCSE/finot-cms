<?php

namespace App\Models;

use App\Models\BaseModel;
use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tour extends BaseModel
{
    use HasFactory, HasAuditLog;

    protected $fillable = [
        'place',
        'description',
        'tour_date',
        'start_time',
        'cost_per_person',
        'registration_deadline',
        'max_capacity',
        'status',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'created_by',
    ];

    protected $casts = [
        'tour_date' => 'date',
        'start_time' => 'datetime:H:i',
        'cost_per_person' => 'decimal:2',
        'registration_deadline' => 'date',
        'max_capacity' => 'integer',
        'cancelled_at' => 'datetime',
    ];

    protected $dates = [
        'tour_date',
        'registration_deadline',
        'cancelled_at',
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cancelledBy()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function passengers(): HasMany
    {
        return $this->hasMany(TourPassenger::class);
    }

    public function confirmedPassengers(): HasMany
    {
        return $this->hasMany(TourPassenger::class)->where('status', 'Confirmed');
    }

    /**
     * Get formatted tour date in Ethiopian
     */
    public function getEthiopianDateAttribute(): string
    {
        return app(\App\Helpers\EthiopianDateHelper::class)
            ->toEthiopian($this->tour_date)['month_name_am'] . ' ' . 
            app(\App\Helpers\EthiopianDateHelper::class)
            ->toEthiopian($this->tour_date)['day'] . ', ' .
            app(\App\Helpers\EthiopianDateHelper::class)
            ->toEthiopian($this->tour_date)['year'];
    }

    /**
     * Get formatted cost
     */
    public function getFormattedCostAttribute(): string
    {
        if ($this->cost_per_person) {
            return 'Birr ' . number_format($this->cost_per_person, 2);
        }
        return 'Free';
    }

    /**
     * Get remaining capacity
     */
    public function getRemainingCapacityAttribute(): int
    {
        if (!$this->max_capacity) {
            return 999; // Unlimited
        }

        $confirmedCount = $this->confirmedPassengers->sum('passenger_count');
        return max(0, $this->max_capacity - $confirmedCount);
    }

    /**
     * Check if tour is full
     */
    public function getIsFullAttribute(): bool
    {
        if (!$this->max_capacity) {
            return false;
        }

        return $this->remaining_capacity <= 0;
    }

    /**
     * Check if registration is open
     */
    public function getIsRegistrationOpenAttribute(): bool
    {
        if ($this->status !== 'Published') {
            return false;
        }

        if ($this->registration_deadline && now()->isAfter($this->registration_deadline)) {
            return false;
        }

        if ($this->is_full) {
            return false;
        }

        return true;
    }

    /**
     * Get status badge color
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'Draft' => 'gray',
            'Published' => 'blue',
            'In Progress' => 'yellow',
            'Completed' => 'green',
            'Cancelled' => 'red',
            default => 'gray',
        };
    }

    /**
     * Check if tour can be deleted
     */
    public function canBeDeleted(): bool
    {
        return $this->passengers->isEmpty();
    }

    /**
     * Check if tour date can be edited
     */
    public function canEditDate(): bool
    {
        return !in_array($this->status, ['In Progress', 'Completed']);
    }

    /**
     * Cancel the tour
     */
    public function cancel(string $reason, int $cancelledBy): void
    {
        $this->update([
            'status' => 'Cancelled',
            'cancelled_at' => now(),
            'cancelled_by' => $cancelledBy,
            'cancellation_reason' => $reason,
        ]);

        // Cancel all confirmed passengers
        $this->confirmedPassengers()->update([
            'status' => 'Cancelled',
            'cancellation_reason' => 'Tour cancelled: ' . $reason,
        ]);

        // Log to audit trail
        Log::channel('audit')->warning('Tier 2 Audit Log', [
            'tier' => 2,
            'action' => 'tour_cancelled',
            'entity_id' => $this->id,
            'entity_type' => 'tour',
            'old_value' => json_encode(['status' => $this->getOriginal('status')]),
            'new_value' => json_encode(['status' => 'Cancelled', 'reason' => $reason]),
            'user_id' => $cancelledBy,
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Get resource name for permissions
     */
    public static function getResourceName(): string
    {
        return 'tours';
    }

    /**
     * Get navigation label for resource
     */
    public static function getNavigationLabel(): string
    {
        return 'Tours';
    }

    /**
     * Get navigation icon for resource
     */
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-map';
    }

    /**
     * Get navigation group for resource
     */
    public static function getNavigationGroup(): ?string
    {
        return 'Tours';
    }
}

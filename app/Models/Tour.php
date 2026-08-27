<?php

namespace App\Models;

use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\Log;

class Tour extends BaseModel
{
    use HasAuditLog;
    use HasFactory;

    protected $fillable = [
        'place',
        'description',
        'image',
        'tour_date',
        'end_date',
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

    /**
     * Validation rules for tour creation and updates.
     */
    public static function rules(): array
    {
        return [
            'max_capacity' => [
                'required',
                'integer',
                'min:1',
                'max:1000',
            ],
            'cost_per_person' => [
                'required',
                'numeric',
                'min:0',
                'max:999999.99',
            ],
            'tour_date' => [
                'required',
                'date',
                'after_or_equal:today',
            ],
            'end_date' => [
                'nullable',
                'date',
                'after_or_equal:tour_date',
            ],
            'registration_deadline' => [
                'required',
                'date',
                'after_or_equal:today',
                'before_or_equal:tour_date',
            ],
        ];
    }

    protected $casts = [
        'tour_date' => 'date',
        'end_date' => 'date',
        'start_time' => 'datetime:H:i',
        'cost_per_person' => 'decimal:2',
        'registration_deadline' => 'date',
        'max_capacity' => 'integer',
        'cancelled_at' => 'datetime',
    ];

    protected $dates = [
        'tour_date',
        'end_date',
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
     * Check if tour has reached capacity
     */
    public function isAtCapacity(): bool
    {
        return $this->confirmedPassengers()->count() >= $this->max_capacity;
    }

    /**
     * Get remaining capacity
     */
    public function getRemainingCapacity(): int
    {
        return max(0, $this->max_capacity - $this->confirmedPassengers()->count());
    }

    /**
     * Check if registration is still open
     */
    public function isRegistrationOpen(): bool
    {
        return !$this->isAtCapacity() &&
               $this->registration_deadline &&
               !$this->registration_deadline->startOfDay()->isBefore(now()->startOfDay());
    }

    /**
     * Validate tour booking capacity
     */
    public function validateBookingCapacity(): array
    {
        if ($this->isAtCapacity()) {
            return [
                'capacity' => 'This tour has reached maximum capacity.',
                'remaining_capacity' => 0
            ];
        }

        if ($this->registration_deadline && $this->registration_deadline->startOfDay()->isBefore(now()->startOfDay())) {
            return [
                'deadline' => 'Registration deadline has passed.',
                'deadline_date' => $this->registration_deadline->format('M d, Y')
            ];
        }

        return [];
    }

    public function attendanceSessions(): HasMany
    {
        return $this->hasMany(TourAttendanceSession::class);
    }

    public function attendanceRecords(): HasManyThrough
    {
        return $this->hasManyThrough(
            TourAttendance::class,
            TourAttendanceSession::class,
            'tour_id',
            'session_id',
            'id',
            'id'
        );
    }

    public function autoCreateAttendanceSession(): ?TourAttendanceSession
    {
        if ($this->attendanceSessions()->exists()) {
            return null;
        }

        $createdBy = auth()->id();

        if ($createdBy === null) {
            return null;
        }

        $session = TourAttendanceSession::create([
            'tour_id' => $this->id,
            'session_date' => $this->tour_date,
            'status' => 'Open',
            'created_by' => $createdBy,
        ]);

        $this->confirmedPassengers->each(function ($passenger) use ($session) {
            $session->attendanceRecords()->create([
                'passenger_id' => $passenger->id,
                'status' => 'Not Present',
            ]);
        });

        return $session;
    }

    /**
     * Get formatted tour date in Ethiopian
     */
    public function getEthiopianDateAttribute(): string
    {
        return app(\App\Helpers\EthiopianDateHelper::class)
            ->toEthiopian($this->tour_date)['month_name_am'].' '.
            app(\App\Helpers\EthiopianDateHelper::class)
                ->toEthiopian($this->tour_date)['day'].', '.
            app(\App\Helpers\EthiopianDateHelper::class)
                ->toEthiopian($this->tour_date)['year'];
    }

    /**
     * Get formatted end date in Ethiopian
     */
    public function getEthiopianEndDateAttribute(): ?string
    {
        if (! $this->end_date) {
            return null;
        }

        return app(\App\Helpers\EthiopianDateHelper::class)
            ->toEthiopian($this->end_date)['month_name_am'].' '.
            app(\App\Helpers\EthiopianDateHelper::class)
                ->toEthiopian($this->end_date)['day'].', '.
            app(\App\Helpers\EthiopianDateHelper::class)
                ->toEthiopian($this->end_date)['year'];
    }

    /**
     * Get formatted registration deadline in Ethiopian
     */
    public function getEthiopianRegistrationDeadlineAttribute(): ?string
    {
        if (! $this->registration_deadline) {
            return null;
        }

        return app(\App\Helpers\EthiopianDateHelper::class)
            ->toEthiopian($this->registration_deadline)['month_name_am'].' '.
            app(\App\Helpers\EthiopianDateHelper::class)
                ->toEthiopian($this->registration_deadline)['day'].', '.
            app(\App\Helpers\EthiopianDateHelper::class)
                ->toEthiopian($this->registration_deadline)['year'];
    }

    /**
     * Get days left until tour date
     */
    public function getDaysLeftAttribute(): ?int
    {
        $effectiveDate = $this->end_date ?? $this->tour_date;
        if (! $effectiveDate) {
            return null;
        }

        $today = now()->startOfDay();
        $target = $effectiveDate->startOfDay();

        return $today->diffInDays($target, false);
    }

    /**
     * Get formatted cost
     */
    public function getFormattedCostAttribute(): string
    {
        if ($this->cost_per_person) {
            return 'Birr '.number_format($this->cost_per_person, 2);
        }

        return 'Free';
    }

    /**
     * Get remaining capacity
     */
    public function getRemainingCapacityAttribute(): int
    {
        if (! $this->max_capacity) {
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
        if (! $this->max_capacity) {
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

        if ($this->registration_deadline && $this->registration_deadline->startOfDay()->isBefore(now()->startOfDay())) {
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
        return match ($this->status) {
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
        return ! in_array($this->status, ['In Progress', 'Completed']);
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
            'cancellation_reason' => 'Tour cancelled: '.$reason,
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

    /**
     * Auto-update status based on tour date and registration status.
     */
    public function updateStatusIfNeeded(): void
    {
        if ($this->status === 'Cancelled') {
            return;
        }

        $today = now()->startOfDay();
        $effectiveEndDate = $this->end_date ?? $this->tour_date;
        $effectiveEndDate = $effectiveEndDate ? $effectiveEndDate->startOfDay() : null;

        // If end date has passed, mark as Completed
        if ($effectiveEndDate && $effectiveEndDate->isBefore($today) && $this->status !== 'Completed') {
            $this->update(['status' => 'Completed']);
            $this->autoCreateAttendanceSession();
            return;
        }

        // If end date is today (or start date is today with no end date), mark as In Progress
        if ($effectiveEndDate && $effectiveEndDate->isSameDay($today) && $this->status !== 'In Progress' && $this->status !== 'Completed') {
            $this->update(['status' => 'In Progress']);
            $this->autoCreateAttendanceSession();
            return;
        }
    }

    protected static function booted(): void
    {
        static::saving(function (self $tour) {
            if ($tour->status === 'Cancelled') {
                return;
            }

            // Respect explicit status change on existing records
            if ($tour->exists && $tour->isDirty('status')) {
                return;
            }

            $today = now()->startOfDay();
            $effectiveEndDate = $tour->end_date ?? $tour->tour_date;
            $effectiveEndDate = $effectiveEndDate ? $effectiveEndDate->startOfDay() : null;

            // If end date has passed, mark as Completed
            if ($effectiveEndDate && $effectiveEndDate->isBefore($today)) {
                $tour->status = 'Completed';
                return;
            }

            // If end date is today or tour date is today and no end date, mark as In Progress
            if ($effectiveEndDate && $effectiveEndDate->isSameDay($today)) {
                if ($tour->status !== 'Completed') {
                    $tour->status = 'In Progress';
                }
                return;
            }
        });

        static::saved(function (self $tour) {
            if ($tour->wasChanged('status') && in_array($tour->status, ['In Progress', 'Completed'])) {
                $tour->autoCreateAttendanceSession();
            }
        });
    }
}

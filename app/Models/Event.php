<?php

namespace App\Models;

use App\Models\BaseModel;
use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends BaseModel
{
    use HasFactory, HasAuditLog;

    protected $fillable = [
        'name',
        'date_time',
        'location',
        'description',
        'featured_image',
        'registration_required',
        'max_capacity',
        'registration_deadline',
        'status',
        'recurrence_type',
        'recurrence_end_date',
        'parent_event_id',
        'created_by',
    ];

    protected $casts = [
        'date_time' => 'datetime',
        'registration_required' => 'boolean',
        'max_capacity' => 'integer',
        'recurrence_end_date' => 'date',
    ];

    protected $dates = [
        'date_time',
        'registration_deadline',
        'recurrence_end_date',
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function parentEvent()
    {
        return $this->belongsTo(Event::class, 'parent_event_id');
    }

    public function childEvents(): HasMany
    {
        return $this->hasMany(Event::class, 'parent_event_id');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    /**
     * Get registration count
     */
    public function getRegistrationCountAttribute(): int
    {
        return $this->registrations()->where('status', 'Confirmed')->count();
    }

    /**
     * Get formatted date in Ethiopian
     */
    public function getEthiopianDateAttribute(): string
    {
        return app(\App\Helpers\EthiopianDateHelper::class)
            ->toEthiopian($this->date_time)['month_name_am'] . ' ' . 
            app(\App\Helpers\EthiopianDateHelper::class)
            ->toEthiopian($this->date_time)['day'] . ', ' .
            app(\App\Helpers\EthiopianDateHelper::class)
            ->toEthiopian($this->date_time)['year'];
    }

    /**
     * Get formatted time
     */
    public function getFormattedTimeAttribute(): string
    {
        return $this->date_time->format('g:i A');
    }

    /**
     * Get status color
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'Draft' => 'gray',
            'Published' => 'blue',
            'Full' => 'warning',
            'Ongoing' => 'green',
            'Completed' => 'success',
            'Cancelled' => 'red',
            default => 'gray',
        };
    }

    /**
     * Check if event is full
     */
    public function isFull(): bool
    {
        if (!$this->max_capacity || !$this->registration_required) {
            return false;
        }

        return $this->registrations()->where('status', 'Confirmed')->count() >= $this->max_capacity;
    }

    /**
     * Get remaining capacity
     */
    public function getRemainingCapacityAttribute(): int
    {
        if (!$this->max_capacity) {
            return 0;
        }

        return max(0, $this->max_capacity - $this->registration_count);
    }

    /**
     * Generate recurring instances
     */
    public function generateRecurringInstances(): void
    {
        if ($this->recurrence_type === 'None' || !$this->recurrence_end_date) {
            return;
        }

        $currentDate = $this->date_time->copy();
        $endDate = $this->recurrence_end_date;

        while ($currentDate->lte($endDate)) {
            switch ($this->recurrence_type) {
                case 'Weekly':
                    $currentDate->addWeek();
                    break;
                case 'Monthly':
                    $currentDate->addMonth();
                    break;
                case 'Custom':
                    // For custom recurrence, you might want to implement custom logic
                    break;
            }

            if ($currentDate->lte($endDate)) {
                Event::create([
                    'name' => $this->name,
                    'date_time' => $currentDate,
                    'location' => $this->location,
                    'description' => $this->description,
                    'featured_image' => $this->featured_image,
                    'registration_required' => $this->registration_required,
                    'max_capacity' => $this->max_capacity,
                    'registration_deadline' => $this->registration_deadline,
                    'status' => 'Draft',
                    'recurrence_type' => 'None', // Generated instances don't recur
                    'parent_event_id' => $this->id,
                    'created_by' => $this->created_by,
                ]);
            }
        }
    }

    /**
     * Get resource name for permissions
     */
    public static function getResourceName(): string
    {
        return 'events';
    }

    /**
     * Get navigation label for resource
     */
    public static function getNavigationLabel(): string
    {
        return 'Events';
    }

    /**
     * Get navigation icon for resource
     */
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-calendar-days';
    }

    /**
     * Get navigation group for resource
     */
    public static function getNavigationGroup(): ?string
    {
        return 'Events & Fundraising';
    }
}

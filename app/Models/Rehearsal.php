<?php

namespace App\Models;

use App\Models\BaseModel;
use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rehearsal extends BaseModel
{
    use HasFactory, HasAuditLog;

    protected $fillable = [
        'date_time',
        'location',
        'status',
        'recurrence_type',
        'recurrence_end_date',
        'songs',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'date_time' => 'datetime',
        'recurrence_end_date' => 'date',
        'songs' => 'array',
    ];

    protected $dates = [
        'date_time',
        'recurrence_end_date',
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(RehearsalAttendance::class);
    }

    public function songs(): BelongsToMany
    {
        return $this->belongsToMany(Song::class, 'rehearsal_songs');
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
     * Get attendance summary
     */
    public function getAttendanceSummaryAttribute(): array
    {
        $attendance = $this->attendance;
        
        return [
            'present' => $attendance->where('status', 'Present')->count(),
            'absent' => $attendance->where('status', 'Absent')->count(),
            'excused' => $attendance->where('status', 'Excused')->count(),
            'late' => $attendance->where('status', 'Late')->count(),
            'permission' => $attendance->where('status', 'Permission')->count(),
            'total' => $attendance->count(),
            'attendance_rate' => $attendance->isNotEmpty() 
                ? round(($attendance->where('status', 'Present')->count() / $attendance->count()) * 100, 2)
                : 0,
        ];
    }

    /**
     * Get status color
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'Scheduled' => 'blue',
            'Completed' => 'green',
            'Cancelled' => 'red',
            default => 'gray',
        };
    }

    /**
     * Generate recurring rehearsals
     */
    public function generateRecurringRehearsals(): void
    {
        if ($this->recurrence_type === 'None' || !$this->recurrence_end_date) {
            return;
        }

        $currentDate = $this->date_time->copy();
        $endDate = $this->recurrence_end_date;

        while ($currentDate->lt($endDate)) {
            switch ($this->recurrence_type) {
                case 'Weekly':
                    $currentDate->addWeek();
                    break;
                case 'Biweekly':
                    $currentDate->addWeeks(2);
                    break;
                case 'Monthly':
                    $currentDate->addMonth();
                    break;
            }

            if ($currentDate->lte($endDate)) {
                Rehearsal::create([
                    'date_time' => $currentDate,
                    'location' => $this->location,
                    'status' => 'Scheduled',
                    'recurrence_type' => 'None', // Generated rehearsals don't recur
                    'songs' => $this->songs,
                    'notes' => $this->notes,
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
        return 'rehearsals';
    }

    /**
     * Get navigation label for resource
     */
    public static function getNavigationLabel(): string
    {
        return 'Rehearsals';
    }

    /**
     * Get navigation icon for resource
     */
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-calendar';
    }

    /**
     * Get navigation group for resource
     */
    public static function getNavigationGroup(): ?string
    {
        return 'Worship & Media';
    }
}

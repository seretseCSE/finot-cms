<?php

namespace App\Models;

use App\Models\BaseModel;
use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TourAttendanceSession extends BaseModel
{
    use HasFactory, HasAuditLog;

    protected $fillable = [
        'tour_id',
        'session_date',
        'status',
        'created_by',
    ];

    protected $casts = [
        'session_date' => 'date',
    ];

    protected $dates = [
        'session_date',
    ];

    public function tour()
    {
        return $this->belongsTo(Tour::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(TourAttendance::class, 'session_id');
    }

    /**
     * Get formatted session date in Ethiopian
     */
    public function getEthiopianSessionDateAttribute(): string
    {
        return app(\App\Helpers\EthiopianDateHelper::class)
            ->toEthiopian($this->session_date)['month_name_am'] . ' ' . 
            app(\App\Helpers\EthiopianDateHelper::class)
            ->toEthiopian($this->session_date)['day'] . ', ' .
            app(\App\Helpers\EthiopianDateHelper::class)
            ->toEthiopian($this->session_date)['year'];
    }

    /**
     * Get attendance summary
     */
    public function getAttendanceSummaryAttribute(): array
    {
        $present = $this->attendanceRecords()->where('status', 'Present')->count();
        $notPresent = $this->attendanceRecords()->where('status', 'Not Present')->count();
        $total = $this->attendanceRecords()->count();

        return [
            'present' => $present,
            'not_present' => $notPresent,
            'total' => $total,
            'present_percentage' => $total > 0 ? round(($present / $total) * 100, 2) : 0,
        ];
    }

    /**
     * Complete the attendance session
     */
    public function complete(): void
    {
        $this->update(['status' => 'Completed']);

        // Log to audit trail
        Log::channel('audit')->info('Tier 1 Audit Log', [
            'tier' => 1,
            'action' => 'tour_attendance_completed',
            'entity_id' => $this->id,
            'entity_type' => 'tour_attendance_session',
            'old_value' => json_encode(['status' => 'Open']),
            'new_value' => json_encode(['status' => 'Completed']),
            'user_id' => Auth::id(),
            'timestamp' => now()->toDateTimeString(),
        ]);
    }
}

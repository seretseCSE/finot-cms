<?php

namespace App\Models;

use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TourAttendanceSession extends BaseModel
{
    use HasAuditLog;
    use HasFactory;

    protected $fillable = [
        'tour_id',
        'session_date',
        'status',
        'created_by',
        'locked_at',
        'locked_by',
        'locked_reason',
    ];

    protected $casts = [
        'session_date' => 'date',
        'locked_at' => 'datetime',
    ];

    protected $dates = [
        'session_date',
        'locked_at',
    ];

    public function tour()
    {
        return $this->belongsTo(Tour::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lockedBy()
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(TourAttendance::class, 'session_id');
    }

    public function getEthiopianSessionDateAttribute(): string
    {
        return app(\App\Helpers\EthiopianDateHelper::class)
            ->toEthiopian($this->session_date)['month_name_am'].' '.
            app(\App\Helpers\EthiopianDateHelper::class)
                ->toEthiopian($this->session_date)['day'].', '.
            app(\App\Helpers\EthiopianDateHelper::class)
                ->toEthiopian($this->session_date)['year'];
    }

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

    public function complete(): void
    {
        $this->update(['status' => 'Completed']);

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

    public function lock(string $reason): void
    {
        $this->update([
            'status' => 'Locked',
            'locked_at' => now(),
            'locked_by' => Auth::id(),
            'locked_reason' => $reason,
        ]);

        Log::channel('audit')->warning('Tier 2 Audit Log', [
            'tier' => 2,
            'action' => 'tour_attendance_session_locked',
            'entity_id' => $this->id,
            'entity_type' => 'tour_attendance_session',
            'old_value' => json_encode(['status' => $this->getOriginal('status')]),
            'new_value' => json_encode(['status' => 'Locked', 'reason' => $reason]),
            'user_id' => Auth::id(),
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    public function unlock(string $reason): void
    {
        $this->update([
            'status' => 'Open',
            'locked_at' => null,
            'locked_by' => null,
            'locked_reason' => null,
        ]);

        Log::channel('audit')->warning('Tier 2 Audit Log', [
            'tier' => 2,
            'action' => 'tour_attendance_session_unlocked',
            'entity_id' => $this->id,
            'entity_type' => 'tour_attendance_session',
            'old_value' => json_encode(['status' => 'Locked']),
            'new_value' => json_encode(['status' => 'Open', 'reason' => $reason]),
            'user_id' => Auth::id(),
            'timestamp' => now()->toDateTimeString(),
        ]);
    }
}

<?php

namespace App\Models;

use App\Models\BaseModel;
use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TourAttendance extends BaseModel
{
    use HasFactory, HasAuditLog;

    protected $fillable = [
        'session_id',
        'passenger_id',
        'status',
        'marked_at',
        'marked_by',
        'notes',
    ];

    protected $casts = [
        'marked_at' => 'datetime',
    ];

    protected $dates = [
        'marked_at',
    ];

    public function session()
    {
        return $this->belongsTo(TourAttendanceSession::class, 'session_id');
    }

    public function passenger()
    {
        return $this->belongsTo(TourPassenger::class, 'passenger_id');
    }

    public function markedBy()
    {
        return $this->belongsTo(User::class, 'marked_by');
    }

    /**
     * Mark passenger as present
     */
    public function markPresent(?string $notes = null): void
    {
        $this->update([
            'status' => 'Present',
            'marked_at' => now(),
            'marked_by' => Auth::id(),
            'notes' => $notes,
        ]);

        // Log to audit trail
        Log::channel('audit')->info('Tier 1 Audit Log', [
            'tier' => 1,
            'action' => 'tour_passenger_marked_present',
            'entity_id' => $this->id,
            'entity_type' => 'tour_attendance',
            'old_value' => json_encode(['status' => 'Not Present']),
            'new_value' => json_encode(['status' => 'Present', 'notes' => $notes]),
            'user_id' => Auth::id(),
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Mark passenger as not present
     */
    public function markNotPresent(?string $notes = null): void
    {
        $this->update([
            'status' => 'Not Present',
            'marked_at' => now(),
            'marked_by' => Auth::id(),
            'notes' => $notes,
        ]);

        // Log to audit trail
        Log::channel('audit')->info('Tier 1 Audit Log', [
            'tier' => 1,
            'action' => 'tour_passenger_marked_not_present',
            'entity_id' => $this->id,
            'entity_type' => 'tour_attendance',
            'old_value' => json_encode(['status' => 'Present']),
            'new_value' => json_encode(['status' => 'Not Present', 'notes' => $notes]),
            'user_id' => Auth::id(),
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Get status color for display
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'Present' => 'green',
            'Not Present' => 'red',
            default => 'gray',
        };
    }
}

<?php

namespace App\Models;

use App\Models\BaseModel;
use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RehearsalAttendance extends BaseModel
{
    use HasFactory, HasAuditLog;

    protected $fillable = [
        'rehearsal_id',
        'member_id',
        'status',
        'marked_by',
        'marked_at',
    ];

    protected $casts = [
        'marked_at' => 'datetime',
    ];

    protected $dates = [
        'marked_at',
    ];

    public function rehearsal()
    {
        return $this->belongsTo(Rehearsal::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function markedBy()
    {
        return $this->belongsTo(User::class, 'marked_by');
    }

    /**
     * Get status color for display
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'Present' => 'green',
            'Absent' => 'red',
            'Excused' => 'yellow',
            'Late' => 'orange',
            'Permission' => 'blue',
            default => 'gray',
        };
    }

    /**
     * Mark attendance
     */
    public function markAttendance(string $status): void
    {
        $this->update([
            'status' => $status,
            'marked_at' => now(),
            'marked_by' => Auth::id(),
        ]);

        // Log to audit trail
        Log::channel('audit')->info('Tier 1 Audit Log', [
            'tier' => 1,
            'action' => 'rehearsal_attendance_marked',
            'entity_id' => $this->id,
            'entity_type' => 'rehearsal_attendance',
            'old_value' => json_encode(['status' => $this->getOriginal('status')]),
            'new_value' => json_encode(['status' => $status]),
            'user_id' => Auth::id(),
            'timestamp' => now()->toDateTimeString(),
        ]);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfflineAttendanceSync extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'session_id',
        'student_id',
        'member_id',
        'status',
        'marked_at',
        'sync_status',
        'synced_at',
        'conflict_reason',
    ];

    protected $casts = [
        'marked_at' => 'datetime',
        'synced_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class, 'session_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'student_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    public function scopePending($query)
    {
        return $query->where('sync_status', 'pending');
    }

    public function scopeSynced($query)
    {
        return $query->where('sync_status', 'synced');
    }

    public function scopeConflicts($query)
    {
        return $query->where('sync_status', 'conflict');
    }

    public function markAsSynced(): void
    {
        $this->update([
            'sync_status' => 'synced',
            'synced_at' => now(),
            'conflict_reason' => null,
        ]);
    }

    public function markAsConflict(string $reason): void
    {
        $this->update([
            'sync_status' => 'conflict',
            'conflict_reason' => $reason,
        ]);
    }
}

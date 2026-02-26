<?php

namespace App\Models;

use App\Models\BaseModel;
use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StudentAttendance extends BaseModel
{
    use HasFactory, HasAuditLog;

    protected $fillable = [
        'student_id',
        'session_id',
        'status',
        'marked_by',
        'marked_at',
        'sync_timestamp',
        'is_synced',
    ];

    protected $casts = [
        'marked_at' => 'datetime',
        'sync_timestamp' => 'datetime',
        'is_synced' => 'boolean',
    ];

    public function student()
    {
        return $this->belongsTo(Member::class, 'student_id');
    }

    public function session()
    {
        return $this->belongsTo(AttendanceSession::class, 'session_id');
    }

    public function markedBy()
    {
        return $this->belongsTo(User::class, 'marked_by');
    }
}

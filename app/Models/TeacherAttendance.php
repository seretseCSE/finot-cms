<?php

namespace App\Models;

use App\Models\BaseModel;
use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TeacherAttendance extends BaseModel
{
    use HasFactory, HasAuditLog;

    protected $fillable = [
        'teacher_id',
        'session_id',
        'attendance_status',
        'marked_by',
        'marked_at',
        'session_outcome',
        'substitute_teacher_name',
        'notes',
    ];

    protected $casts = [
        'marked_at' => 'datetime',
    ];

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    public function session()
    {
        return $this->belongsTo(AttendanceSession::class, 'session_id');
    }

    public function markedBy()
    {
        return $this->belongsTo(User::class, 'marked_by');
    }

    public function scopeForSession($query, $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }
}

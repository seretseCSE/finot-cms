<?php

namespace App\Models;

use App\Models\BaseModel;
use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceSession extends BaseModel
{
    use HasFactory, HasAuditLog;

    protected $fillable = [
        'class_id',
        'session_date',
        'academic_year_id',
        'status',
        'locked_at',
        'locked_by',
        'unlock_justification',
        'unlocked_at',
        'unlocked_by',
        'created_by',
    ];

    protected $casts = [
        'session_date' => 'date',
        'locked_at' => 'datetime',
        'unlocked_at' => 'datetime',
    ];

    public function class()
    {
        return $this->belongsTo(ClassModel::class, 'class_id');
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lockedBy()
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function unlockedBy()
    {
        return $this->belongsTo(User::class, 'unlocked_by');
    }

    public function studentAttendance(): HasMany
    {
        return $this->hasMany(StudentAttendance::class, 'session_id');
    }

    public function teacherAttendance(): HasMany
    {
        return $this->hasMany(TeacherAttendance::class, 'session_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'Open');
    }

    public function scopeLocked($query)
    {
        return $query->where('status', 'Locked');
    }

    public function isLocked(): bool
    {
        return $this->status === 'Locked';
    }

    public function canBeMarked(): bool
    {
        return $this->status === 'Open';
    }
}

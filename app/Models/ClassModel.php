<?php

namespace App\Models;

use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClassModel extends BaseModel
{
    use HasFactory, HasAuditLog, SoftDeletes;

    protected $table = 'classes';

    protected $fillable = [
        'name',
        'description',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function enrollments()
    {
        return $this->hasMany(StudentEnrollment::class, 'class_id');
    }

    public function attendanceSessions()
    {
        return $this->hasMany(AttendanceSession::class, 'class_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function teacherAssignments()
    {
        return $this->hasMany(TeacherAssignment::class, 'class_id');
    }

    public function canBeDeleted(): bool
    {
        $hasActiveEnrollments = $this->enrollments()->where('status', 'Enrolled')->exists();
        $hasAttendance = $this->attendanceSessions()->exists();

        return ! $hasActiveEnrollments && ! $hasAttendance;
    }
}

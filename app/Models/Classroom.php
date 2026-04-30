<?php

namespace App\Models;

use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Classroom extends BaseModel
{
    use HasFactory;
    use HasAuditLog;
    use SoftDeletes;

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

    public function teacherAssignments()
    {
        return $this->hasMany(TeacherAssignment::class, 'class_id');
    }

    public function members()
    {
        return $this->hasManyThrough(
            Member::class,
            StudentEnrollment::class,
            'class_id',
            'id',
            'id',
            'member_id'
        )->where('student_enrollments.status', 'Enrolled');
    }

    public function attendanceSessions()
    {
        return $this->hasMany(AttendanceSession::class, 'class_id');
    }

    // Scope for active classrooms
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

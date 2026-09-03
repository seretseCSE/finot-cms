<?php

namespace App\Models;

use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClassModel extends BaseModel
{
    use HasFactory;
    use HasAuditLog;
    use SoftDeletes;

    protected $table = 'classes';

    protected $fillable = [
        'name',
        'description',
        'program_year',
        'is_active',
        'created_by',
        'facility_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'program_year' => 'integer',
    ];

    public static function getResourceName(): string
    {
        return 'classes';
    }

    public function facility()
    {
        return $this->belongsTo(Facility::class);
    }

    protected static function newFactory()
    {
        return \Database\Factories\SchoolClassFactory::new();
    }

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
        return $this->belongsToMany(AttendanceSession::class, 'session_classes', 'class_id', 'session_id');
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

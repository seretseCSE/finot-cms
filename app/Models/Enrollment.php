<?php

namespace App\Models;

use App\Models\BaseModel;
use App\Models\Traits\HasAuditLog;
use App\Models\Traits\GeneratesAutoId;
use App\Models\Traits\ScopedByDepartment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Enrollment extends BaseModel
{
    use HasFactory, ScopedByDepartment, HasAuditLog, GeneratesAutoId;

    protected $fillable = [
        'student_id',
        'school_class_id',
        'enrollment_date',
        'academic_year_id',
        'department_id',
        'is_active',
    ];

    protected $casts = [
        'enrollment_date' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Get the student for this enrollment.
     */
    public function student()
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * Get the class for this enrollment.
     */
    public function schoolClass()
    {
        return $this->belongsTo(\App\Models\SchoolClass::class);
    }

    /**
     * Get the academic year for this enrollment.
     */
    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * Get the department for this enrollment.
     */
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the resource name for permissions.
     */
    public static function getResourceName(): string
    {
        return 'enrollments';
    }

    /**
     * Get the navigation label for the resource.
     */
    public static function getNavigationLabel(): string
    {
        return 'Enrollments';
    }

    /**
     * Get the navigation icon for the resource.
     */
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-academic-cap';
    }

    /**
     * Get the navigation group for the resource.
     */
    public static function getNavigationGroup(): ?string
    {
        return 'Education';
    }
}

<?php

namespace App\Models;

use App\Models\Traits\HasAuditLog;
use App\Models\Traits\GeneratesAutoId;
use App\Models\Traits\ScopedByDepartment;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Promotion extends BaseModel
{
    use HasFactory;
    use ScopedByDepartment;
    use HasAuditLog;
    use GeneratesAutoId;

    protected $fillable = [
        'student_id',
        'from_class_id',
        'to_class_id',
        'promotion_date',
        'academic_year_id',
        'reason',
        'department_id',
        'is_active',
    ];

    protected $casts = [
        'promotion_date' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Get the student for this promotion.
     */
    public function student()
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * Get the from class for this promotion.
     */
    public function fromClass()
    {
        return $this->belongsTo(\App\Models\SchoolClass::class);
    }

    /**
     * Get the to class for this promotion.
     */
    public function toClass()
    {
        return $this->belongsTo(\App\Models\SchoolClass::class);
    }

    /**
     * Get the academic year for this promotion.
     */
    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * Get the department for this promotion.
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
        return 'promotions';
    }

    /**
     * Get the navigation label for the resource.
     */
    public static function getNavigationLabel(): string
    {
        return 'Promotions';
    }

    /**
     * Get the navigation icon for the resource.
     */
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-arrow-up';
    }

    /**
     * Get the navigation group for the resource.
     */
    public static function getNavigationGroup(): ?string
    {
        return 'Education';
    }
}

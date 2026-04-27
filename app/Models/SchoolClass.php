<?php

namespace App\Models;

use App\Models\Traits\HasAuditLog;
use App\Models\Traits\GeneratesAutoId;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SchoolClass extends BaseModel
{
    use HasFactory;
    use HasAuditLog;
    use GeneratesAutoId;

    protected $table = 'classes';

    protected $fillable = [
        'name',
        'room_number',
        'max_students',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the academic year for this class.
     */
    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * Get the department for this class.
     */
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the enrollments for this class.
     */
    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * Get the resource name for permissions.
     */
    public static function getResourceName(): string
    {
        return 'classes';
    }

    /**
     * Get the navigation label for the resource.
     */
    public static function getNavigationLabel(): string
    {
        return 'Classes';
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

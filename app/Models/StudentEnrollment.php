<?php

namespace App\Models;

use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StudentEnrollment extends BaseModel
{
    use HasFactory;
    use HasAuditLog;

    protected $fillable = [
        'member_id',
        'class_id',
        'academic_year_id',
        'enrolled_date',
        'completion_date',
        'status',
        'withdrawal_reason',
        'withdrawal_notes',
        'enrolled_by',
        'completed_by',
        'promoted_to_enrollment_id',
    ];

    protected $casts = [
        'enrolled_date' => 'date',
        'completion_date' => 'date',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function class()
    {
        return $this->belongsTo(ClassModel::class, 'class_id');
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    public function enrolledBy()
    {
        return $this->belongsTo(User::class, 'enrolled_by');
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function promotedTo()
    {
        return $this->belongsTo(self::class, 'promoted_to_enrollment_id');
    }

    public function promotedFrom()
    {
        return $this->hasOne(self::class, 'promoted_to_enrollment_id');
    }

    /**
     * Get the student's full name with proper formatting
     */
    public function getStudentFullNameAttribute(): string
    {
        $member = $this->member;

        if (! $member) {
            return 'Unknown Student';
        }

        $parts = array_filter([
            $member->first_name,
            $member->father_name,
            $member->grandfather_name
        ]);

        return implode(' ', $parts);
    }
}

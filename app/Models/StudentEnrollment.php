<?php

namespace App\Models;

use App\Models\BaseModel;
use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentEnrollment extends BaseModel
{
    use HasFactory, HasAuditLog;

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
}

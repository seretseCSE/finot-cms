<?php

namespace App\Models;

use App\Enums\PromotionDecision;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A year-end decision: DECIDED on the promotion board (decided_at set), then
 * EXECUTED by the rollover (executed_at + to_* filled). One row per source
 * enrollment; re-deciding before execution updates in place. Executed rows
 * are the immutable academic-history trail.
 */
#[Fillable([
    'student_id', 'academic_year_id', 'from_enrollment_id', 'to_enrollment_id',
    'from_grade_level_id', 'to_grade_level_id', 'from_branch_id', 'to_branch_id',
    'decision', 'average', 'decided_by', 'decided_at', 'executed_at', 'notes',
])]
class StudentPromotion extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'decision' => PromotionDecision::class,
            'average' => 'decimal:2',
            'decided_at' => 'datetime',
            'executed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<StudentEnrollment, $this>
     */
    public function fromEnrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'from_enrollment_id');
    }

    /**
     * @return BelongsTo<StudentEnrollment, $this>
     */
    public function toEnrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'to_enrollment_id');
    }

    /**
     * @return BelongsTo<GradeLevel, $this>
     */
    public function toGradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class, 'to_grade_level_id');
    }
}

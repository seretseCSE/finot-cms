<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['subject_assignment_id', 'continuous_assessment_item_id', 'type', 'name', 'max_score', 'weight', 'conducted_on'])]
class Assessment extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'max_score' => 'decimal:2',
            'weight' => 'decimal:2',
            'conducted_on' => 'date',
        ];
    }

    /** @return BelongsTo<SubjectAssignment, $this> */
    public function subjectAssignment(): BelongsTo
    {
        return $this->belongsTo(SubjectAssignment::class);
    }

    /** @return HasMany<AssessmentResult, $this> */
    public function results(): HasMany
    {
        return $this->hasMany(AssessmentResult::class);
    }

    /** @return BelongsTo<ContinuousAssessmentItem, $this> */
    public function continuousAssessmentItem(): BelongsTo
    {
        return $this->belongsTo(ContinuousAssessmentItem::class);
    }

    /**
     * Whether this row materialises a grade-book template slot. Planned
     * assessments carry the principal's structure — teachers can neither
     * edit nor delete them.
     */
    public function isPlanned(): bool
    {
        return $this->continuous_assessment_item_id !== null;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One assessment slot of a grade book template. Materialised into concrete
 * `assessments` per subject assignment when a marklist is opened.
 */
#[Fillable(['continuous_assessment_id', 'type', 'name', 'weight', 'max_score', 'due_on', 'sort_order'])]
class ContinuousAssessmentItem extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
            'max_score' => 'decimal:2',
            'due_on' => 'date',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ContinuousAssessment, $this>
     */
    public function continuousAssessment(): BelongsTo
    {
        return $this->belongsTo(ContinuousAssessment::class);
    }

    /**
     * @return HasMany<Assessment, $this>
     */
    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }
}

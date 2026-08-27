<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One employee's per-term performance appraisal.
 * draft (evaluator scores) → submitted (teacher notified, record visible to
 * them) → acknowledged (teacher signed, optional comment). Scores are
 * snapshot lines in evaluation_scores; overall is out of 100.
 */
#[Fillable([
    'school_id', 'branch_id', 'employee_id', 'term_id', 'evaluation_template_id',
    'evaluator_id', 'status', 'overall_score', 'strengths', 'improvements',
    'teacher_comment', 'submitted_at', 'acknowledged_at',
])]
class TeacherEvaluation extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_ACKNOWLEDGED = 'acknowledged';

    protected function casts(): array
    {
        return [
            'overall_score' => 'decimal:2',
            'submitted_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(EvaluationTemplate::class, 'evaluation_template_id');
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(EvaluationScore::class)->orderBy('sort_order');
    }

    /** Weighted overall out of 100 over the SCORED lines (null while empty). */
    public function computeOverall(): ?float
    {
        $scored = $this->scores->filter(fn (EvaluationScore $s): bool => $s->score !== null);

        if ($scored->isEmpty()) {
            return null;
        }

        return round($scored->sum(
            fn (EvaluationScore $s): float => (float) $s->max_score > 0
                ? ((float) $s->score / (float) $s->max_score) * (float) $s->weight
                : 0,
        ), 2);
    }
}

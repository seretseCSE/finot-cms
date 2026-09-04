<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One snapshotted criterion line of an appraisal, with its score + note. */
#[Fillable([
    'teacher_evaluation_id', 'evaluation_criterion_id', 'domain', 'label',
    'weight', 'max_score', 'score', 'note', 'sort_order',
])]
class EvaluationScore extends Model
{
    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
            'max_score' => 'decimal:2',
            'score' => 'decimal:2',
        ];
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(TeacherEvaluation::class, 'teacher_evaluation_id');
    }
}

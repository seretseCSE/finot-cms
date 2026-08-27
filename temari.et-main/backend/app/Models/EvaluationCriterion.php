<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One rubric line: domain, criterion, weight share, rating ceiling. */
#[Fillable(['evaluation_template_id', 'domain', 'label', 'weight', 'max_score', 'sort_order'])]
class EvaluationCriterion extends Model
{
    protected $table = 'evaluation_criteria';

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
            'max_score' => 'decimal:2',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(EvaluationTemplate::class, 'evaluation_template_id');
    }
}

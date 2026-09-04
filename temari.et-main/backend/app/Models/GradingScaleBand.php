<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One contiguous score range of a grading scale and its display (letter +
 * label), value (grade points) and judgement (passing).
 */
#[Fillable([
    'grading_scale_id', 'min_score', 'max_score', 'letter', 'label',
    'grade_points', 'is_passing', 'sort_order',
])]
class GradingScaleBand extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_score' => 'decimal:2',
            'max_score' => 'decimal:2',
            'grade_points' => 'decimal:2',
            'is_passing' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<GradingScale, $this>
     */
    public function scale(): BelongsTo
    {
        return $this->belongsTo(GradingScale::class, 'grading_scale_id');
    }
}

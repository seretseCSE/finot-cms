<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Which grading scale applies to a grade-level window, per school (branch_id
 * null = school default) or per branch (override). `display` = what report
 * cards show: numeric | letter | both. Resolved by GradingPolicyResolver.
 */
#[Fillable([
    'school_id', 'branch_id', 'grading_scale_id', 'display',
    'min_grade_sort', 'max_grade_sort',
])]
class GradingPolicy extends Model
{
    use SoftDeletes;

    public const DISPLAYS = ['numeric', 'letter', 'both'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_grade_sort' => 'integer',
            'max_grade_sort' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<GradingScale, $this>
     */
    public function scale(): BelongsTo
    {
        return $this->belongsTo(GradingScale::class, 'grading_scale_id');
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function appliesToGradeSort(int $sortOrder): bool
    {
        return ($this->min_grade_sort === null || $sortOrder >= $this->min_grade_sort)
            && ($this->max_grade_sort === null || $sortOrder <= $this->max_grade_sort);
    }
}

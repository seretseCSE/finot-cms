<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A principal/director-defined assessment plan for a branch + term. WHERE it
 * applies is expressed by its targeting rows (grade → sections → subjects).
 * Where a grade book applies, teachers' marklists materialise its items —
 * teachers never define assessment structure themselves.
 */
#[Fillable([
    'school_id', 'branch_id', 'term_id', 'name', 'is_active', 'created_by',
])]
class ContinuousAssessment extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<ContinuousAssessmentItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ContinuousAssessmentItem::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<ContinuousAssessmentTarget, $this>
     */
    public function targets(): HasMany
    {
        return $this->hasMany(ContinuousAssessmentTarget::class);
    }

    /**
     * The most specific targeting row that covers this grade + section +
     * subject, or null when the plan does not apply. Higher specificity
     * (subject > section > grade) wins.
     */
    public function matchingTarget(int $gradeLevelId, int $sectionId, int $subjectId): ?ContinuousAssessmentTarget
    {
        return $this->targets
            ->filter(fn (ContinuousAssessmentTarget $t): bool => $t->matches($gradeLevelId, $sectionId, $subjectId))
            ->sortByDesc(fn (ContinuousAssessmentTarget $t): array => $t->specificity())
            ->first();
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

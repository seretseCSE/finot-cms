<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A mapping from raw numeric marks to displayed grades. Platform-seeded
 * defaults (school_id null) + school-custom rows. Marks are stored numeric
 * everywhere; this scale renders them (letter/label/grade points/pass) at read time
 * and is snapshotted into student_term_results when a term freezes.
 */
#[Fillable(['school_id', 'code', 'name', 'description', 'is_active', 'sort_order'])]
class GradingScale extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * @return HasMany<GradingScaleBand, $this>
     */
    public function bands(): HasMany
    {
        return $this->hasMany(GradingScaleBand::class)->orderByDesc('min_score');
    }

    /**
     * @return HasMany<GradingPolicy, $this>
     */
    public function policies(): HasMany
    {
        return $this->hasMany(GradingPolicy::class);
    }

    public function isPlatform(): bool
    {
        return $this->school_id === null;
    }

    /**
     * The band a raw score (0–100 space) falls into, or null when the score
     * is outside every band. Requires bands to be loaded.
     */
    public function bandFor(float $score): ?GradingScaleBand
    {
        return $this->bands
            ->first(fn (GradingScaleBand $band): bool => $score >= (float) $band->min_score
                && $score <= (float) $band->max_score);
    }
}

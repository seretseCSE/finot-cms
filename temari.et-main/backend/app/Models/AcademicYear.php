<?php

namespace App\Models;

use App\Enums\AcademicYearStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'school_id', 'branch_id', 'name', 'starts_on', 'ends_on', 'status', 'is_active',
])]
class AcademicYear extends Model
{
    use SoftDeletes;

    /**
     * Soft-deleting a year cascades to its structure children (terms + fees) —
     * otherwise they'd linger pointing at a trashed year, and anything walking
     * term→academicYear would hit null. Force deletes rely on the DB cascade.
     */
    protected static function booted(): void
    {
        static::deleting(function (AcademicYear $year): void {
            if ($year->isForceDeleting()) {
                return;
            }

            $year->terms()->delete();
            $year->fees()->delete();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'status' => AcademicYearStatus::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * The branch's operating year — what "current" used to mean before the
     * status lifecycle replaced the is_current flag.
     */
    public function isCurrent(): bool
    {
        return $this->status === AcademicYearStatus::Active;
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * @return HasMany<Term, $this>
     */
    public function terms(): HasMany
    {
        return $this->hasMany(Term::class);
    }

    /**
     * @return HasMany<FeeStructure, $this>
     */
    public function fees(): HasMany
    {
        return $this->hasMany(FeeStructure::class);
    }
}

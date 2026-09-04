<?php

namespace App\Models;

use App\Enums\TimetableVersionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A draft-or-published copy of a term's timetable. Slots hang off the
 * version; publishing archives the previously published version of the term.
 */
#[Fillable([
    'school_id', 'branch_id', 'term_id', 'name', 'status', 'score', 'conflicts',
    'days', 'generated_at', 'published_at', 'created_by',
])]
class TimetableVersion extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TimetableVersionStatus::class,
            'conflicts' => 'array',
            'days' => 'array',
            'generated_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function isEditable(): bool
    {
        return $this->status === TimetableVersionStatus::Draft;
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
     * @return HasMany<TimetableSlot, $this>
     */
    public function slots(): HasMany
    {
        return $this->hasMany(TimetableSlot::class);
    }
}

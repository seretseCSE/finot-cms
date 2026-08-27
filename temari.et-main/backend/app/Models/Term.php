<?php

namespace App\Models;

use App\Enums\TermStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'academic_year_id', 'school_id', 'branch_id', 'school_program_id', 'name', 'sequence',
    'starts_on', 'ends_on', 'class_starts_at', 'class_ends_at', 'period_minutes',
    'is_quarter', 'semester', 'is_current', 'status', 'is_active',
])]
class Term extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'status' => TermStatus::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
            'period_minutes' => 'integer',
            'is_quarter' => 'boolean',
            'semester' => 'integer',
            'is_current' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function isClosed(): bool
    {
        return $this->status === TermStatus::Closed;
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * The education program this term runs under (regular / night / distance …).
     *
     * @return BelongsTo<SchoolProgram, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(SchoolProgram::class, 'school_program_id');
    }

    /**
     * The period schedule, in daily order.
     *
     * @return HasMany<TermPeriod, $this>
     */
    public function periods(): HasMany
    {
        return $this->hasMany(TermPeriod::class)->orderBy('sequence');
    }

    /**
     * @return HasMany<TimetableVersion, $this>
     */
    public function timetableVersions(): HasMany
    {
        return $this->hasMany(TimetableVersion::class);
    }
}

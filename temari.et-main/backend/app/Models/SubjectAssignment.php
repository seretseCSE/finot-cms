<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'school_id', 'branch_id', 'academic_year_id', 'section_id', 'subject_id',
    'term_id', 'employee_id', 'periods_per_week', 'block_size', 'is_active',
])]
class SubjectAssignment extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return ['periods_per_week' => 'integer', 'block_size' => 'integer', 'is_active' => 'boolean'];
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<Section, $this> */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /** @return BelongsTo<Subject, $this> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /** @return BelongsTo<Term, $this> */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return HasMany<TimetableSlot, $this> */
    public function timetableSlots(): HasMany
    {
        return $this->hasMany(TimetableSlot::class);
    }

    /** @return HasMany<Assessment, $this> */
    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }

    /** @return HasOne<Marklist, $this> */
    public function marklist(): HasOne
    {
        return $this->hasOne(Marklist::class);
    }

    /**
     * Whether this assignment belongs to the given user (as its teacher).
     * Drives the `grades.manage_own` ownership lane: a teacher may only touch
     * continuous assessments of assignments that are actually theirs.
     */
    public function isOwnedBy(User $user): bool
    {
        $this->loadMissing('employee');

        return $this->employee !== null && (int) $this->employee->user_id === (int) $user->id;
    }
}

<?php

namespace App\Models;

use App\Enums\MarklistStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The sign-off workflow row for one subject assignment's continuous assessment:
 * draft (teacher edits) → submitted (teacher signed off) → approved
 * (supervisor countersigned; read-only until reopened). Created lazily the
 * first time the continuous assessment is opened.
 */
#[Fillable([
    'subject_assignment_id', 'school_id', 'branch_id', 'term_id', 'status',
    'submitted_at', 'submitted_by', 'approved_at', 'approved_by', 'remarks',
    'assisted_by', 'assisted_at', 'assist_reason',
])]
class Marklist extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MarklistStatus::class,
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'assisted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<SubjectAssignment, $this>
     */
    public function subjectAssignment(): BelongsTo
    {
        return $this->belongsTo(SubjectAssignment::class);
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * The supervisor who declared on-behalf mark entry on this draft —
     * the loud lane that replaces silent supervisor edits.
     *
     * @return BelongsTo<User, $this>
     */
    public function assister(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assisted_by');
    }

    public function isLocked(): bool
    {
        return $this->status->isLocked();
    }
}

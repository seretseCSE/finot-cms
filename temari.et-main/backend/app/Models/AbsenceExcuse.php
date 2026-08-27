<?php

namespace App\Models;

use App\Enums\AbsenceExcuseStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A guardian's explanation for a child's absence, awaiting the branch's
 * decision. Approval retro-marks the range's absent attendance records as
 * excused (AbsenceExcuseController@decide) — the row itself stays as the
 * audit trail of who asked and who decided.
 */
#[Fillable([
    'school_id', 'branch_id', 'student_id', 'requested_by',
    'starts_on', 'ends_on', 'reason', 'attachment_path',
    'status', 'decided_by', 'decided_at', 'decision_note',
])]
class AbsenceExcuse extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'status' => AbsenceExcuseStatus::class,
            'decided_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<User, $this> */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}

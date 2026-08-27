<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A mid-year withdrawal — the student leaves the school entirely or moves to
 * a school outside Temari. Freezes the reason, named destination and the
 * outstanding balance at withdrawal time, and backs the printable QR-verified
 * clearance letter. In-platform moves use student_transfer_requests instead.
 */
#[Fillable([
    'student_id', 'enrollment_id', 'school_id', 'branch_id',
    'reason', 'destination', 'withdrawn_on', 'outstanding_amount',
    'public_token', 'withdrawn_by',
])]
class StudentWithdrawal extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'withdrawn_on' => 'date',
            'outstanding_amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<StudentEnrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'enrollment_id');
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
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
    public function withdrawnBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'withdrawn_by');
    }
}

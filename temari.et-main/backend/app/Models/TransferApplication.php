<?php

namespace App\Models;

use App\Enums\TransferApplicationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A parent/student-initiated transfer application: family → destination
 * school → (on acceptance) a standard student_transfer_requests row that the
 * CURRENT school approves or rejects. See the migration for the full story.
 */
#[Fillable([
    'student_id', 'applicant_user_id', 'applicant_parent_id',
    'from_enrollment_id', 'from_school_id', 'from_branch_id',
    'to_school_id', 'to_branch_id',
    'status', 'reason', 'decline_note',
    'transfer_request_id', 'decided_by', 'decided_at',
])]
class TransferApplication extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TransferApplicationStatus::class,
            'decided_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function applicant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applicant_user_id');
    }

    /**
     * @return BelongsTo<StudentEnrollment, $this>
     */
    public function fromEnrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'from_enrollment_id');
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function fromSchool(): BelongsTo
    {
        return $this->belongsTo(School::class, 'from_school_id');
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function toSchool(): BelongsTo
    {
        return $this->belongsTo(School::class, 'to_school_id');
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    /**
     * @return BelongsTo<StudentTransferRequest, $this>
     */
    public function transferRequest(): BelongsTo
    {
        return $this->belongsTo(StudentTransferRequest::class, 'transfer_request_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}

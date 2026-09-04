<?php

namespace App\Models;

use App\Enums\TransferRequestStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An in-platform transfer: receiving branch requests, sending branch decides.
 * Approval performs the handover (see ApproveTransferAction) and links the
 * new enrollment here so the printable transfer letter can cite both sides.
 */
#[Fillable([
    'student_id', 'from_enrollment_id', 'from_school_id', 'from_branch_id',
    'to_school_id', 'to_branch_id', 'to_academic_year_id', 'to_grade_level_id',
    'to_enrollment_id', 'status', 'handover_snapshot', 'public_token', 'reason',
    'requested_by', 'decided_by', 'decided_at', 'decision_note',
])]
class StudentTransferRequest extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TransferRequestStatus::class,
            'handover_snapshot' => 'array',
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
     * @return HasMany<TransferRequestAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(TransferRequestAttachment::class, 'student_transfer_request_id');
    }

    /**
     * @return BelongsTo<StudentEnrollment, $this>
     */
    public function fromEnrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'from_enrollment_id');
    }

    /**
     * @return BelongsTo<StudentEnrollment, $this>
     */
    public function toEnrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'to_enrollment_id');
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
     * @return BelongsTo<AcademicYear, $this>
     */
    public function toAcademicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'to_academic_year_id');
    }

    /**
     * @return BelongsTo<GradeLevel, $this>
     */
    public function toGradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class, 'to_grade_level_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}

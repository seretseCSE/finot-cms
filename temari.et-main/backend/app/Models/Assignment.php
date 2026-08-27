<?php

namespace App\Models;

use App\Enums\AssignmentStatus;
use App\Support\QuestionRules;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Homework/classwork for one class (ADR-016), anchored to the
 * subject_assignment like everything a teacher owns. `kind` shapes the work:
 * standard (student turns something in), quiz (the linked quiz IS the work —
 * attempts are the submissions, one source of truth). `assessment_id` links
 * a graded assignment into the continuous-assessment gradebook.
 */
#[Fillable([
    'school_id', 'branch_id', 'subject_assignment_id', 'kind', 'quiz_id',
    'title', 'instructions', 'submission_types', 'attachments', 'rubric',
    'target_student_ids', 'max_score', 'available_from', 'due_at',
    'late_policy', 'late_penalty_percent', 'resubmission_policy', 'status',
    'published_at', 'assessment_id', 'created_by',
])]
class Assignment extends Model
{
    use SoftDeletes;

    public const KINDS = ['standard', 'quiz'];

    public const SUBMISSION_TYPES = ['text', 'file', 'photo', 'audio', 'link'];

    public const RESUBMISSION_POLICIES = ['until_graded', 'once', 'never'];

    protected $attributes = [
        'kind' => 'standard',
        'status' => 'draft',
        'resubmission_policy' => 'until_graded',
    ];

    protected function casts(): array
    {
        return [
            'submission_types' => 'array',
            'attachments' => 'array',
            'rubric' => 'array',
            'target_student_ids' => 'array',
            'max_score' => 'decimal:2',
            'available_from' => 'datetime',
            'due_at' => 'datetime',
            'late_penalty_percent' => 'decimal:2',
            'status' => AssignmentStatus::class,
            'published_at' => 'datetime',
        ];
    }

    /** Instructions with stored `<img data-path>` markers re-signed. */
    public function presentInstructions(): ?string
    {
        return $this->instructions === null
            ? null
            : QuestionRules::hydrateStemMedia($this->instructions);
    }

    /** Whether this post reaches the given student (no targets = whole class). */
    public function reachesStudent(int $studentId): bool
    {
        $ids = $this->target_student_ids;

        return $ids === null || $ids === [] || in_array($studentId, array_map('intval', $ids), true);
    }

    /**
     * Posts that reach the student: whole-class or targeted at them.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisibleToStudent(Builder $query, int $studentId): Builder
    {
        return $query->where(function (Builder $q) use ($studentId): void {
            $q->whereNull('target_student_ids')
                ->orWhereJsonContains('target_student_ids', $studentId);
        });
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->subjectAssignment !== null && $this->subjectAssignment->isOwnedBy($user);
    }

    /** @return BelongsTo<SubjectAssignment, $this> */
    public function subjectAssignment(): BelongsTo
    {
        return $this->belongsTo(SubjectAssignment::class);
    }

    /** @return BelongsTo<Quiz, $this> */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    /** @return BelongsTo<Assessment, $this> */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /** @return HasMany<AssignmentSubmission, $this> */
    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

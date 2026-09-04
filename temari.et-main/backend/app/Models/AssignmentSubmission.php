<?php

namespace App\Models;

use App\Enums\SubmissionStatus;
use App\Support\QuestionRules;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One student's turn-in. Students may resubmit until graded (row updated in
 * place); files live privately on R2 behind signed URLs.
 */
#[Fillable([
    'assignment_id', 'student_id', 'student_enrollment_id', 'body', 'files',
    'link_url', 'attempt_count', 'submitted_at', 'is_late', 'status', 'score',
    'rubric_scores', 'feedback', 'graded_by', 'graded_at',
])]
class AssignmentSubmission extends Model
{
    use SoftDeletes;

    /** The student's rich answer with stored `<img data-path>` markers re-signed. */
    public function presentBody(): ?string
    {
        return $this->body === null
            ? null
            : QuestionRules::hydrateStemMedia($this->body);
    }

    /** Teacher feedback with stored `<img data-path>` markers re-signed. */
    public function presentFeedback(): ?string
    {
        return $this->feedback === null
            ? null
            : QuestionRules::hydrateStemMedia($this->feedback);
    }

    protected function casts(): array
    {
        return [
            'files' => 'array',
            'attempt_count' => 'integer',
            'submitted_at' => 'datetime',
            'is_late' => 'boolean',
            'status' => SubmissionStatus::class,
            'score' => 'decimal:2',
            'rubric_scores' => 'array',
            'graded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Assignment, $this> */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<StudentEnrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id');
    }

    /** @return BelongsTo<User, $this> */
    public function grader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }
}

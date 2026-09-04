<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One section a quiz/exam is given to (quiz × subject_assignment). The
 * anchor class is always among the targets; extra rows are the sibling
 * sections sitting the same paper.
 */
#[Fillable(['quiz_id', 'subject_assignment_id'])]
class QuizTarget extends Model
{
    /** @return BelongsTo<Quiz, $this> */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    /** @return BelongsTo<SubjectAssignment, $this> */
    public function subjectAssignment(): BelongsTo
    {
        return $this->belongsTo(SubjectAssignment::class);
    }
}

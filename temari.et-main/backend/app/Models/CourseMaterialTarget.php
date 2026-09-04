<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Class targeting for teacher-posted materials. */
#[Fillable(['course_material_id', 'subject_assignment_id'])]
class CourseMaterialTarget extends Model
{
    /** @return BelongsTo<CourseMaterial, $this> */
    public function material(): BelongsTo
    {
        return $this->belongsTo(CourseMaterial::class, 'course_material_id');
    }

    /** @return BelongsTo<SubjectAssignment, $this> */
    public function subjectAssignment(): BelongsTo
    {
        return $this->belongsTo(SubjectAssignment::class);
    }
}

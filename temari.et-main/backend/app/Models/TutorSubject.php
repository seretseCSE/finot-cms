<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One subject a tutor teaches, with its explicit grade set (grade
 * sort_orders; empty/null = every grade the subject applies to).
 */
#[Fillable(['tutor_profile_id', 'subject_id', 'grade_sorts'])]
class TutorSubject extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['grade_sorts' => 'array'];
    }

    /**
     * @return BelongsTo<TutorProfile, $this>
     */
    public function tutorProfile(): BelongsTo
    {
        return $this->belongsTo(TutorProfile::class);
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}

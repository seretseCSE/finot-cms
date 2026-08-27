<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One targeting row of a grade book: a grade (null = all grades) optionally
 * narrowed to a set of that grade's sections and/or subjects. A null/empty
 * id-array means "all" on that axis.
 */
#[Fillable(['continuous_assessment_id', 'grade_level_id', 'section_ids', 'subject_ids'])]
class ContinuousAssessmentTarget extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'section_ids' => 'array',
            'subject_ids' => 'array',
        ];
    }

    /**
     * @return BelongsTo<ContinuousAssessment, $this>
     */
    public function continuousAssessment(): BelongsTo
    {
        return $this->belongsTo(ContinuousAssessment::class);
    }

    /**
     * @return BelongsTo<GradeLevel, $this>
     */
    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    /** Whether this row covers the given grade + section + subject. */
    public function matches(int $gradeLevelId, int $sectionId, int $subjectId): bool
    {
        if ($this->grade_level_id !== null && (int) $this->grade_level_id !== $gradeLevelId) {
            return false;
        }

        if ($this->hasSections() && ! in_array($sectionId, $this->sectionIds(), true)) {
            return false;
        }

        if ($this->hasSubjects() && ! in_array($subjectId, $this->subjectIds(), true)) {
            return false;
        }

        return true;
    }

    /**
     * How narrow this row is, as a [subject, section, grade] specificity tuple
     * — higher wins when several plans govern the same assignment (subject
     * beats section beats grade), mirroring the old precedence.
     *
     * @return array{0: int, 1: int, 2: int}
     */
    public function specificity(): array
    {
        return [
            $this->hasSubjects() ? 1 : 0,
            $this->hasSections() ? 1 : 0,
            $this->grade_level_id !== null ? 1 : 0,
        ];
    }

    public function hasSections(): bool
    {
        return ! empty($this->section_ids);
    }

    public function hasSubjects(): bool
    {
        return ! empty($this->subject_ids);
    }

    /** @return list<int> */
    public function sectionIds(): array
    {
        return array_map('intval', $this->section_ids ?? []);
    }

    /** @return list<int> */
    public function subjectIds(): array
    {
        return array_map('intval', $this->subject_ids ?? []);
    }
}

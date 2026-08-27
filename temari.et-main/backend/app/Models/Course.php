<?php

namespace App\Models;

use App\Support\QuestionRules;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A structured course: modules → lessons → per-user progress. One engine,
 * three audiences — platform (school_id null), school (grade-windowed),
 * class (subject_assignment_id). See the courses migration for the map.
 */
#[Fillable([
    'school_id', 'branch_id', 'subject_assignment_id', 'subject_id',
    'min_grade_sort', 'max_grade_sort', 'stream', 'title', 'description',
    'language', 'cover_path', 'is_sequential', 'status', 'published_at', 'created_by',
])]
class Course extends Model
{
    use SoftDeletes;

    public const STATUSES = ['draft', 'published', 'archived'];

    public const STREAMS = ['natural', 'social'];

    protected $attributes = [
        'status' => 'draft',
        'language' => 'en',
    ];

    protected function casts(): array
    {
        return [
            'min_grade_sort' => 'integer',
            'max_grade_sort' => 'integer',
            'is_sequential' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function isPlatform(): bool
    {
        return $this->school_id === null;
    }

    /** Description (rich) with stored `<img data-path>` markers re-signed. */
    public function presentDescription(): ?string
    {
        return $this->description === null
            ? null
            : QuestionRules::hydrateStemMedia($this->description);
    }

    /** Owned when the teacher teaches ANY targeted class (anchor included). */
    public function isOwnedBy(User $user): bool
    {
        if ($this->subjectAssignment !== null && $this->subjectAssignment->isOwnedBy($user)) {
            return true;
        }

        return $this->targetAssignments()
            ->whereHas('employee', fn ($q) => $q->where('user_id', $user->id))
            ->exists();
    }

    /** @return HasMany<CourseModule, $this> */
    public function modules(): HasMany
    {
        return $this->hasMany(CourseModule::class)->orderBy('sort_order');
    }

    /** @return HasMany<CourseLesson, $this> */
    public function lessons(): HasMany
    {
        return $this->hasMany(CourseLesson::class);
    }

    /** @return BelongsTo<SubjectAssignment, $this> */
    public function subjectAssignment(): BelongsTo
    {
        return $this->belongsTo(SubjectAssignment::class);
    }

    /** @return HasMany<CourseTarget, $this> */
    public function targets(): HasMany
    {
        return $this->hasMany(CourseTarget::class);
    }

    /** @return BelongsToMany<SubjectAssignment, $this> */
    public function targetAssignments(): BelongsToMany
    {
        return $this->belongsToMany(SubjectAssignment::class, 'course_targets');
    }

    /** @return BelongsTo<Subject, $this> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

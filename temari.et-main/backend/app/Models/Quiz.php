<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use App\Enums\QuizAttemptStatus;
use App\Enums\QuizStatus;
use App\Support\QuestionRules;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A quiz / exam / mock (one table, `kind` disambiguates — ADR-016). Class
 * quizzes anchor to a subject_assignment; platform mocks (`is_platform`)
 * target a subject + grade level and are open to any registered user.
 */
#[Fillable([
    'school_id', 'branch_id', 'subject_assignment_id', 'is_platform', 'kind',
    'title', 'instructions', 'subject_id', 'grade_level_id',
    'exam_kind', 'exam_year_ec', 'stream', 'language',
    'total_points', 'settings', 'draw', 'parts', 'status', 'assessment_id', 'created_by',
])]
class Quiz extends Model
{
    use SoftDeletes;

    public const KINDS = ['quiz', 'exam', 'mock'];

    /** Prep-paper identity (platform lane only). */
    public const EXAM_KINDS = ['national_past', 'mock', 'practice'];

    public const STREAMS = ['natural', 'social'];

    protected $attributes = [
        'status' => 'draft',
    ];

    protected function casts(): array
    {
        return [
            'is_platform' => 'boolean',
            'exam_year_ec' => 'integer',
            'total_points' => 'decimal:2',
            'settings' => 'array',
            'draw' => 'array',
            'parts' => 'array',
            'status' => QuizStatus::class,
            'published_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /** A settings knob with its default. */
    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    public function requiresAccessCode(): bool
    {
        return $this->access_code_hash !== null;
    }

    /** Whether the availability window admits new attempts right now. */
    public function windowOpen(): bool
    {
        $opensAt = $this->setting('opens_at');
        $closesAt = $this->setting('closes_at');

        return ($opensAt === null || Carbon::parse($opensAt)->isPast())
            && ($closesAt === null || Carbon::parse($closesAt)->isFuture());
    }

    /** Owned when the teacher teaches ANY targeted section (anchor included). */
    public function isOwnedBy(User $user): bool
    {
        if ($this->subjectAssignment !== null && $this->subjectAssignment->isOwnedBy($user)) {
            return true;
        }

        return $this->targetAssignments()
            ->whereHas('employee', fn ($q) => $q->where('user_id', $user->id))
            ->exists();
    }

    /** Instructions with stored `<img data-path>` markers re-signed. */
    public function presentInstructions(): ?string
    {
        return $this->instructions === null
            ? null
            : QuestionRules::hydrateStemMedia($this->instructions);
    }

    /**
     * Paper parts with their instructions' stored `<img data-path>` markers
     * re-signed — the read-time twin of presentInstructions().
     *
     * @return list<array{title: string, instructions: ?string}>|null
     */
    public function presentParts(): ?array
    {
        if (! is_array($this->parts) || $this->parts === []) {
            return null;
        }

        return array_values(array_map(fn (array $part): array => [
            'title' => (string) ($part['title'] ?? ''),
            'instructions' => isset($part['instructions']) && $part['instructions'] !== null
                ? QuestionRules::hydrateStemMedia((string) $part['instructions'])
                : null,
        ], $this->parts));
    }

    /** @return BelongsTo<SubjectAssignment, $this> */
    public function subjectAssignment(): BelongsTo
    {
        return $this->belongsTo(SubjectAssignment::class);
    }

    /** @return HasMany<QuizTarget, $this> */
    public function targets(): HasMany
    {
        return $this->hasMany(QuizTarget::class);
    }

    /** @return BelongsToMany<SubjectAssignment, $this> */
    public function targetAssignments(): BelongsToMany
    {
        return $this->belongsToMany(SubjectAssignment::class, 'quiz_targets');
    }

    /**
     * Attach `expected_takers` (active enrollments across every target
     * section) and `takers_count` (distinct students with a counting
     * attempt) — the completion-rate pair on lists and detail.
     */
    public function scopeWithTakerStats(Builder $query): Builder
    {
        if ($query->getQuery()->columns === null) {
            $query->select('quizzes.*');
        }

        return $query
            ->selectSub(
                "select count(distinct se.student_id)
                 from quiz_targets qt
                 join subject_assignments sa on sa.id = qt.subject_assignment_id
                 join student_enrollments se on se.section_id = sa.section_id
                 where qt.quiz_id = quizzes.id
                   and se.status = '".EnrollmentStatus::Active->value."'
                   and se.deleted_at is null",
                'expected_takers',
            )
            ->selectSub(
                "select count(distinct qa.user_id)
                 from quiz_attempts qa
                 where qa.quiz_id = quizzes.id
                   and qa.status != '".QuizAttemptStatus::Invalidated->value."'",
                'takers_count',
            );
    }

    /** @return BelongsTo<School, $this> */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /** @return BelongsTo<Subject, $this> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /** @return BelongsTo<GradeLevel, $this> */
    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    /** @return BelongsTo<Assessment, $this> */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /** @return HasMany<QuizQuestion, $this> */
    public function quizQuestions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('sort_order');
    }

    /** @return HasMany<QuizAttempt, $this> */
    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

<?php

namespace App\Filament\Pages\Student;

use App\Models\AcademicYear;
use App\Models\AssessmentScore;
use App\Models\Batch;
use App\Models\MarklistItem;
use App\Models\StudentEnrollment;
use App\Models\StudentTermResult;
use App\Models\Subject;
use App\Models\Term;
use App\Services\Academics\RankingService;
use App\Support\RoleGate;
use Filament\Pages\Page;

class MyResults extends Page
{
    protected static ?string $title = 'My Results';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.student.my-results';

    public ?int $academic_year_id = null;

    public ?int $term_id = null;

    public ?int $batch_id = null;

    public ?int $subject_id = null;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-academic-cap';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'My Learning';
    }

    public static function getNavigationLabel(): string
    {
        return 'My Results';
    }

    public static function canAccess(array $parameters = []): bool
    {
        return (RoleGate::is('student') && RoleGate::can('results.view_own'))
            || (RoleGate::is('parent') && RoleGate::can('results.view_linked'));
    }

    public function mount(): void
    {
        $memberId = $this->memberId();
        if (! $memberId) {
            return;
        }

        $enrollment = StudentEnrollment::query()
            ->with('batchYear')
            ->where('member_id', $memberId)
            ->orderByRaw("case when status = 'Enrolled' and removed_at is null then 0 else 1 end")
            ->latest('id')
            ->first();

        $this->academic_year_id = $enrollment?->academic_year_id;
        $this->batch_id = $enrollment?->batch_id;

        $activeTerm = Term::query()
            ->where(function ($q) {
                $q->where('is_active', true)->orWhere('status', 'active');
            })
            ->when($enrollment?->batch_year_id, fn ($q, $id) => $q->where('batch_year_id', $id))
            ->when($this->academic_year_id, fn ($q, $id) => $q->where('academic_year_id', $id))
            ->latest('id')
            ->first();

        $this->term_id = $activeTerm?->id;
    }

    public function updatedAcademicYearId(mixed $value): void
    {
        $this->academic_year_id = $this->nullableId($value);
        $this->term_id = null;
        $this->subject_id = null;
    }

    public function updatedTermId(mixed $value): void
    {
        $this->term_id = $this->nullableId($value);
        $this->subject_id = null;
    }

    public function updatedBatchId(mixed $value): void
    {
        $this->batch_id = $this->nullableId($value);
        $this->term_id = null;
        $this->subject_id = null;
    }

    public function updatedSubjectId(mixed $value): void
    {
        $this->subject_id = $this->nullableId($value);
    }

    public function clearFilters(): void
    {
        $this->academic_year_id = null;
        $this->term_id = null;
        $this->batch_id = null;
        $this->subject_id = null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function filterOptions(): array
    {
        $memberId = $this->memberId();
        if (! $memberId) {
            return ['academic_years' => [], 'terms' => [], 'batches' => [], 'subjects' => []];
        }

        $enrollments = StudentEnrollment::query()
            ->where('member_id', $memberId)
            ->get(['academic_year_id', 'batch_id', 'batch_year_id']);

        $yearIds = $enrollments->pluck('academic_year_id')->filter()->map(fn ($id) => (int) $id);
        $batchIds = $enrollments->pluck('batch_id')->filter()->map(fn ($id) => (int) $id);

        $marklistTerms = MarklistItem::query()
            ->where('member_id', $memberId)
            ->whereHas('marklist')
            ->with('marklist.term.batchYear')
            ->get()
            ->pluck('marklist.term')
            ->filter();

        $scoreTermIds = AssessmentScore::query()
            ->where('member_id', $memberId)
            ->whereHas('assessment.offering.term')
            ->with('assessment.offering.term.batchYear')
            ->get()
            ->pluck('assessment.offering.term')
            ->filter();

        $terms = $marklistTerms->concat($scoreTermIds)->unique('id')->values();
        $yearIds = $yearIds->concat($terms->pluck('academic_year_id'))->filter()->unique()->values();
        $batchIds = $batchIds
            ->concat($terms->pluck('batchYear.batch_id'))
            ->filter()
            ->unique()
            ->values();

        $visibleTerms = $terms->when($this->academic_year_id, fn ($c) => $c->where('academic_year_id', $this->academic_year_id))
            ->when($this->batch_id, fn ($c) => $c->filter(fn ($term) => (int) ($term->batchYear?->batch_id) === (int) $this->batch_id));

        $subjectIds = MarklistItem::query()
            ->where('member_id', $memberId)
            ->whereHas('marklist', function ($query) {
                if ($this->term_id) {
                    $query->where('term_id', $this->term_id);
                }
                if ($this->academic_year_id) {
                    $query->whereHas('term', fn ($term) => $term->where('academic_year_id', $this->academic_year_id));
                }
            })
            ->with('marklist')
            ->get()
            ->pluck('marklist.subject_id');

        $offeringSubjectIds = AssessmentScore::query()
            ->where('member_id', $memberId)
            ->whereHas('assessment.offering', function ($query) {
                if ($this->term_id) {
                    $query->where('term_id', $this->term_id);
                }
                if ($this->academic_year_id) {
                    $query->whereHas('term', fn ($term) => $term->where('academic_year_id', $this->academic_year_id));
                }
                if ($this->batch_id) {
                    $query->whereHas('batchYear', fn ($year) => $year->where('batch_id', $this->batch_id));
                }
            })
            ->with('assessment.offering')
            ->get()
            ->pluck('assessment.offering.subject_id');

        return [
            'academic_years' => AcademicYear::query()
                ->whereIn('id', $yearIds->all() ?: [0])
                ->orderByDesc('id')
                ->pluck('name', 'id')
                ->all(),
            'terms' => $visibleTerms
                ->sortByDesc('id')
                ->mapWithKeys(fn ($term) => [$term->id => $term->name])
                ->all(),
            'batches' => Batch::query()
                ->whereIn('id', $batchIds->all() ?: [0])
                ->orderByDesc('id')
                ->pluck('name', 'id')
                ->all(),
            'subjects' => Subject::query()
                ->whereIn('id', $subjectIds->concat($offeringSubjectIds)->filter()->unique()->all() ?: [0])
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all(),
        ];
    }

    /**
     * @return array{
     *     items: list<array<string, mixed>>,
     *     semester_average: float|null,
     *     year_average: float|null,
     *     overall_rank: int|null,
     *     cohort_size: int,
     *     term_results: list<array<string, mixed>>
     * }
     */
    public function summary(): array
    {
        $memberId = $this->memberId();

        if (! $memberId) {
            return [
                'items' => [],
                'semester_average' => null,
                'year_average' => null,
                'overall_rank' => null,
                'cohort_size' => 0,
                'term_results' => [],
            ];
        }

        $summary = app(RankingService::class)->studentResults((int) $memberId, [
            'academic_year_id' => $this->academic_year_id,
            'term_id' => $this->term_id,
            'batch_id' => $this->batch_id,
            'subject_id' => $this->subject_id,
        ]);

        $termResults = StudentTermResult::query()
            ->with(['term', 'batchYear.batch'])
            ->where('member_id', $memberId)
            ->when($this->term_id, fn ($q) => $q->where('term_id', $this->term_id))
            ->when($this->academic_year_id, fn ($q) => $q->whereHas('term', fn ($term) => $term->where('academic_year_id', $this->academic_year_id)))
            ->when($this->batch_id, fn ($q) => $q->whereHas('batchYear', fn ($year) => $year->where('batch_id', $this->batch_id)))
            ->latest('computed_at')
            ->limit(8)
            ->get()
            ->map(fn ($r) => [
                'term' => $r->term?->name,
                'batch' => $r->batchYear?->batch?->name,
                'average' => $r->average,
                'rank' => $r->rank,
                'rank_of' => $r->rank_of,
                'breakdown' => $r->breakdown ?? [],
                'computed_at' => $r->computed_at?->toDateTimeString(),
            ])->all();

        $summary['term_results'] = $termResults;

        return $summary;
    }

    protected function memberId(): ?int
    {
        $user = RoleGate::user();
        if ($user?->member_id) {
            return (int) $user->member_id;
        }

        if (RoleGate::is('parent') && $user?->parent_id) {
            $child = app(\App\Services\Learning\LearningAccess::class)->childrenForParent($user)->first();

            return $child?->id ? (int) $child->id : null;
        }

        return null;
    }

    protected function nullableId(mixed $value): ?int
    {
        if ($value === null || $value === '' || $value === '0') {
            return null;
        }

        return (int) $value;
    }
}

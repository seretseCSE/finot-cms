<?php

namespace App\Services\Documents\Types;

use App\Models\GeneratedDocument;
use App\Models\School;
use App\Models\StudentTermResult;
use App\Models\Term;
use App\Models\User;
use App\Services\Documents\DocumentType;
use App\Services\Pdf\InlineImage;
use App\Services\Reports\StudentReportService;
use App\Services\Reports\SubjectRankResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * A selection's semester report cards as ONE official PDF — the same card
 * the single-student print produces, one per page (or two per page when the
 * school's compact report_card_per_page setting says so). Bulk issuing a
 * class's cards at term end is the normal case; like the transcript batch it
 * must be the identical official document, never a browser print.
 *
 * No model subject: anchored by params (term + student ids from the roster).
 * Authorization mirrors the roster's dual lane — the term's scope decides,
 * supervisory grades.view prints anything in scope, a homeroom teacher only
 * students whose frozen row sits in their OWN homeroom sections.
 *
 * NOT publicly downloadable: one card's QR must never hand out the whole
 * class's marks. The QR proves origin through the verify lane only.
 */
class ReportCardBatchDocument extends DocumentType
{
    /** Same cap as the transcript batch — one render, bounded budget. */
    public const MAX_STUDENTS = 60;

    public function __construct(private readonly StudentReportService $reports)
    {
    }

    public function view(): string
    {
        return 'report-card-batch';
    }

    public function rules(): array
    {
        return [
            'term_id' => ['required', 'integer', 'exists:terms,id'],
            'student_ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_STUDENTS],
            'student_ids.*' => ['integer'],
        ];
    }

    public function resolveSubject(?int $subjectId): ?Model
    {
        return null;
    }

    public function authorize(User $user, ?Model $subject, array $params): bool
    {
        $term = Term::query()->find($params['term_id'] ?? null);

        if ($term === null) {
            return false;
        }

        $ids = $this->studentIds($params);
        $results = $this->results($term, $ids);

        // Every requested student must have a frozen row in this term — a
        // foreign id (another school's student) fails the whole batch.
        if ($results->pluck('student_id')->unique()->count() !== $ids->count()) {
            return false;
        }

        if ($user->hasPermissionForScope('grades.view', $term->school_id, $term->branch_id)) {
            return true;
        }

        if (! $user->hasPermissionForScope('grades.manage_own', $term->school_id, $term->branch_id)) {
            return false;
        }

        $allowed = $user->homeroomSectionIds($term->academic_year_id);

        return $results->every(
            fn (StudentTermResult $r) => $r->section_id !== null
                && in_array((int) $r->section_id, $allowed, true),
        );
    }

    public function anchor(?Model $subject, array $params): array
    {
        $term = Term::query()->find($params['term_id'] ?? null);

        return [
            'school_id' => $term?->school_id,
            'branch_id' => $term?->branch_id,
        ];
    }

    public function payload(?Model $subject, array $params): array
    {
        $term = Term::query()->with('branch')->findOrFail($params['term_id']);
        $ids = $this->studentIds($params);

        $cards = $this->reports->reportCards($ids->all(), $term->id);

        $showRanks = $term->branch?->effectiveReportCardSubjectRanks() ?? false;

        // Rows frozen before the subject-rank release carry no ranks —
        // backfill them read-time from the frozen section cohorts so the
        // setting works on history without a recompute.
        if ($showRanks) {
            $cards = app(SubjectRankResolver::class)->fill($cards, $term->id);
        }

        $school = School::query()->find($term->school_id);

        return [
            'cards' => $cards,
            // Inline ONCE as a shared CSS background — never a data URI
            // repeated on 60 pages (PdfRenderer budget).
            'logo' => InlineImage::fromStorage($school?->logo_path),
            'per_page' => $term->branch?->effectiveReportCardPerPage() ?? 1,
            'show_subject_ranks' => $showRanks,
            'label' => $this->coverageLabel($term, $ids),
            'count' => count($cards),
        ];
    }

    /** v4: larger QR for easier scanning. */
    public function templateVersion(): int
    {
        return 4;
    }

    /** Origin proof only — never the class's marks. */
    public function verifySummary(GeneratedDocument $document): array
    {
        $params = $document->params ?? [];
        $term = Term::query()->with('academicYear:id,name')->find($params['term_id'] ?? null);

        return array_filter([
            'school' => $document->school?->name,
            'branch' => $document->branch?->name,
            'coverage' => $term !== null ? $this->coverageLabel($term, $this->studentIds($params)) : null,
            'students' => (string) $this->studentIds($params)->count(),
            'issued_on' => $document->created_at?->toDateString(),
        ], fn ($v) => $v !== null && $v !== '');
    }

    /** "Grade 9 A · Semester 2 · 2018 E.C." — derived from the frozen rows. */
    private function coverageLabel(Term $term, Collection $ids): string
    {
        $results = $this->results($term, $ids)
            ->load(['section:id,name,grade_level_id', 'section.gradeLevel:id,name,sort_order']);

        $grades = $results
            ->sortBy(fn (StudentTermResult $r) => $r->section?->gradeLevel?->sort_order ?? 0)
            ->map(fn (StudentTermResult $r) => $r->section?->gradeLevel?->name)
            ->filter()->unique()->values();

        $sections = $results->map(fn (StudentTermResult $r) => $r->section?->name)
            ->filter()->unique()->values();

        $scope = $grades->implode(', ');

        if ($grades->count() === 1 && $sections->count() === 1) {
            $scope .= ' '.$sections->first();
        }

        $term->loadMissing('academicYear:id,name');

        return trim($scope.' · '.$term->name.' · '.($term->academicYear?->name ?? ''), ' ·');
    }

    /** @return Collection<int, int> */
    private function studentIds(array $params): Collection
    {
        return collect($params['student_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    /**
     * @param  Collection<int, int>  $ids
     * @return \Illuminate\Database\Eloquent\Collection<int, StudentTermResult>
     */
    private function results(Term $term, Collection $ids)
    {
        return StudentTermResult::query()
            ->where('term_id', $term->id)
            ->whereIn('student_id', $ids)
            ->get(['id', 'student_id', 'section_id']);
    }
}

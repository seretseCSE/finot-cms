<?php

namespace App\Services\Documents\Types;

use App\Models\AcademicYear;
use App\Models\GeneratedDocument;
use App\Models\School;
use App\Models\StudentTermResult;
use App\Models\User;
use App\Services\Documents\DocumentType;
use App\Services\Pdf\InlineImage;
use App\Services\Reports\YearReportCardService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * A selection's yearly report cards (one chosen side — inside or cover) as
 * ONE official PDF, one student per A4 landscape sheet. Same dual-lane
 * authorization as the semester batch, anchored on the YEAR's scope: every
 * requested student must have at least one frozen row in the year, and a
 * homeroom teacher may only batch students whose rows sit in their own
 * homeroom sections.
 *
 * NOT publicly downloadable — the QR proves origin through the verify lane.
 */
class YearReportCardBatchDocument extends DocumentType
{
    public const MAX_STUDENTS = 60;

    public function __construct(private readonly YearReportCardService $cards)
    {
    }

    public function view(): string
    {
        return 'year-report-card';
    }

    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'side' => ['required', 'string', 'in:inside,cover,both'],
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
        $year = AcademicYear::query()->find($params['academic_year_id'] ?? null);

        if ($year === null) {
            return false;
        }

        $ids = $this->studentIds($params);
        $results = $this->results($year, $ids);

        if ($results->pluck('student_id')->unique()->count() !== $ids->count()) {
            return false;
        }

        if ($user->hasPermissionForScope('grades.view', $year->school_id, $year->branch_id)) {
            return true;
        }

        if (! $user->hasPermissionForScope('grades.manage_own', $year->school_id, $year->branch_id)) {
            return false;
        }

        $allowed = $user->homeroomSectionIds($year->id);

        return $results->every(
            fn (StudentTermResult $r) => $r->section_id !== null
                && in_array((int) $r->section_id, $allowed, true),
        );
    }

    public function anchor(?Model $subject, array $params): array
    {
        $year = AcademicYear::query()->find($params['academic_year_id'] ?? null);

        return [
            'school_id' => $year?->school_id,
            'branch_id' => $year?->branch_id,
        ];
    }

    public function payload(?Model $subject, array $params): array
    {
        $year = AcademicYear::query()->with('branch')->findOrFail($params['academic_year_id']);
        $ids = $this->studentIds($params);

        $built = $this->cards->cards($year, $ids->all());

        $school = School::query()->find($year->school_id);

        return [
            'side' => $params['side'] ?? 'inside',
            'terms' => $built['terms'],
            'cards' => $built['cards'],
            'skills' => $built['skills'],
            'masthead' => $this->cards->masthead($year),
            'logo' => InlineImage::fromStorage($school?->logo_path),
            'show_grading_criteria' => $year->branch?->effectiveReportCardGradingCriteria() ?? false,
            'grading_criteria' => ($year->branch?->effectiveReportCardGradingCriteria() ?? false)
                ? $this->cards->gradingCriteria($built['cards'])
                : [],
            'count' => count($built['cards']),
        ];
    }

    public function landscape(): bool
    {
        return true;
    }

    /** v3: larger QR for easier scanning. */
    public function templateVersion(): int
    {
        return 3;
    }

    public function verifySummary(GeneratedDocument $document): array
    {
        $params = $document->params ?? [];
        $year = AcademicYear::query()->find($params['academic_year_id'] ?? null);

        return array_filter([
            'school' => $document->school?->name,
            'branch' => $document->branch?->name,
            'coverage' => $year?->name,
            'students' => (string) $this->studentIds($params)->count(),
            'issued_on' => $document->created_at?->toDateString(),
        ], fn ($v) => $v !== null && $v !== '');
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
     * One representative frozen row per student in the year (their latest),
     * for the authorization checks.
     *
     * @param  Collection<int, int>  $ids
     * @return \Illuminate\Database\Eloquent\Collection<int, StudentTermResult>
     */
    private function results(AcademicYear $year, Collection $ids)
    {
        return StudentTermResult::query()
            ->where('academic_year_id', $year->id)
            ->whereIn('student_id', $ids)
            ->get(['id', 'student_id', 'section_id']);
    }
}

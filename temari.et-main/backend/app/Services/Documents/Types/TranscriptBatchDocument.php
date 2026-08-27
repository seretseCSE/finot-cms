<?php

namespace App\Services\Documents\Types;

use App\Enums\EnrollmentStatus;
use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\GeneratedDocument;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\Documents\DocumentType;
use App\Services\Pdf\InlineImage;
use App\Services\Reports\StudentReportService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * A whole class's transcripts as ONE official PDF — the same sheet the
 * single-student transcript prints, one per page. Bulk issuing is the normal
 * case (a section at a time at year end), and it must produce the official
 * document like every other print in the platform, never a browser print of
 * the web page.
 *
 * No model subject: the batch is anchored by params (academic year + the
 * student ids from the transcripts register). Authorization mirrors
 * TranscriptController::batch exactly — the anchor YEAR's school/branch
 * decides, every student must hold an ACTIVE enrollment in it, and a homeroom
 * teacher may only batch their OWN sections.
 *
 * NOT publicly downloadable: one sheet's QR must never hand whoever holds it
 * a PDF of the whole class's marks. Its QR proves authenticity through the
 * verify lane (school, coverage, date) — nothing more.
 */
class TranscriptBatchDocument extends DocumentType
{
    /** Same cap as the register's batch endpoint — one render, one page each. */
    public const MAX_STUDENTS = 60;

    public function __construct(private readonly StudentReportService $reports) {}

    public function view(): string
    {
        return 'transcript-batch';
    }

    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'student_ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_STUDENTS],
            'student_ids.*' => ['integer'],
            // Optional year narrowing (partial transcripts) for the whole batch.
            'academic_year_ids' => ['sometimes', 'array', 'min:1'],
            'academic_year_ids.*' => ['integer'],
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
        $enrollments = $this->enrollments($year, $ids);

        // A student id from another school has no enrollment row in this year,
        // so one foreign id fails the whole batch (register precedent).
        if ($enrollments->pluck('student_id')->unique()->count() !== $ids->count()) {
            return false;
        }

        if ($user->hasPermissionForScope('grades.view', $year->school_id, $year->branch_id)) {
            return true;
        }

        if (! $user->hasPermissionForScope('grades.manage_own', $year->school_id, $year->branch_id)) {
            return false;
        }

        // Homeroom lane: every requested student must sit in the caller's own
        // homeroom — never another class's records.
        $allowed = $user->homeroomSectionIds($year->id);

        return $enrollments->every(
            fn (StudentEnrollment $e) => $e->section_id !== null
                && in_array((int) $e->section_id, $allowed, true),
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
        $year = AcademicYear::query()->findOrFail($params['academic_year_id']);
        $ids = $this->studentIds($params);

        // Preserve the register's sheet order.
        $students = Student::query()
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn (Student $student) => $ids->search($student->id))
            ->values();

        $transcripts = $this->reports->transcripts(
            $students,
            isset($params['academic_year_ids'])
                ? array_map('intval', $params['academic_year_ids'])
                : null,
        );

        // The PDF HTML must be SELF-CONTAINED (PdfRenderer contract): the
        // remote renderer never fetches signed URLs, so every remote image
        // has to go. The school logo travels inline ONCE (a CSS background
        // shared by every sheet) instead of a data URI repeated on 60 pages;
        // student photos are dropped for the same budget reason — each sheet
        // keeps the 4×4 cm frame for a physical photo.
        foreach ($transcripts as $i => $transcript) {
            $transcripts[$i]['student']['photo_url'] = null;

            if (($transcript['issued_by'] ?? null) !== null) {
                $transcripts[$i]['issued_by']['logo_url'] = null;
            }
        }

        $school = School::query()->find($year->school_id);

        return [
            'transcripts' => $transcripts,
            'logo' => InlineImage::fromStorage($school?->logo_path),
            'label' => $this->coverageLabel($year, $ids),
            'count' => count($transcripts),
        ];
    }

    /** The wide Ethiopian year grid prints landscape, like the single sheet. */
    public function landscape(): bool
    {
        return true;
    }

    /** First release of the batch sheet. */
    public function templateVersion(): int
    {
        return 1;
    }

    /**
     * The QR proves the sheet's ORIGIN, nothing more: a class batch may never
     * be downloadable by whoever holds one student's page.
     */
    public function verifySummary(GeneratedDocument $document): array
    {
        $params = $document->params ?? [];
        $year = AcademicYear::query()->find($params['academic_year_id'] ?? null);

        return array_filter([
            'school' => $document->school?->name,
            'branch' => $document->branch?->name,
            'coverage' => $year !== null
                ? $this->coverageLabel($year, $this->studentIds($params))
                : null,
            'students' => (string) $this->studentIds($params)->count(),
            'issued_on' => $document->created_at?->toDateString(),
        ], fn ($v) => $v !== null && $v !== '');
    }

    /**
     * "Grade 9 A · 2018 E.C." — what this stack of sheets covers, derived
     * from the enrollments themselves (never from client-supplied labels).
     */
    private function coverageLabel(AcademicYear $year, Collection $ids): string
    {
        $enrollments = $this->enrollments($year, $ids)
            ->load(['gradeLevel:id,name,sort_order', 'section:id,name']);

        $grades = $enrollments
            ->sortBy(fn (StudentEnrollment $e) => $e->gradeLevel?->sort_order ?? 0)
            ->map(fn (StudentEnrollment $e) => $e->gradeLevel?->name)
            ->filter()->unique()->values();

        $sections = $enrollments->map(fn (StudentEnrollment $e) => $e->section?->name)
            ->filter()->unique()->values();

        $scope = $grades->implode(', ');

        // One grade in one section reads as a class ("Grade 9 A"); anything
        // wider stays at grade level.
        if ($grades->count() === 1 && $sections->count() === 1) {
            $scope .= ' '.$sections->first();
        }

        return trim($scope.' · '.$year->name, ' ·');
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
     * @return \Illuminate\Database\Eloquent\Collection<int, StudentEnrollment>
     */
    private function enrollments(AcademicYear $year, Collection $ids)
    {
        return StudentEnrollment::query()
            ->where('academic_year_id', $year->id)
            ->where('status', EnrollmentStatus::Active->value)
            ->whereIn('student_id', $ids)
            ->get(['id', 'student_id', 'section_id', 'grade_level_id']);
    }
}

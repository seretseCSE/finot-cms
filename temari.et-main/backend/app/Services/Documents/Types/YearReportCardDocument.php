<?php

namespace App\Services\Documents\Types;

use App\Models\AcademicYear;
use App\Models\GeneratedDocument;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\Documents\DocumentType;
use App\Services\Pdf\InlineImage;
use App\Services\Reports\YearReportCardService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * The yearly report card, printed as a duplex booklet in two sides:
 *   · side=inside — the marks grid (subject × term with year averages,
 *     per-term totals/averages/ranks, absences, conduct) plus the school's
 *     behavioral skill panel when one is configured;
 *   · side=cover — the outer sheet: school masthead + student identity +
 *     the per-term remarks/signature page.
 * One student per A4 landscape sheet, both sides. Staff lane or the family
 * itself may print, exactly like the semester card.
 */
class YearReportCardDocument extends DocumentType
{
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
        ];
    }

    public function resolveSubject(?int $subjectId): ?Model
    {
        return Student::find($subjectId);
    }

    public function authorize(User $user, ?Model $subject, array $params): bool
    {
        return $subject instanceof Student
            && (Gate::forUser($user)->allows('view', $subject) || $subject->familyMayViewGrades($user));
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
        /** @var Student $subject */
        $year = AcademicYear::query()->findOrFail($params['academic_year_id']);

        $built = $this->cards->cards($year, [$subject->id]);

        if ($built['cards'] === []) {
            throw ValidationException::withMessages([
                'document' => ['No results have been published for this student in this year yet.'],
            ]);
        }

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
            'count' => 1,
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
        $student = $document->subject;

        if (! $student instanceof Student) {
            return [];
        }

        $year = AcademicYear::query()->find($document->params['academic_year_id'] ?? null);

        return array_filter([
            'student' => $student->full_name,
            'student_id' => $student->public_id,
            'school' => $document->school?->name,
            'coverage' => $year?->name,
            'issued_on' => $document->created_at?->toDateString(),
        ], fn ($v) => $v !== null && $v !== '');
    }
}

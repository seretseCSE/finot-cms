<?php

namespace App\Services\Documents\Types;

use App\Models\Branch;
use App\Models\GeneratedDocument;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentTermResult;
use App\Models\User;
use App\Services\Documents\DocumentType;
use App\Services\Pdf\InlineImage;
use App\Services\Reports\StudentReportService;
use App\Services\Reports\SubjectRankResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** The official term report card — frozen student_term_results only. */
class ReportCardDocument extends DocumentType
{
    public function __construct(private readonly StudentReportService $reports)
    {
    }

    public function view(): string
    {
        return 'report-card';
    }

    public function rules(): array
    {
        return ['term_id' => ['required', 'integer', 'exists:terms,id']];
    }

    public function resolveSubject(?int $subjectId): ?Model
    {
        return Student::find($subjectId);
    }

    public function authorize(User $user, ?Model $subject, array $params): bool
    {
        // Staff lane (row-anchored policy) OR the family itself (ADR-012) —
        // parents/students print the same official QR-bearing PDF.
        return $subject instanceof Student
            && (Gate::forUser($user)->allows('view', $subject) || $subject->familyMayViewGrades($user));
    }

    public function anchor(?Model $subject, array $params): array
    {
        /** @var Student $subject */
        $result = $this->result($subject, (int) ($params['term_id'] ?? 0));

        return [
            'school_id' => $result->school_id ?? $subject->school_id,
            'branch_id' => $result->branch_id ?? $subject->branch_id,
        ];
    }

    public function payload(?Model $subject, array $params): array
    {
        /** @var Student $subject */
        $card = $this->reports->reportCard($subject, (int) ($params['term_id'] ?? 0));

        if ($card === null) {
            throw ValidationException::withMessages([
                'document' => ['Results for this term have not been published yet.'],
            ]);
        }

        // Self-contained PDF HTML (PdfRenderer contract): the school logo
        // travels inline as a data URI — the renderer fetches nothing.
        $result = $this->result($subject, (int) ($params['term_id'] ?? 0));
        $school = School::query()->find($result?->school_id);
        $card['school_logo_url'] = InlineImage::fromStorage($school?->logo_path);

        // Branch-effective print options (in the hash: flipping the setting
        // re-renders the card).
        $branch = Branch::query()->find($result?->branch_id);
        $showRanks = $branch?->effectiveReportCardSubjectRanks() ?? false;

        // Rows frozen before the subject-rank release carry no ranks —
        // backfill them read-time from the frozen section cohort so the
        // setting works on history without a recompute.
        if ($showRanks) {
            $card = app(SubjectRankResolver::class)->fill([$card], (int) $params['term_id'])[0];
        }

        return [
            'card' => $card,
            'show_subject_ranks' => $showRanks,
        ];
    }

    /** v6: larger QR for easier scanning. */
    public function templateVersion(): int
    {
        return 6;
    }

    public function verifySummary(GeneratedDocument $document): array
    {
        $student = $document->subject;

        if (! $student instanceof Student) {
            return [];
        }

        return [
            'student' => $student->full_name,
            'student_id' => $student->public_id,
            'school' => $document->school?->name,
            'issued_on' => $document->created_at?->toDateString(),
        ];
    }

    private function result(Student $student, int $termId): ?StudentTermResult
    {
        return StudentTermResult::query()
            ->where('student_id', $student->id)
            ->where('term_id', $termId)
            ->first(['school_id', 'branch_id']);
    }
}

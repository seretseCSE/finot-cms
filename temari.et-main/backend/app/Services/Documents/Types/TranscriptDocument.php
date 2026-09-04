<?php

namespace App\Services\Documents\Types;

use App\Models\GeneratedDocument;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentTermResult;
use App\Models\User;
use App\Services\Documents\DocumentType;
use App\Services\Pdf\InlineImage;
use App\Services\Reports\StudentReportService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * The official multi-year transcript, built from frozen term results only.
 * Its QR resolves to the public verify page — authenticity without grades.
 */
class TranscriptDocument extends DocumentType
{
    public function __construct(private readonly StudentReportService $reports)
    {
    }

    public function view(): string
    {
        return 'transcript';
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
        $latest = StudentTermResult::query()
            ->where('student_id', $subject->id)
            ->latest('id')
            ->first(['school_id', 'branch_id']);

        return [
            'school_id' => $latest->school_id ?? $subject->school_id,
            'branch_id' => $latest->branch_id ?? $subject->branch_id,
        ];
    }

    public function payload(?Model $subject, array $params): array
    {
        /** @var Student $subject */
        $transcript = $this->reports->transcript(
            $subject,
            isset($params['academic_year_ids'])
                ? array_map('intval', $params['academic_year_ids'])
                : null,
        );

        // The PDF HTML must be SELF-CONTAINED (PdfRenderer contract): the
        // remote renderer never fetches signed URLs, so the student photo
        // and school logo travel inline as data URIs — like the QR does.
        $transcript['student']['photo_url'] = InlineImage::fromStorage($subject->photo_path);

        if ($transcript['issued_by'] !== null) {
            $school = School::query()->find($this->anchor($subject, $params)['school_id']);
            $transcript['issued_by']['logo_url'] = InlineImage::fromStorage($school?->logo_path);
        }

        return ['transcript' => $transcript];
    }

    /** Optional year narrowing — a subset print is stamped PARTIAL. */
    public function rules(): array
    {
        return [
            'academic_year_ids' => ['sometimes', 'array', 'min:1'],
            'academic_year_ids.*' => ['integer'],
        ];
    }

    /** The Ethiopian year-grid transcript prints landscape. */
    public function landscape(): bool
    {
        return true;
    }

    /** The timestamp lives INSIDE the transcript payload (dot notation). */
    public function volatileKeys(): array
    {
        return ['generated_at', 'qr', 'transcript.generated_at'];
    }

    /** Convergent fit-to-one-page scaling (shared sheet partial). */
    public function templateVersion(): int
    {
        return 8;
    }

    /** Possession of the paper = possession of the record (receipt precedent). */
    public function publiclyDownloadable(): bool
    {
        return true;
    }

    /**
     * The QR opens the LIVE transcript page (public, token-scoped, killed by
     * revoke) — the same article the print view renders, not a bare summary.
     */
    public function qrTarget(GeneratedDocument $document): string
    {
        return rtrim((string) config('sms.frontend_url'), '/').'/transcripts/'.$document->public_token;
    }

    public function verifySummary(GeneratedDocument $document): array
    {
        $student = $document->subject;

        if (! $student instanceof Student) {
            return [];
        }

        // The verify page must expose a PARTIAL print as one — otherwise a
        // subset could masquerade as the complete record.
        $yearIds = $document->params['academic_year_ids'] ?? null;
        $covered = null;

        if ($yearIds !== null) {
            $covered = StudentTermResult::query()
                ->where('student_id', $student->id)
                ->whereIn('academic_year_id', $yearIds)
                ->with('gradeLevel:id,name,sort_order')
                ->get()
                ->pluck('gradeLevel.name')
                ->filter()
                ->unique()
                ->implode(', ');
        }

        return array_filter([
            'student' => $student->full_name,
            'student_id' => $student->public_id,
            'school' => $document->school?->name,
            'coverage' => $yearIds === null ? null : 'Partial — '.($covered ?: 'selected years only'),
            'issued_on' => $document->created_at?->toDateString(),
        ], fn ($v) => $v !== null);
    }
}

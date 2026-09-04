<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\GeneratedDocument;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Services\Reports\StudentReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The transcripts workspace: a per-year register of enrolled students with
 * transcript readiness, and a batch endpoint returning up to 60 full
 * transcripts in one query — the frontend renders/prints/exports them
 * (documents are generated client-side, never stored).
 *
 * Supervisory grades.view reads the whole scope; a homeroom teacher
 * (grades.manage_own) only their own homeroom sections — the same dual lane
 * as report cards and rosters. A batch transcript may include prior years at
 * other schools: like the single-student endpoint, the school currently
 * holding the student prints the student's complete record.
 */
class TranscriptController extends Controller
{
    public function __construct(private readonly StudentReportService $reports)
    {
    }

    public function register(Request $request, AcademicYear $academicYear): JsonResponse
    {
        $allowedSectionIds = $this->allowedSectionIds(
            $request,
            $academicYear->school_id,
            $academicYear->branch_id,
            $academicYear->id,
        );

        return response()->json($this->reports->transcriptRegister(
            $academicYear,
            $request->filled('section_id') ? $request->integer('section_id') : null,
            $request->filled('grade_level_id') ? $request->integer('grade_level_id') : null,
            $allowedSectionIds,
        ));
    }

    /**
     * Up to 60 transcripts in one response. Tenant safety: the permission is
     * checked against the anchor YEAR's school/branch, and every requested
     * student must hold an ACTIVE enrollment in that year — a student id from
     * another school has no such row, so one foreign id fails the whole batch.
     */
    public function batch(Request $request): JsonResponse
    {
        $data = $request->validate([
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'student_ids' => ['required', 'array', 'min:1', 'max:60'],
            'student_ids.*' => ['integer'],
            // Optional year narrowing (partial transcripts) for the whole batch.
            'academic_year_ids' => ['sometimes', 'array', 'min:1'],
            'academic_year_ids.*' => ['integer'],
        ]);

        $year = AcademicYear::findOrFail($data['academic_year_id']);
        $allowedSectionIds = $this->allowedSectionIds($request, $year->school_id, $year->branch_id, $year->id);

        $ids = collect($data['student_ids'])->unique()->values();

        $enrollments = StudentEnrollment::query()
            ->where('academic_year_id', $year->id)
            ->where('status', EnrollmentStatus::Active->value)
            ->whereIn('student_id', $ids)
            ->get(['id', 'student_id', 'section_id']);

        abort_unless($enrollments->pluck('student_id')->unique()->count() === $ids->count(), 403);

        // Homeroom lane: every requested student must sit in the caller's own
        // homeroom — never another class's records.
        if ($allowedSectionIds !== null) {
            abort_unless(
                $enrollments->every(fn ($e) => $e->section_id !== null
                    && in_array((int) $e->section_id, $allowedSectionIds, true)),
                403,
            );
        }

        // Preserve the requested order (the register's sheet order).
        $students = Student::query()->whereIn('id', $ids)->get()
            ->sortBy(fn (Student $s) => $ids->search($s->id))
            ->values();

        return response()->json(['data' => $this->reports->transcripts(
            $students,
            isset($data['academic_year_ids']) ? array_map('intval', $data['academic_year_ids']) : null,
        )]);
    }

    /**
     * UNAUTHENTICATED page behind the transcript QR: whoever holds the paper
     * holds the record (receipt precedent), so the token renders the full
     * article — always the AUTHORITATIVE live data, built with the same year
     * narrowing the document was issued with. Revoking kills it.
     */
    public function publicTranscript(string $token): JsonResponse
    {
        // public_token is a Postgres uuid — a malformed probe must 404,
        // never bubble up as a database error.
        abort_unless(Str::isUuid($token), 404);

        $document = GeneratedDocument::query()
            ->where('public_token', $token)
            ->where('type', 'transcript')
            ->firstOrFail();

        abort_if($document->revoked_at !== null, 410, 'This transcript has been revoked by the issuing school.');

        $student = $document->subject;
        abort_unless($student instanceof Student, 404);

        $yearIds = $document->params['academic_year_ids'] ?? null;

        return response()->json(['data' => [
            'transcript' => $this->reports->transcript(
                $student,
                $yearIds === null ? null : array_map('intval', $yearIds),
            ),
            'download_url' => $document->downloadUrl(),
            // Inline URL: "Print" opens the PDF in the tab's viewer instead
            // of dropping a file in the downloads folder.
            'view_url' => $document->viewUrl(),
            'issued_on' => $document->created_at?->toDateString(),
        ]]);
    }

    /**
     * Dual-lane gate (mirrors TermResultController::index): supervisors are
     * unrestricted (null); homeroom teachers get their own sections (possibly
     * an empty list — an empty register, never another class's rows).
     *
     * @return list<int>|null
     */
    private function allowedSectionIds(Request $request, int $schoolId, int $branchId, int $academicYearId): ?array
    {
        $user = $request->user();

        if ($user->hasPermissionForScope('grades.view', $schoolId, $branchId)) {
            return null;
        }

        abort_unless($user->hasPermissionForScope('grades.manage_own', $schoolId, $branchId), 403);

        return $user->homeroomSectionIds($academicYearId);
    }
}

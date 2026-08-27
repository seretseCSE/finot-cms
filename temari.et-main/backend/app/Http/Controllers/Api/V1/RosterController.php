<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\GeneratedDocument;
use App\Models\GradeLevel;
use App\Models\Section;
use App\Models\Term;
use App\Services\Reports\RosterReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The Ethiopian roster sheets over frozen student_term_results: one term
 * (students × subjects with total/average/rank) and one academic year (per
 * term marks, semester sub-averages, yearly average + read-time rank).
 * Supervisory grades.view reads everything in scope; a homeroom teacher
 * (grades.manage_own) reads only their own homeroom sections — same dual
 * lane as the report-card register. Recomputing stays on
 * POST terms/{term}/compute-results.
 */
class RosterController extends Controller
{
    public function __construct(private readonly RosterReportService $rosters) {}

    public function term(Request $request, Term $term): JsonResponse
    {
        $allowedSectionIds = $this->allowedSectionIds($request, $term->school_id, $term->branch_id, $term->academic_year_id);

        return response()->json($this->rosters->termRoster(
            $term,
            $request->filled('section_id') ? $request->integer('section_id') : null,
            $request->filled('grade_level_id') ? $request->integer('grade_level_id') : null,
            $allowedSectionIds,
        ));
    }

    public function year(Request $request, AcademicYear $academicYear): JsonResponse
    {
        $allowedSectionIds = $this->allowedSectionIds($request, $academicYear->school_id, $academicYear->branch_id, $academicYear->id);

        return response()->json($this->rosters->yearRoster(
            $academicYear,
            $request->filled('section_id') ? $request->integer('section_id') : null,
            $request->filled('grade_level_id') ? $request->integer('grade_level_id') : null,
            $allowedSectionIds,
        ));
    }

    /**
     * PUBLIC roster page — what the QR on the official roster PDF opens. No
     * login: possession of the printed sheet (its token) is possession of the
     * record, exactly like public transcripts. Always renders the AUTHORITATIVE
     * live data with the same scope the document was issued with (supervisory,
     * so no section restriction); revoking the document kills this page.
     */
    public function publicRoster(string $token): JsonResponse
    {
        // public_token is a Postgres uuid — a malformed probe must 404,
        // never bubble up as a database error.
        abort_unless(Str::isUuid($token), 404);

        $document = GeneratedDocument::query()
            ->where('public_token', $token)
            ->where('type', 'roster')
            ->firstOrFail();

        abort_if($document->revoked_at !== null, 410, 'This roster has been revoked by the issuing school.');

        $params = $document->params ?? [];
        $sectionId = isset($params['section_id']) ? (int) $params['section_id'] : null;
        $gradeLevelId = isset($params['grade_level_id']) ? (int) $params['grade_level_id'] : null;

        if (($params['scope'] ?? null) === 'year') {
            $year = AcademicYear::query()->findOrFail($params['academic_year_id']);
            $roster = $this->rosters->yearRoster($year, $sectionId, $gradeLevelId, null);
            $periodLabel = $year->name;
        } else {
            $term = Term::query()->with('academicYear:id,name')->findOrFail($params['term_id']);
            $roster = $this->rosters->termRoster($term, $sectionId, $gradeLevelId, null);
            $periodLabel = $term->academicYear?->name
                ? $term->name.' · '.$term->academicYear->name
                : $term->name;
        }

        $section = $sectionId ? Section::query()->with('gradeLevel:id,name')->find($sectionId) : null;
        $grade = $gradeLevelId ? GradeLevel::query()->find($gradeLevelId) : null;
        $scopeLabel = $section !== null
            ? trim(($section->gradeLevel?->name ?? '').' '.$section->name)
            : ($grade !== null ? $grade->name : null);

        $roster = $this->stripStaffOnlyFields($roster);

        return response()->json(['data' => [
            'scope' => $params['scope'] ?? 'term',
            'period_label' => $periodLabel,
            'scope_label' => $scopeLabel,
            'show_section' => $sectionId === null,
            'school_name' => $document->school?->name,
            'branch_name' => $document->branch?->name,
            'data' => $roster['data'],
            'meta' => $roster['meta'],
            'download_url' => $document->downloadUrl(),
            // Inline URL: "Print" opens the PDF in the tab's viewer instead
            // of dropping a file in the downloads folder.
            'view_url' => $document->viewUrl(),
            'issued_on' => $document->created_at?->toDateString(),
        ]]);
    }

    /**
     * The staff roster rows carry report-card working data (homeroom comment,
     * behavior skill ratings, enrollment ids, the school's skill config) for
     * the Extra-assessment surface. None of it prints on the roster sheet,
     * so the PUBLIC QR payload must never carry it — possession of the sheet
     * proves the sheet, nothing more.
     *
     * @param  array{data: array<string, mixed>, meta: array<string, mixed>}  $roster
     * @return array{data: array<string, mixed>, meta: array<string, mixed>}
     */
    private function stripStaffOnlyFields(array $roster): array
    {
        $strip = fn (array $line): array => collect($line)
            ->except(['comment', 'skills', 'student_enrollment_id'])
            ->all();

        if (isset($roster['data']['rows'])) {
            $roster['data']['rows'] = array_map($strip, $roster['data']['rows']);
        }

        if (isset($roster['data']['students'])) {
            $roster['data']['students'] = array_map(
                fn (array $student): array => [
                    ...$student,
                    'terms' => array_map($strip, $student['terms']),
                ],
                $roster['data']['students'],
            );
        }

        unset($roster['meta']['report_card']);

        return $roster;
    }

    /**
     * Dual-lane gate (mirrors TermResultController::index): supervisors are
     * unrestricted (null); homeroom teachers get their own sections (possibly
     * an empty list — an empty sheet, never another class's rows).
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

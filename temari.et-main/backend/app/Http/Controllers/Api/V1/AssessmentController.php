<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BulkUpsertResultsRequest;
use App\Http\Requests\Api\V1\StoreAssessmentRequest;
use App\Http\Requests\Api\V1\UpdateAssessmentRequest;
use App\Http\Resources\AssessmentResource;
use App\Http\Resources\AssessmentResultResource;
use App\Models\Assessment;
use App\Models\Student;
use App\Models\SubjectAssignment;
use App\Services\ContinuousAssessmentMaterializer;
use App\Support\TermGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssessmentController extends Controller
{
    public function index(Request $request, SubjectAssignment $subjectAssignment, ContinuousAssessmentMaterializer $materializer): AnonymousResourceCollection
    {
        $this->authorize('viewAssignment', [Assessment::class, $subjectAssignment]);

        // The plan materialises on first sight — a teacher picking a gradebook
        // slot (e.g. in the exam builder) must find it even before anyone has
        // opened the marklist.
        $book = $materializer->bookFor($subjectAssignment);

        if ($book !== null && ! $subjectAssignment->term?->isClosed()) {
            $materializer->materialize($subjectAssignment, $book);
        }

        $assessments = $subjectAssignment->assessments()
            ->withCount('results')
            ->orderBy('conducted_on')
            ->get();

        return AssessmentResource::collection($assessments);
    }

    public function store(StoreAssessmentRequest $request, SubjectAssignment $subjectAssignment, ContinuousAssessmentMaterializer $materializer): JsonResponse
    {
        $this->authorize('canManage', [Assessment::class, $subjectAssignment]);
        TermGate::assertWritable($subjectAssignment->term);
        $this->assertMarklistDraft($subjectAssignment);
        $this->assertTeacherMayDefine($subjectAssignment);

        // Where a grade book governs, the structure IS the plan — nobody adds
        // ad-hoc rows on top (edit the grade book instead, weights stay 100).
        if ($materializer->bookFor($subjectAssignment) !== null) {
            throw ValidationException::withMessages([
                'name' => ['This continuous assessment follows a grade book plan — assessments are defined there.'],
            ]);
        }

        $this->assertWeightBudget($subjectAssignment, (float) $request->validated('weight'));

        $assessment = $subjectAssignment->assessments()->create($request->validated());

        return (new AssessmentResource($assessment))
            ->additional(['message' => 'Assessment created.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateAssessmentRequest $request, Assessment $assessment): AssessmentResource
    {
        $this->authorize('update', $assessment);
        TermGate::assertWritable($assessment->subjectAssignment->term);
        $this->assertMarklistDraft($assessment->subjectAssignment);
        $this->assertNotPlanned($assessment);
        $this->assertTeacherMayDefine($assessment->subjectAssignment);

        if ($request->has('weight')) {
            $this->assertWeightBudget(
                $assessment->subjectAssignment,
                (float) $request->validated('weight'),
                $assessment->id,
            );
        }

        $assessment->update($request->validated());

        return new AssessmentResource($assessment);
    }

    public function destroy(Assessment $assessment): JsonResponse
    {
        $this->authorize('delete', $assessment);
        TermGate::assertWritable($assessment->subjectAssignment->term);
        $this->assertMarklistDraft($assessment->subjectAssignment);
        $this->assertNotPlanned($assessment);
        $this->assertTeacherMayDefine($assessment->subjectAssignment);

        $assessment->delete();

        return response()->json(['message' => 'Assessment deleted.']);
    }

    /** Return all results for an assessment (roster view). */
    public function results(Assessment $assessment): AnonymousResourceCollection
    {
        $this->authorize('viewAssignment', [Assessment::class, $assessment->subjectAssignment]);

        return AssessmentResultResource::collection(
            $assessment->results()->with('student')->get()
        );
    }

    /** Bulk-upsert marks for an assessment. */
    public function upsertResults(BulkUpsertResultsRequest $request, Assessment $assessment): JsonResponse
    {
        // Mark entry, not structure: a supervisor on a teacher-owned draft is
        // denied here until they declare on-behalf entry (marklists/{id}/assist).
        $this->authorize('enterMarks', $assessment);
        TermGate::assertWritable($assessment->subjectAssignment->term);
        $this->assertMarklistDraft($assessment->subjectAssignment);

        $max = (float) $assessment->max_score;
        $over = collect($request->validated('results'))
            ->filter(fn (array $row): bool => isset($row['score']) && (float) $row['score'] > $max);

        if ($over->isNotEmpty()) {
            // Name every offending row — "score can't exceed X" with no student
            // is useless when a teacher just typed 40 marks. One message per
            // student: who, which assessment, what they entered, the real max.
            $names = Student::query()
                ->whereIn('id', $over->pluck('student_id'))
                ->get()
                ->mapWithKeys(fn (Student $s): array => [$s->id => $s->full_name]);

            throw ValidationException::withMessages([
                'results' => $over->map(fn (array $row): string => sprintf(
                    '%s — %s: %s entered, but the maximum is %s (weight %s%%).',
                    $names[$row['student_id']] ?? "Student #{$row['student_id']}",
                    $assessment->name,
                    $row['score'] + 0,
                    $max + 0,
                    $assessment->weight + 0,
                ))->values()->all(),
            ]);
        }

        $employeeId = $request->user()?->employee?->id;

        // Per-cell authorship (`recorded_by`) is the audit trail behind the
        // "entered by" badges — only rows whose VALUE actually changed are
        // written, so re-saving a full grid never re-attributes untouched
        // marks to the last saver.
        $existing = $assessment->results()
            ->whereIn('student_id', collect($request->validated('results'))->pluck('student_id'))
            ->get()
            ->keyBy('student_id');

        $rows = collect($request->validated('results'))
            ->reject(function (array $row) use ($existing): bool {
                $current = $existing->get($row['student_id']);
                if ($current === null) {
                    return false;
                }

                $score = isset($row['score']) ? (float) $row['score'] : null;
                $currentScore = $current->score !== null ? (float) $current->score : null;

                return $score === $currentScore
                    && (bool) ($row['is_absent'] ?? false) === (bool) $current->is_absent
                    && ($row['remarks'] ?? null) === $current->remarks;
            })
            ->map(fn (array $row) => [
                'assessment_id' => $assessment->id,
                'student_id' => $row['student_id'],
                'score' => $row['score'] ?? null,
                'is_absent' => $row['is_absent'] ?? false,
                'remarks' => $row['remarks'] ?? null,
                'recorded_by' => $employeeId,
                'created_at' => now(),
                'updated_at' => now(),
            ])->values()->all();

        if ($rows !== []) {
            DB::table('assessment_results')->upsert(
                $rows,
                ['assessment_id', 'student_id'],
                ['score', 'is_absent', 'remarks', 'recorded_by', 'updated_at'],
            );
        }

        return response()->json(['message' => 'Results saved.', 'meta' => ['count' => count($rows)]]);
    }

    /**
     * Marks and structure are frozen once the marklist leaves draft —
     * submitted/approved continuous assessments reopen through the marklist workflow,
     * never by silent edits.
     */
    private function assertMarklistDraft(SubjectAssignment $assignment): void
    {
        $marklist = $assignment->marklist;

        if ($marklist !== null && $marklist->isLocked()) {
            throw ValidationException::withMessages([
                'marklist' => ["This marklist is {$marklist->status->value} — reopen it before editing marks."],
            ]);
        }
    }

    /**
     * Free-form assessment STRUCTURE (add/edit/delete) is supervisory by
     * default; a teacher may shape their own marklist only when the branch
     * has opted in (`teacher_assessments_enabled`, school default off).
     * Mark ENTRY is untouched — this gates structure, never scores.
     */
    private function assertTeacherMayDefine(SubjectAssignment $assignment): void
    {
        $user = request()->user();

        if ($user->hasPermissionForScope('grades.manage', $assignment->school_id, $assignment->branch_id)) {
            return;
        }

        $assignment->loadMissing('branch.school');

        if (! $assignment->branch?->effectiveTeacherAssessmentsEnabled()) {
            throw ValidationException::withMessages([
                'name' => ['Teachers cannot define assessments at this branch — the continuous-assessment plan is set by the school office.'],
            ]);
        }
    }

    private function assertNotPlanned(Assessment $assessment): void
    {
        if ($assessment->isPlanned()) {
            throw ValidationException::withMessages([
                'name' => ['This assessment comes from the grade book plan — edit the grade book instead.'],
            ]);
        }
    }

    /**
     * Free-form continuous assessments still respect the 100% budget: the weights of one
     * assignment's assessments may never sum past 100.
     */
    private function assertWeightBudget(SubjectAssignment $assignment, float $weight, ?int $ignoreId = null): void
    {
        $current = (float) $assignment->assessments()
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
            ->sum('weight');

        if ($current + $weight > 100.005) {
            $remaining = max(0, round(100 - $current, 2));

            throw ValidationException::withMessages([
                'weight' => ["Total weight would exceed 100 — {$remaining} remains for this subject."],
            ]);
        }
    }
}

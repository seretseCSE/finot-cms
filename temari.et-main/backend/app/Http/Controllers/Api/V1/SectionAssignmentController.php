<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CommitSectionAssignmentsAction;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Section;
use App\Models\StudentEnrollment;
use App\Services\Notify\Notifier;
use App\Services\SectionAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The class-formation board: pool + sections → auto-balanced proposal →
 * reviewed commit. Gated by `sections.update` against the year's scope.
 */
class SectionAssignmentController extends Controller
{
    public function board(Request $request, SectionAssignmentService $service): JsonResponse
    {
        $request->validate([
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'grade_level_id' => ['required', 'integer', 'exists:grade_levels,id'],
        ]);

        $year = $this->authorizedYear($request);
        $board = $service->board($year, $request->integer('grade_level_id'));

        return response()->json(['data' => $board]);
    }

    public function propose(Request $request, SectionAssignmentService $service): JsonResponse
    {
        $data = $request->validate([
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'grade_level_id' => ['required', 'integer', 'exists:grade_levels,id'],
            'mode' => ['sometimes', 'in:fill,reshuffle'],
        ]);

        $year = $this->authorizedYear($request);

        $proposal = $service->propose(
            $year,
            (int) $data['grade_level_id'],
            $data['mode'] ?? 'fill',
        );

        return response()->json(['data' => $proposal]);
    }

    public function commit(Request $request, CommitSectionAssignmentsAction $action): JsonResponse
    {
        $data = $request->validate([
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'assignments' => ['required', 'array', 'min:1', 'max:2000'],
            'assignments.*.enrollment_id' => ['required', 'integer'],
            'assignments.*.section_id' => ['nullable', 'integer'],
        ]);

        $year = $this->authorizedYear($request);
        $updated = $action->execute($year, $data['assignments'], $request->user());

        return response()->json([
            'message' => 'Section assignments saved.',
            'meta' => ['updated' => $updated],
        ]);
    }

    /**
     * Bulk assign/move/unassign from the student register: a hand-picked set
     * of students → one section (or back to the pool with `section_id: null`).
     * Students without a live enrollment in the year, or enrolled in another
     * grade, are SKIPPED and reported — one stray row never kills the batch.
     */
    public function assignStudents(
        Request $request,
        CommitSectionAssignmentsAction $action,
        Notifier $notifier,
    ): JsonResponse {
        $data = $request->validate([
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'student_ids' => ['required', 'array', 'min:1', 'max:500'],
            'student_ids.*' => ['integer'],
            'section_id' => ['nullable', 'integer'],
        ]);

        $year = $this->authorizedYear($request);

        $section = null;
        if (! empty($data['section_id'])) {
            $section = Section::query()
                ->where('branch_id', $year->branch_id)
                ->with('gradeLevel:id,name')
                ->find((int) $data['section_id']);

            if ($section === null) {
                throw ValidationException::withMessages([
                    'section_id' => ['This section does not belong to the selected year\'s branch.'],
                ]);
            }
        }

        $enrollments = StudentEnrollment::query()
            ->where('academic_year_id', $year->id)
            ->whereIn('student_id', array_map(intval(...), $data['student_ids']))
            ->live()
            ->with('student:id,user_id,first_name,father_name,grandfather_name')
            ->get()
            ->keyBy('student_id');

        $assignments = [];
        $changed = [];
        $skipped = [];

        foreach (array_unique(array_map(intval(...), $data['student_ids'])) as $studentId) {
            $enrollment = $enrollments->get($studentId);

            if ($enrollment === null) {
                $skipped[] = ['student_id' => $studentId, 'name' => null, 'reason' => 'not_enrolled'];

                continue;
            }

            if ($section !== null && $section->grade_level_id !== $enrollment->grade_level_id) {
                $skipped[] = [
                    'student_id' => $studentId,
                    'name' => $enrollment->student?->full_name,
                    'reason' => 'grade_mismatch',
                ];

                continue;
            }

            $assignments[] = ['enrollment_id' => $enrollment->id, 'section_id' => $section?->id];

            if ($enrollment->section_id !== $section?->id) {
                $changed[] = $enrollment;
            }
        }

        $updated = $assignments === []
            ? 0
            : $action->execute($year, $assignments, $request->user());

        // Families learn about a NEW section in-app (never SMS — routine ops);
        // returning a student to the pool is internal and stays silent.
        if ($section !== null) {
            foreach ($changed as $enrollment) {
                $notifier->toFamily($enrollment->student, 'academics.section_assigned', [
                    'grade' => $section->gradeLevel?->name ?? '',
                    'section' => $section->name,
                ], [
                    'link' => '/me',
                    'dedupeKey' => "section-assigned:{$enrollment->id}:{$section->id}",
                ]);
            }
        }

        return response()->json([
            'message' => 'Section assignments saved.',
            'meta' => ['updated' => $updated, 'skipped' => $skipped],
        ]);
    }

    private function authorizedYear(Request $request): AcademicYear
    {
        $year = AcademicYear::findOrFail($request->integer('academic_year_id'));

        abort_unless(
            $request->user()->hasPermissionForScope('sections.update', $year->school_id, $year->branch_id),
            403,
        );

        return $year;
    }
}

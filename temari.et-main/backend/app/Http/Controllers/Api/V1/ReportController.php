<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\Student;
use App\Models\SubjectAssignment;
use App\Services\Reports\StudentReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * Result card: all subject scores for a student in a given term,
     * with weighted totals per subject.
     */
    public function resultCard(Request $request, Student $student, StudentReportService $reports): JsonResponse
    {
        $request->validate(['term_id' => ['required', 'integer', 'exists:terms,id']]);

        // Ensure the requesting user can view this student
        $this->authorize('view', $student);

        return response()->json(['data' => $reports->resultCard($student, $request->integer('term_id'))]);
    }

    /**
     * The OFFICIAL (frozen) report card for a term — what gets printed and
     * signed. Null data until the term's results are computed.
     */
    public function reportCard(Request $request, Student $student, StudentReportService $reports): JsonResponse
    {
        $request->validate(['term_id' => ['required', 'integer', 'exists:terms,id']]);

        $this->authorize('view', $student);

        return response()->json(['data' => $reports->reportCard($student, $request->integer('term_id'))]);
    }

    /**
     * Multi-year transcript built solely from frozen term results. An
     * optional `academic_year_ids[]` narrows the sheet to those years — the
     * payload is then stamped `is_partial` (default: the complete record).
     */
    public function transcript(Request $request, Student $student, StudentReportService $reports): JsonResponse
    {
        $data = $request->validate([
            'academic_year_ids' => ['sometimes', 'array', 'min:1'],
            'academic_year_ids.*' => ['integer'],
        ]);

        $this->authorize('view', $student);

        return response()->json(['data' => $reports->transcript(
            $student,
            isset($data['academic_year_ids']) ? array_map('intval', $data['academic_year_ids']) : null,
        )]);
    }

    /**
     * Attendance summary for a student in a given term.
     */
    public function attendanceSummary(Request $request, Student $student, StudentReportService $reports): JsonResponse
    {
        $request->validate(['term_id' => ['required', 'integer', 'exists:terms,id']]);

        $this->authorize('view', $student);

        return response()->json(['data' => $reports->attendanceSummary($student, $request->integer('term_id'))]);
    }

    /**
     * Section-level continuous assessment: all students × all assessments for a subject assignment.
     */
    public function sectionContinuousAssessment(Request $request, SubjectAssignment $subjectAssignment): JsonResponse
    {
        $this->authorize('viewAssignment', [Assessment::class, $subjectAssignment]);

        $subjectAssignment->load([
            'subject',
            'section.enrollments.student',
            'assessments.results',
        ]);

        $students = $subjectAssignment->section->enrollments
            ->where('status', 'active')
            ->map(fn ($e) => $e->student);

        $assessments = $subjectAssignment->assessments;

        $rows = $students->map(function ($student) use ($assessments): array {
            $scores = $assessments->map(function ($a) use ($student): array {
                $result = $a->results->firstWhere('student_id', $student->id);

                return [
                    'assessment_id' => $a->id,
                    'score' => $result ? (float) $result->score : null,
                    'is_absent' => $result?->is_absent ?? false,
                ];
            });

            return [
                'student_id' => $student->id,
                'full_name' => $student->full_name,
                'scores' => $scores->values(),
            ];
        });

        return response()->json([
            'data' => [
                'subject_assignment_id' => $subjectAssignment->id,
                'subject' => [
                    'id' => $subjectAssignment->subject->id,
                    'name' => $subjectAssignment->subject->name,
                ],
                'assessments' => $assessments->map(fn ($a) => [
                    'id' => $a->id,
                    'type' => $a->type,
                    'name' => $a->name,
                    'max_score' => (float) $a->max_score,
                    'weight' => (float) $a->weight,
                ])->values(),
                'students' => $rows->values(),
            ],
        ]);
    }
}

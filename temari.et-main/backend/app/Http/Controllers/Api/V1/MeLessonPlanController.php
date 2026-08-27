<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentGuardian;
use App\Services\LessonPlans\FamilyLessonPlanPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lesson plans through the relationship lane (ADR-012): a student (or a
 * linked guardian) follows what each subject teacher planned — the approved
 * syllabus roadmap, how far the class has come, and this week's approved
 * topics. ONLY approved plans are visible; drafts, submissions, decline
 * reasons and lag justifications are staff-internal and never leave the
 * staff lane. The payload itself is shared with the AI family tools —
 * see FamilyLessonPlanPayload.
 */
class MeLessonPlanController extends Controller
{
    public function own(Request $request, FamilyLessonPlanPayload $payload): JsonResponse
    {
        $student = Student::query()->where('user_id', $request->user()->id)->first();
        abort_if($student === null, 404, 'No student record is linked to your account.');

        return response()->json(['data' => $payload->forStudent($student)]);
    }

    public function child(Request $request, Student $student, FamilyLessonPlanPayload $payload): JsonResponse
    {
        $parentId = $request->user()->parentProfile()->value('id');

        $link = StudentGuardian::query()
            ->where('is_active', true)
            ->where('parent_id', $parentId ?? 0)
            ->where('student_id', $student->id)
            ->exists();

        abort_unless($link, 403, 'This student is not linked to your account.');

        return response()->json(['data' => $payload->forStudent($student)]);
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RevertPromotionsAction;
use App\Actions\RolloverPromotionsAction;
use App\Actions\SavePromotionDecisionsAction;
use App\Enums\PromotionDecision;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Services\PromotionBoardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The year-end promotion board: compute → review/decide → execute (rollover).
 * All three stages are gated by `promotion.manage` against the year's scope,
 * so a principal can run any branch's board from the school-wide workspace.
 */
class PromotionController extends Controller
{
    public function board(Request $request): JsonResponse
    {
        $request->validate([
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'grade_level_id' => ['nullable', 'integer', 'exists:grade_levels,id'],
        ]);

        $year = AcademicYear::with('branch.school')->findOrFail($request->integer('academic_year_id'));
        $this->authorizeYear($request, $year, 'promotion.manage');

        $board = app(PromotionBoardService::class)->build(
            $year,
            $request->filled('grade_level_id') ? $request->integer('grade_level_id') : null,
        );

        return response()->json([
            'data' => $board['rows'],
            'meta' => [
                'terms' => $board['terms'],
                'threshold' => $board['threshold'],
                'top_grade_sort' => $board['top_grade_sort'],
            ],
        ]);
    }

    public function saveDecisions(Request $request, SavePromotionDecisionsAction $action): JsonResponse
    {
        $data = $request->validate([
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'decisions' => ['required', 'array', 'min:1', 'max:500'],
            'decisions.*.enrollment_id' => ['required', 'integer'],
            'decisions.*.decision' => ['nullable', Rule::enum(PromotionDecision::class)],
            'decisions.*.notes' => ['nullable', 'string', 'max:500'],
        ]);

        $year = AcademicYear::with('branch.school')->findOrFail($data['academic_year_id']);
        $this->authorizeYear($request, $year, 'promotion.manage');

        $saved = $action->execute($year, $data['decisions'], $request->user());

        return response()->json([
            'message' => 'Decisions saved.',
            'meta' => ['saved' => $saved],
        ]);
    }

    public function rollover(Request $request, RolloverPromotionsAction $action): JsonResponse
    {
        $data = $request->validate([
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'to_academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'grade_level_id' => ['nullable', 'integer', 'exists:grade_levels,id'],
        ]);

        $fromYear = AcademicYear::with('branch.school')->findOrFail($data['academic_year_id']);
        $toYear = AcademicYear::findOrFail($data['to_academic_year_id']);
        $this->authorizeYear($request, $fromYear, 'promotion.manage');

        $result = $action->execute(
            $fromYear,
            $toYear,
            $request->user(),
            $data['grade_level_id'] ?? null,
        );

        return response()->json([
            'message' => "Rollover finished — {$result['executed']} students moved.",
            'data' => $result,
        ]);
    }

    /**
     * The rollover's safety net: put executed decisions back to "decided" —
     * the whole batch, one grade, or named students. Same permission as the
     * rollover; per-student blockers (attendance, marks, money received in
     * the new year) are enforced by the action and reported per row.
     */
    public function revert(Request $request, RevertPromotionsAction $action): JsonResponse
    {
        $data = $request->validate([
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'grade_level_id' => ['nullable', 'integer', 'exists:grade_levels,id'],
            'enrollment_ids' => ['nullable', 'array', 'max:500'],
            'enrollment_ids.*' => ['integer'],
        ]);

        $fromYear = AcademicYear::with('branch.school')->findOrFail($data['academic_year_id']);
        $this->authorizeYear($request, $fromYear, 'promotion.manage');

        $result = $action->execute(
            $fromYear,
            $request->user(),
            $data['grade_level_id'] ?? null,
            $data['enrollment_ids'] ?? null,
        );

        return response()->json([
            'message' => "Revert finished — {$result['reverted']} students moved back.",
            'data' => $result,
        ]);
    }

    private function authorizeYear(Request $request, AcademicYear $year, string $permission): void
    {
        abort_unless(
            $request->user()->hasPermissionForScope($permission, $year->school_id, $year->branch_id),
            403,
        );
    }
}

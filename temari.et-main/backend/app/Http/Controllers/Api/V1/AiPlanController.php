<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * School Plan AI entitlement administration — Temari.et platform staff
 * only (schools pay the School Plan invoice offline; a sales/finance admin
 * grants the months here). Never school-side self-service.
 */
class AiPlanController extends Controller
{
    public function show(Request $request, School $school): JsonResponse
    {
        abort_unless($request->user()->hasPlatformPermission('schools.view'), 403);

        return response()->json(['data' => [
            'school_id' => $school->id,
            'ai_plan_until' => $school->ai_plan_until?->toDateString(),
            'active' => $school->aiPlanActive(),
        ]]);
    }

    /** Extend (or start) the plan by N months from max(today, current end). */
    public function grant(Request $request, School $school): JsonResponse
    {
        abort_unless($request->user()->hasPlatformPermission('schools.update'), 403);

        $data = $request->validate([
            'months' => ['required', 'integer', 'min:1', 'max:24'],
        ]);

        $from = $school->ai_plan_until !== null && $school->ai_plan_until->isFuture()
            ? $school->ai_plan_until
            : today();

        $school->forceFill(['ai_plan_until' => $from->copy()->addMonths($data['months'])])->save();

        return response()->json([
            'data' => [
                'school_id' => $school->id,
                'ai_plan_until' => $school->ai_plan_until?->toDateString(),
                'active' => $school->aiPlanActive(),
            ],
            'message' => 'School AI plan updated.',
        ]);
    }

    /** End the plan (effective immediately). */
    public function revoke(Request $request, School $school): JsonResponse
    {
        abort_unless($request->user()->hasPlatformPermission('schools.update'), 403);

        $school->forceFill(['ai_plan_until' => today()->subDay()])->save();

        return response()->json(['message' => 'School AI plan ended.']);
    }
}

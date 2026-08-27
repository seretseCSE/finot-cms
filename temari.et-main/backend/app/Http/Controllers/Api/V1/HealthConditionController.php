<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\HealthCondition;
use Illuminate\Http\JsonResponse;

/**
 * The platform health-condition catalog (seed data) — read-only for schools,
 * consumed by the registration form's condition picker.
 */
class HealthConditionController extends Controller
{
    public function index(): JsonResponse
    {
        $conditions = HealthCondition::query()
            ->where('is_active', true)
            ->orderBy('category')
            ->orderBy('name')
            ->get(['id', 'name', 'category']);

        return response()->json(['data' => $conditions]);
    }
}

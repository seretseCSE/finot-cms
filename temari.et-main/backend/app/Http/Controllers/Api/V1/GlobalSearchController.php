<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\GlobalSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The ⌘K palette's backend — validation and scope resolution only; the actual
 * fan-out search lives in {@see GlobalSearchService}.
 */
class GlobalSearchController extends Controller
{
    public function __invoke(Request $request, GlobalSearchService $search): JsonResponse
    {
        $data = $request->validate(['query' => ['required', 'string', 'min:2', 'max:60']]);

        return response()->json([
            'data' => $search->search(
                $request->user(),
                trim($data['query']),
                $this->activeBranchOrNull($request),
                $this->activeSchoolScopeId($request),
            ),
        ]);
    }
}

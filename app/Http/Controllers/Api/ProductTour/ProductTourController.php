<?php

namespace App\Http\Controllers\Api\ProductTour;

use App\Http\Controllers\Controller;
use App\Services\ProductTour\ProductTourService;
use App\Services\ProductTour\FeatureDiscoveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProductTourController extends Controller
{
    protected ProductTourService $tourService;

    protected FeatureDiscoveryService $featureDiscovery;

    public function __construct(ProductTourService $tourService, FeatureDiscoveryService $featureDiscovery)
    {
        $this->tourService = $tourService;
        $this->featureDiscovery = $featureDiscovery;
    }

    public function status(Request $request): JsonResponse
    {
        $panel = $request->input('panel', 'admin');
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => $this->tourService->status($user, $panel),
        ]);
    }

    public function start(Request $request): JsonResponse
    {
        $request->validate([
            'tour_key' => ['required', 'string'],
            'panel' => ['sometimes', 'string', 'in:admin'],
        ]);

        $user = $request->user();
        $panel = $request->input('panel', 'admin');
        $tourKey = $request->input('tour_key');

        $result = $this->tourService->start($tourKey, $user, $panel);

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    public function complete(Request $request): JsonResponse
    {
        $request->validate([
            'tour_key' => ['required', 'string'],
            'panel' => ['sometimes', 'string', 'in:admin'],
            'metadata' => ['sometimes', 'array'],
        ]);

        $user = $request->user();
        $panel = $request->input('panel', 'admin');
        $tourKey = $request->input('tour_key');
        $metadata = $request->input('metadata', []);

        $this->tourService->complete($tourKey, $user, $panel, $metadata);

        return response()->json([
            'success' => true,
            'message' => 'Tour completed.',
        ]);
    }

    public function skip(Request $request): JsonResponse
    {
        $request->validate([
            'tour_key' => ['required', 'string'],
            'panel' => ['sometimes', 'string', 'in:admin'],
        ]);

        $user = $request->user();
        $panel = $request->input('panel', 'admin');
        $tourKey = $request->input('tour_key');

        $this->tourService->skip($tourKey, $user, $panel);

        return response()->json([
            'success' => true,
            'message' => 'Tour skipped.',
        ]);
    }

    public function restart(Request $request): JsonResponse
    {
        $request->validate([
            'tour_key' => ['required', 'string'],
            'panel' => ['sometimes', 'string', 'in:admin'],
        ]);

        $user = $request->user();
        $panel = $request->input('panel', 'admin');
        $tourKey = $request->input('tour_key');

        $this->tourService->restart($tourKey, $user, $panel);

        return response()->json([
            'success' => true,
            'message' => 'Tour restarted.',
        ]);
    }

    public function progress(Request $request): JsonResponse
    {
        $request->validate([
            'tour_key' => ['required', 'string'],
            'step' => ['required', 'integer', 'min:0'],
            'percentage' => ['required', 'integer', 'min:0', 'max:100'],
            'panel' => ['sometimes', 'string', 'in:admin'],
        ]);

        $user = $request->user();
        $panel = $request->input('panel', 'admin');
        $tourKey = $request->input('tour_key');
        $step = $request->integer('step');
        $percentage = $request->integer('percentage');

        $this->tourService->saveProgress($tourKey, $step, $percentage, $user, $panel);

        return response()->json([
            'success' => true,
            'message' => 'Progress saved.',
        ]);
    }

    public function featureDiscovery(Request $request): JsonResponse
    {
        $panel = $request->input('panel', 'admin');
        $user = $request->user();

        $discoveries = $this->featureDiscovery->discover($user, $panel);

        return response()->json([
            'success' => true,
            'data' => $discoveries,
        ]);
    }

    public function dismissHint(Request $request): JsonResponse
    {
        $request->validate([
            'tour_key' => ['required', 'string'],
        ]);

        $user = $request->user();
        $this->featureDiscovery->dismissHint($user, $request->input('tour_key'));

        return response()->json([
            'success' => true,
            'message' => 'Hint dismissed.',
        ]);
    }
}

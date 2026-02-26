<?php

namespace App\Http\Controllers;

use App\Services\ProductTourService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductTourController extends Controller
{
    protected $productTourService;

    public function __construct(ProductTourService $productTourService)
    {
        $this->productTourService = $productTourService;
    }

    public function restart(Request $request)
    {
        $user = Auth::user();
        $role = $user->roles->first()->name ?? 'staff';
        
        $this->productTourService->restartTour($user, $role);
        
        return response()->json([
            'success' => true,
            'message' => 'Tour restarted successfully'
        ]);
    }

    public function complete(Request $request)
    {
        $user = Auth::user();
        $role = $user->roles->first()->name ?? 'staff';
        
        $this->productTourService->markTourCompleted($user, $role);
        
        return response()->json([
            'success' => true,
            'message' => 'Tour marked as completed'
        ]);
    }

    public function status(Request $request)
    {
        $user = Auth::user();
        $role = $user->roles->first()->name ?? 'staff';
        
        $shouldShow = $this->productTourService->shouldShowTour($user, $role);
        $completedTours = $this->productTourService->getAllCompletedTours($user);
        
        return response()->json([
            'should_show_tour' => $shouldShow,
            'completed_tours' => $completedTours,
            'current_role' => $role,
        ]);
    }
}

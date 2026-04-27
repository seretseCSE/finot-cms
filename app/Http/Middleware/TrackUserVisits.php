<?php

namespace App\Http\Middleware;

use App\Services\ProductTourService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TrackUserVisits
{
    protected $productTourService;

    public function __construct(ProductTourService $productTourService)
    {
        $this->productTourService = $productTourService;
    }

    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            $user = Auth::user();
            $this->productTourService->incrementVisitCount($user);
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class SessionController extends Controller
{
    /**
     * Extend the current session.
     */
    public function extendSession(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        // Update last activity timestamp
        Session::put('last_activity', now()->toDateTimeString());

        return response()->json([
            'success' => true,
            'message' => 'Session extended',
            'last_activity' => Session::get('last_activity'),
            'expires_at' => now()->addMinutes(config('session.lifetime', 30))->toDateTimeString(),
        ]);
    }

    /**
     * Get current session status.
     */
    public function getSessionStatus(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $lastActivity = Session::get('last_activity', now());
        $lastActivityTime = is_string($lastActivity) ? new \DateTime($lastActivity) : $lastActivity;
        $now = now();
        
        $sessionLifetime = config('session.lifetime', 30);
        $inactiveMinutes = $now->diffInMinutes($lastActivityTime);
        $remainingMinutes = max(0, $sessionLifetime - $inactiveMinutes);

        return response()->json([
            'authenticated' => true,
            'user_id' => Auth::id(),
            'last_activity' => $lastActivityTime->toDateTimeString(),
            'session_lifetime' => $sessionLifetime,
            'inactive_minutes' => $inactiveMinutes,
            'remaining_minutes' => $remainingMinutes,
            'expires_at' => $now->addMinutes($remainingMinutes)->toDateTimeString(),
            'will_timeout_soon' => $remainingMinutes <= 5,
        ]);
    }
}

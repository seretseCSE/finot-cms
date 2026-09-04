<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks authenticated requests from users whose global account status is not
 * active (inactive or banned). This enforces platform-wide deactivation on
 * every request — not just at login — so a status change takes effect
 * immediately for any live session.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->isActive()) {
            return response()->json([
                'message' => 'Your account has been deactivated. Please contact support for assistance.',
                'code' => $user->status->deniedCode(),
            ], 403);
        }

        return $next($request);
    }
}

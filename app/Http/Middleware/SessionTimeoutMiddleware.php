<?php

namespace App\Http\Middleware;

use App\Models\UserSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SessionTimeoutMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check() || $request->hasHeader('X-Livewire')) {
            return $next($request);
        }

        if ($request->routeIs([
            'login',
            'login.submit',
            'logout',
            'change-initial-password',
            'change-initial-password.submit',
        ])) {
            return $next($request);
        }

        $sessionToken = session('session_token');

        if ($sessionToken) {
            $userSession = UserSession::where('user_id', Auth::id())
                ->where('session_token', $sessionToken)
                ->first();

            if ($userSession) {
                $userSession->updateLastActivity();
            } else {
                UserSession::query()->updateOrCreate(
                    ['session_token' => $sessionToken],
                    [
                        'user_id' => Auth::id(),
                        'device_info' => $request->userAgent(),
                        'ip_address' => $request->ip(),
                        'last_activity' => now(),
                    ]
                );
            }
        }

        return $next($request);
    }
}

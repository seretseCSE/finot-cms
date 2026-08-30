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

        $sessionToken = session('session_token');

        if ($sessionToken) {
            $userSession = UserSession::where('user_id', Auth::id())
                ->where('session_token', $sessionToken)
                ->first();

            if ($userSession) {
                $userSession->updateLastActivity();
            } else {
                Auth::logout();
                session()->flash('session_expired', 'Your session has expired. Please login again.');
                return redirect()->route('login');
            }
        }

        return $next($request);
    }
}

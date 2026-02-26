<?php

namespace App\Http\Middleware;

use App\Models\UserSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SessionTimeoutMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Clean up expired sessions (older than 30 minutes)
        $this->cleanupExpiredSessions();
        
        // Update current session activity if user is authenticated
        if (Auth::check()) {
            $sessionToken = session('session_token');
            
            if ($sessionToken) {
                $userSession = UserSession::where('user_id', Auth::id())
                    ->where('session_token', $sessionToken)
                    ->first();
                
                if ($userSession) {
                    $userSession->updateLastActivity();
                } else {
                    // Session not found in database, might be expired
                    Auth::logout();
                    session()->flash('session_expired', 'Your session has expired. Please login again.');
                    return redirect()->route('login');
                }
            }
        }
        
        return $next($request);
    }
    
    /**
     * Clean up expired sessions (older than 30 minutes).
     */
    private function cleanupExpiredSessions(): void
    {
        UserSession::where('last_activity', '<', now()->subMinutes(30))
            ->delete();
    }
}

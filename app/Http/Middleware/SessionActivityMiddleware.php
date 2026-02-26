<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class SessionActivityMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip for API endpoints and unauthenticated users
        if ($this->shouldSkip($request)) {
            return $next($request);
        }

        // Check if user is authenticated
        if (Auth::check()) {
            $user = Auth::user();
            $now = now();

            // Get last activity timestamp
            $lastActivity = Session::get('last_activity', $now);
            $lastActivityTime = is_string($lastActivity) ? new \DateTime($lastActivity) : $lastActivity;

            // Check if session has expired (30 minutes of inactivity)
            $sessionLifetime = config('session.lifetime', 30); // minutes
            $inactiveMinutes = $now->diffInMinutes($lastActivityTime);

            if ($inactiveMinutes >= $sessionLifetime) {
                // Logout user and clear session
                Auth::logout();
                Session::flush();
                
                // Add flash message for session expired
                Session::flash('session_expired', 'Your session has expired due to inactivity. Please log in again.');
                
                // Redirect to login page
                return redirect()->route('filament.admin.auth.login');
            }

            // Update last activity timestamp
            Session::put('last_activity', $now->toDateTimeString());
        }

        return $next($request);
    }

    /**
     * Determine if the middleware should be skipped for this request.
     */
    protected function shouldSkip(Request $request): bool
    {
        // Skip for API endpoints (PWA background sync)
        if ($request->is('api/*') || $request->isJson()) {
            return true;
        }

        // Skip for unauthenticated users
        if (!Auth::check()) {
            return true;
        }

        // Skip for logout and login routes to avoid redirect loops
        $skipRoutes = [
            'filament.admin.auth.login',
            'filament.admin.logout',
            'filament.admin.auth.logout',
        ];

        $currentRoute = $request->route();
        if ($currentRoute && in_array($currentRoute->getName(), $skipRoutes)) {
            return true;
        }

        return false;
    }
}

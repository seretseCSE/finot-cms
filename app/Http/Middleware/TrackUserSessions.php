<?php

namespace App\Http\Middleware;

use App\Models\UserSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class TrackUserSessions
{
    /**
     * Handle an incoming request.
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
            $sessionToken = session()->getId();

            // Store session token in session for current session identification
            session(['session_token' => $sessionToken]);

            // Get or create user session record
            $userSession = UserSession::where('session_token', $sessionToken)
                ->where('user_id', $user->id)
                ->first();

            if (! $userSession) {
                // Check if token already exists globally (prevents unique constraint violation)
                $existingByToken = UserSession::where('session_token', $sessionToken)->first();
                if ($existingByToken) {
                    $existingByToken->update([
                        'user_id' => $user->id,
                        'device_info' => $this->getDeviceInfo($request),
                        'ip_address' => $request->ip(),
                        'last_activity' => now(),
                    ]);
                } else {
                    // Enforce maximum 3 active sessions
                    if ($user->hasMaxSessions()) {
                        Auth::logout();
                        $request->session()->invalidate();
                        $request->session()->regenerateToken();

                        return redirect()->route('filament.admin.auth.login')
                            ->withErrors([
                                'max_sessions' => 'Maximum 3 active devices reached. Please revoke an existing session from your profile before logging in on a new device.',
                            ]);
                    }

                    // Create new session record
                    UserSession::create([
                        'user_id' => $user->id,
                        'session_token' => $sessionToken,
                        'device_info' => $this->getDeviceInfo($request),
                        'ip_address' => $request->ip(),
                        'last_activity' => now(),
                    ]);
                }
            } else {
                // Update existing session
                $userSession->update([
                    'last_activity' => now(),
                ]);
            }
        }

        return $next($request);
    }

    /**
     * Get device information from request headers.
     */
    protected function getDeviceInfo(Request $request): string
    {
        $userAgent = $request->header('User-Agent', 'Unknown');

        // Extract browser and OS info
        $deviceInfo = [];

        // Browser detection
        if (strpos($userAgent, 'Chrome') !== false) {
            $deviceInfo[] = 'Chrome';
        } elseif (strpos($userAgent, 'Firefox') !== false) {
            $deviceInfo[] = 'Firefox';
        } elseif (strpos($userAgent, 'Safari') !== false) {
            $deviceInfo[] = 'Safari';
        } elseif (strpos($userAgent, 'Edge') !== false) {
            $deviceInfo[] = 'Edge';
        } else {
            $deviceInfo[] = 'Unknown Browser';
        }

        // OS detection
        if (strpos($userAgent, 'Windows') !== false) {
            $deviceInfo[] = 'Windows';
        } elseif (strpos($userAgent, 'Mac') !== false) {
            $deviceInfo[] = 'macOS';
        } elseif (strpos($userAgent, 'Linux') !== false) {
            $deviceInfo[] = 'Linux';
        } elseif (strpos($userAgent, 'Android') !== false) {
            $deviceInfo[] = 'Android';
        } elseif (strpos($userAgent, 'iOS') !== false) {
            $deviceInfo[] = 'iOS';
        } else {
            $deviceInfo[] = 'Unknown OS';
        }

        return implode(' - ', $deviceInfo);
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
        if (! Auth::check()) {
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

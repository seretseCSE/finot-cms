<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ForcePasswordChange
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only check for authenticated users
        if (Auth::check()) {
            $user = Auth::user();

            // Check if user needs to change temporary password
            if (!$user->temp_password_changed) {
                // Allow access to password change page and logout
                $allowedRoutes = [
                    'filament.admin.pages.change-password',
                    'filament.admin.logout',
                    'filament.admin.auth.logout',
                    'filament.admin.auth.login',
                ];

                // Check if current route is allowed
                $currentRoute = $request->route() ? $request->route()->getName() : null;

                if (!in_array($currentRoute, $allowedRoutes)) {
                    // Store intended URL for redirect after password change
                    session(['url.intended' => $request->fullUrl()]);

                    // Redirect to password change page
                    return redirect()->route('filament.admin.pages.change-password');
                }
            }
        }

        return $next($request);
    }
}

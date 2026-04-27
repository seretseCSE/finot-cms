<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

class SetLocaleMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        // Check if user is authenticated
        if (Auth::check()) {
            $user = Auth::user();
            $locale = $user->language_preference ?? 'en';
        } else {
            // Try to get locale from cookie for guests
            $locale = $request->cookie('locale') ?? 'en';
        }

        // Set the application locale
        App::setLocale($locale);

        // Store locale in session for future requests
        session(['locale' => $locale]);

        return $next($request);
    }
}

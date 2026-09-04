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
        $defaultLocale = config('app.locale', 'am');

        // Check if user is authenticated
        if (Auth::check()) {
            $user = Auth::user();
            $locale = $user->language_preference ?: $defaultLocale;
        } else {
            // Explicit choice is stored in the locale cookie; otherwise Amharic
            $locale = $request->cookie('locale') ?? $defaultLocale;
        }

        if (! in_array($locale, ['en', 'am'], true)) {
            $locale = $defaultLocale;
        }

        // Set the application locale
        App::setLocale($locale);

        // Avoid dirtying database-backed sessions when the locale is unchanged.
        if (session('locale') !== $locale) {
            session(['locale' => $locale]);
        }

        return $next($request);
    }
}

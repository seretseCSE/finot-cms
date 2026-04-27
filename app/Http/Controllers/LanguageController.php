<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class LanguageController extends Controller
{
    public function switch($locale)
    {
        // Validate the locale
        if (!in_array($locale, ['en', 'am'])) {
            abort(404);
        }

        // Store the locale in session
        Session::put('locale', $locale);

        // Set the locale for current request
        App::setLocale($locale);

        // If authenticated, persist to user profile
        if (Auth::check()) {
            $user = Auth::user();
            if ($user->language_preference !== $locale) {
                $user->language_preference = $locale;
                $user->save();
            }
        }

        // Redirect back or to home
        return redirect()->back()->withCookie(cookie('locale', $locale, 60 * 24 * 365));
    }
}

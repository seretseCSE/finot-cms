<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
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

        // Redirect back or to home
        return redirect()->back();
    }
}

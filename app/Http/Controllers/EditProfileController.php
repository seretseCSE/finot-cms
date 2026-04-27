<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

class EditProfileController extends Controller
{
    public function __invoke()
    {
        // Check if user is authenticated
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        // Redirect to the Filament EditProfile page
        return redirect()->route('filament.admin.pages.edit-profile');
    }
}

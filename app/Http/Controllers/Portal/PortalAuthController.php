<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PortalAuthController extends Controller
{
    public function showLogin(): RedirectResponse
    {
        return redirect()->route('login');
    }

    public function login(Request $request): RedirectResponse
    {
        return app(AuthController::class)->login($request);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}

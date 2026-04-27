<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Handle phone-based authentication.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'phone' => 'required|string',
            'password' => 'required|string',
        ]);

        $prefix = config('finot.phone_prefix', '+251');
        $fullPhone = str_starts_with($credentials['phone'], $prefix)
            ? $credentials['phone']
            : $prefix.$credentials['phone'];

        $user = User::where('phone', $fullPhone)->first();

        // Check if user exists and password is correct
        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            if ($user) {
                $user->incrementFailedAttempts();
            }

            return back()
                ->withErrors(['phone' => 'The provided credentials are incorrect.'])
                ->withInput();
        }

        // Check if account is currently locked
        if ($user->isCurrentlyLocked()) {
            return back()
                ->withErrors(['phone' => $user->getLockStatusMessage()])
                ->withInput();
        }

        // Check if user is active
        if (! $user->is_active) {
            return back()
                ->withErrors(['phone' => 'Your account is inactive.'])
                ->withInput();
        }

        // Check if temporary password needs changing
        if (! $user->temp_password_changed) {
            Auth::login($user);
            $request->session()->regenerate();

            return redirect('/change-initial-password');
        }

        // Successful login
        Auth::login($user);
        $user->resetFailedAttempts();
        $request->session()->regenerate();

        return redirect()->intended('/admin');
    }

    /**
     * Handle logout.
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    /**
     * Show the change initial password form.
     */
    public function showChangeInitialPassword()
    {
        return response('Change Initial Password');
    }

    /**
     * Process the initial password change.
     */
    public function changeInitialPassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = Auth::user();

        if (! $user) {
            return redirect('/login');
        }

        if (! Hash::check($request->current_password, $user->password)) {
            return back()
                ->withErrors(['current_password' => 'Current password is incorrect.'])
                ->withInput();
        }

        $user->updatePassword($request->password, 3);
        $user->update(['temp_password_changed' => true]);

        return redirect('/admin')->with('success', 'Password changed successfully.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Favorite;
use App\Models\User;
use App\Rules\PasswordHistoryRule;
use App\Rules\PasswordStrengthRule;
use App\Services\PhoneFormattingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLogin(): Response|RedirectResponse
    {
        if (Auth::check()) {
            return $this->redirectAfterLogin(Auth::user());
        }

        return $this->authPage('auth.login');
    }

    /**
     * Handle phone-based authentication.
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'phone' => 'required|string',
            'password' => 'required|string',
        ]);

        $fullPhone = PhoneFormattingService::normalizeForAuth($credentials['phone']);
        $user = $fullPhone ? User::query()->where('phone', $fullPhone)->first() : null;

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            if ($user) {
                $user->incrementFailedAttempts();
            }

            return back()
                ->withErrors(['phone' => 'The provided credentials are incorrect.'])
                ->withInput();
        }

        if ($user->isCurrentlyLocked()) {
            return back()
                ->withErrors(['phone' => $user->getLockoutMessage()])
                ->withInput();
        }

        if (! $user->is_active) {
            return back()
                ->withErrors(['phone' => 'Your account is inactive.'])
                ->withInput();
        }

        Auth::login($user);
        $user->resetFailedAttempts();
        $request->session()->forget('session_token');
        $request->session()->regenerate();
        $this->mergeGuestFavorites($request, $user);

        return $this->redirectAfterLogin($user);
    }

    public function redirectAfterLogin(User $user): RedirectResponse
    {
        $url = $user->postLoginUrl();

        if ($user->isStudentOnly()) {
            $redirect = redirect()->to($url);
            if (! $user->temp_password_changed) {
                $redirect->with('info', 'Please update your password.');
            }

            return $redirect;
        }

        if (! $user->temp_password_changed) {
            return redirect()->to($url);
        }

        return redirect()->intended($url);
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
    public function showChangeInitialPassword(): Response|RedirectResponse
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return redirect()->route('login');
        }

        if ($user->isStudentOnly()) {
            return redirect()->to($user->postLoginUrl());
        }

        if ($user->temp_password_changed) {
            return redirect()->to($user->postLoginUrl());
        }

        return $this->authPage('auth.change-initial-password');
    }

    /**
     * Process the initial password change.
     */
    public function changeInitialPassword(Request $request): RedirectResponse
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return redirect()->route('login');
        }

        $request->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => [
                'required',
                'string',
                'confirmed',
                new PasswordStrengthRule(),
                new PasswordHistoryRule($user, 3),
            ],
        ], [
            'current_password.current_password' => 'Current password is incorrect.',
            'password.confirmed' => 'Password confirmation does not match.',
        ]);

        $user->updatePassword($request->password, 3);
        $user = $user->fresh();
        Auth::login($user);
        $user->persistAuthPasswordHashInSession();
        $request->session()->forget('url.intended');

        return redirect()->to($user->postLoginUrl())->with('success', 'Password changed successfully.');
    }

    /**
     * Merge guest cookie favorites into the user's database favorites on login.
     * DB wins: only inserts favorites not already present for this user.
     */
    /**
     * Auth forms include a CSRF token. Browsers and the PWA must not cache them.
     */
    private function authPage(string $view): Response
    {
        return response()
            ->view($view)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    private function mergeGuestFavorites(Request $request, User $user): void
    {
        $types = [
            'App\Models\LibraryResource',
            'App\Models\Course',
        ];

        foreach ($types as $type) {
            $cookieKey = 'favorites_'.str_replace('\\', '_', $type);
            $guestIds = json_decode($request->cookie($cookieKey, '[]'), true) ?? [];

            if (empty($guestIds)) {
                continue;
            }

            $existingIds = Favorite::where('user_id', $user->id)
                ->where('favorable_type', $type)
                ->whereIn('favorable_id', $guestIds)
                ->pluck('favorable_id')
                ->toArray();

            $newIds = array_diff($guestIds, $existingIds);

            foreach ($newIds as $id) {
                Favorite::create([
                    'user_id' => $user->id,
                    'favorable_type' => $type,
                    'favorable_id' => (int) $id,
                ]);
            }

            cookie()->queue($cookieKey, '', -1);
        }
    }
}

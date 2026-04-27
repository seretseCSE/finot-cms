<?php

namespace App\Http\Controllers;

use App\Services\PhoneAuthService;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CustomLoginController
{
    protected PhoneAuthService $phoneAuthService;

    public function __construct(PhoneAuthService $phoneAuthService)
    {
        $this->phoneAuthService = $phoneAuthService;
    }

    /**
     * Handle the incoming login request.
     */
    public function login(Request $request): LoginResponse
    {
        $credentials = $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = $this->phoneAuthService->authenticate(
            $credentials['login'],
            $credentials['password']
        );

        if (!$user) {
            throw ValidationException::withMessages([
                'login' => __('The provided credentials are incorrect.'),
            ]);
        }

        Auth::login($user);

        $request->session()->regenerate();

        return app(LoginResponse::class);
    }
}

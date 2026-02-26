<?php

namespace App\Providers;

use App\Services\PhoneAuthService;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Illuminate\Http\Request;

class CustomFilamentAuthProvider
{
    protected PhoneAuthService $phoneAuthService;

    public function __construct(PhoneAuthService $phoneAuthService)
    {
        $this->phoneAuthService = $phoneAuthService;
    }

    /**
     * Custom authentication logic for Filament.
     */
    public function authenticate(Request $request): ?LoginResponse
    {
        $credentials = $request->only(['login', 'password']);
        
        // Use phoneAuthService to authenticate
        $user = $this->phoneAuthService->authenticate(
            $credentials['login'], 
            $credentials['password']
        );

        if (!$user) {
            return null;
        }

        // Log the user in
        auth()->login($user);

        // Return successful login response
        return app(LoginResponse::class);
    }
}

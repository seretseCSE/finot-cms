<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

class PhoneAuthService
{
    /**
     * Authenticate user by phone or email.
     */
    public function authenticate(string $identifier, string $password): ?User
    {
        // Validate input
        $validator = Validator::make([
            'identifier' => $identifier,
            'password' => $password,
        ], [
            'identifier' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return null;
        }

        // Find user by phone or email
        $user = User::where(function ($query) use ($identifier) {
            $query->where('phone', $identifier)
                  ->orWhere('email', $identifier);
        })->first();

        if (!$user) {
            return null;
        }

        // Check password
        if (!Hash::check($password, $user->password)) {
            return null;
        }

        return $user;
    }

    /**
     * Find user by phone number or email.
     */
    public function findUser(string $identifier): ?User
    {
        return User::where(function ($query) use ($identifier) {
            $query->where('phone', $identifier)
                  ->orWhere('email', $identifier);
        })->first();
    }

    /**
     * Check if identifier is phone number.
     */
    public function isPhoneNumber(string $identifier): bool
    {
        // Check if it starts with + and contains only digits after that
        return preg_match('/^\+\d+$/', $identifier);
    }

    /**
     * Format phone number for display.
     */
    public function formatPhone(string $phone): string
    {
        // Remove any non-digit characters except +
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Ensure it starts with +
        if (!str_starts_with($phone, '+')) {
            $phone = '+' . $phone;
        }
        
        return $phone;
    }
}

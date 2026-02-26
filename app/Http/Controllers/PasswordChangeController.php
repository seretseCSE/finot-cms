<?php

namespace App\Http\Controllers;

use App\Rules\PasswordHistoryRule;
use App\Rules\PasswordStrengthRule;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class PasswordChangeController extends Controller
{
    /**
     * Handle password change request.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => [
                'required',
                'string',
                'min:8',
                new PasswordStrengthRule(),
                new PasswordHistoryRule($user, 3),
            ],
            'new_password_confirmation' => 'required|string|same:new_password',
        ], [
            'new_password.required' => 'New password is required.',
            'new_password.min' => 'New password must be at least 8 characters.',
            'new_password_confirmation.required' => 'Password confirmation is required.',
            'new_password_confirmation.same' => 'Password confirmation does not match.',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Verify current password
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['errors' => ['current_password' => 'Current password is incorrect.']], 422);
        }

        // Update password with history tracking
        $user->updatePassword($request->new_password, 3);

        return response()->json(['success' => 'Password changed successfully.']);
    }

    /**
     * Get password requirements for frontend validation.
     */
    public function getPasswordRequirements(): JsonResponse
    {
        return response()->json([
            'min_length' => 8,
            'require_uppercase' => true,
            'require_lowercase' => true,
            'require_number' => true,
            'max_history' => 3,
        ]);
    }
}

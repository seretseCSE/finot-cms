<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Hash;

class PasswordStrengthRule implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) {
            return;
        }

        $errors = [];

        // Minimum 8 characters
        if (strlen($value) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        }

        // At least one uppercase letter
        if (!preg_match('/[A-Z]/', $value)) {
            $errors[] = 'Password must contain at least one uppercase letter (A-Z).';
        }

        // At least one lowercase letter
        if (!preg_match('/[a-z]/', $value)) {
            $errors[] = 'Password must contain at least one lowercase letter (a-z).';
        }

        // At least one number
        if (!preg_match('/[0-9]/', $value)) {
            $errors[] = 'Password must contain at least one number (0-9).';
        }

        // If there are any errors, fail with all messages
        if (!empty($errors)) {
            $fail(implode(' ', $errors));
        }
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        return 'Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, and one number.';
    }
}

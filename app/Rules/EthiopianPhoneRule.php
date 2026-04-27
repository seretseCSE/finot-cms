<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class EthiopianPhoneRule implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) {
            return;
        }

        $prefix = config('finot.phone_prefix', '+251');

        // Ethiopian mobile number format: prefix followed by 9 digits
        // Valid prefixes: 9, 7 (for mobile numbers)
        if (!preg_match('/^' . preg_quote($prefix, '/') . '[97][0-9]{8}$/', $value)) {
            $fail('The :attribute must be a valid Ethiopian mobile number (e.g., ' . $prefix . '912345678).');
        }
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        return 'The :attribute must be a valid Ethiopian mobile number (e.g., ' . config('finot.phone_prefix', '+251') . '912345678).';
    }
}

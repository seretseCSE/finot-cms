<?php

namespace App\Rules;

use App\Support\PhoneNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The one phone-number rule for the whole app. Accepts every Ethio Telecom /
 * Safaricom Ethiopia shape (local `09…`/`07…` and international `+2519…`/`+2517…`)
 * — see App\Support\PhoneNumber. Pair with `nullable` where the field is optional.
 */
class EthiopianPhone implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! PhoneNumber::isValid($value)) {
            $fail(PhoneNumber::allowSafaricom() ? 'phone.invalid' : 'phone.invalid_ethio_only')->translate();
        }
    }
}

<?php

namespace App\Rules;

use App\Support\PhoneNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Ethiopian mobile OR geographic landline — for school official contact lines
 * (e.g. `+251 11 662 98 00`). Account / SMS fields must keep using EthiopianPhone.
 */
class EthiopianContactPhone implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! PhoneNumber::isValidContact($value)) {
            $fail(PhoneNumber::allowSafaricom() ? 'phone.invalid_contact' : 'phone.invalid_contact_ethio_only')->translate();
        }
    }
}

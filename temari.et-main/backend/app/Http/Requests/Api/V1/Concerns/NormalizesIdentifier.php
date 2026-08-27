<?php

namespace App\Http\Requests\Api\V1\Concerns;

use App\Support\PhoneNumber;
use App\Support\PublicId;

/**
 * Canonicalises the auth `identifier` field before validation: a phone number
 * becomes the local `09…`/`07…` form, anything else is uppercased as a
 * candidate Temari student ID. Which one it actually is stays a server-side
 * decision (App\Support\LoginIdentifier).
 */
trait NormalizesIdentifier
{
    protected function prepareForValidation(): void
    {
        $raw = $this->input('identifier');

        if (is_string($raw) && trim($raw) !== '') {
            $this->merge([
                'identifier' => PhoneNumber::normalize($raw) ?? PublicId::normalize($raw),
            ]);
        }
    }
}

<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\NormalizesIdentifier;
use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    use NormalizesIdentifier;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'max:30'],
            'otp' => ['required', 'string', 'digits:6'],
            'password' => ['required', 'string', 'min:4', 'confirmed'],
        ];
    }
}

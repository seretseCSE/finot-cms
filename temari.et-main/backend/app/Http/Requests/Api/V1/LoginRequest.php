<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\NormalizesIdentifier;
use Illuminate\Foundation\Http\FormRequest;

/**
 * One credential field for every account type: `identifier` is a phone number
 * or a Temari student ID (the ID-login lane for students without their own
 * phone). Detection is server-side — the client never says which.
 */
class LoginRequest extends FormRequest
{
    use NormalizesIdentifier;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string'],
        ];
    }
}

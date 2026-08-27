<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\GuardianRules;
use App\Http\Requests\Api\V1\Concerns\NormalizesPhoneFields;
use Illuminate\Foundation\Http\FormRequest;

class StoreGuardianRequest extends FormRequest
{
    use GuardianRules, NormalizesPhoneFields;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return self::guardianRowRules();
    }

    /**
     * @return list<string>
     */
    protected function phoneFields(): array
    {
        return ['phone', 'secondary_phone'];
    }
}

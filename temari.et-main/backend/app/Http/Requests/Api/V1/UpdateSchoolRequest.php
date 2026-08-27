<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\NormalizesPhoneFields;
use App\Rules\EthiopianContactPhone;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSchoolRequest extends FormRequest
{
    use NormalizesPhoneFields;

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
            'name' => ['sometimes', 'string', 'max:255'],
            // Official line may be a geographic office landline.
            'phone' => ['nullable', 'string', 'max:20', new EthiopianContactPhone],
            'address' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return list<string>
     */
    protected function contactPhoneFields(): array
    {
        return ['phone'];
    }
}

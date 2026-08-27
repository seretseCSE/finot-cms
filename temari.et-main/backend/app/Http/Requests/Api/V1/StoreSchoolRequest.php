<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\NormalizesPhoneFields;
use App\Rules\EthiopianContactPhone;
use App\Rules\EthiopianPhone;
use Illuminate\Foundation\Http\FormRequest;

class StoreSchoolRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            // Official contact line for document mastheads (branch values win).
            // May be a geographic office landline (e.g. +251 11 662 98 00).
            'phone' => ['nullable', 'string', 'max:20', new EthiopianContactPhone],
            'address' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],

            'principal_name' => ['required', 'string', 'max:255'],
            'principal_phone' => ['required', 'string', 'max:20', new EthiopianPhone],

            'technical_name' => ['nullable', 'required_with:technical_phone', 'string', 'max:255'],
            'technical_phone' => ['nullable', 'required_with:technical_name', 'string', 'max:20', new EthiopianPhone],
        ];
    }

    /**
     * @return list<string>
     */
    protected function phoneFields(): array
    {
        return ['principal_phone', 'technical_phone'];
    }

    /**
     * @return list<string>
     */
    protected function contactPhoneFields(): array
    {
        return ['phone'];
    }
}

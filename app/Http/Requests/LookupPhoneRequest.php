<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LookupPhoneRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     *
     * Normalizes phone numbers by stripping leading zeros and prepending the
     * configured country prefix.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $prefix = config('finot.phone_prefix', '+251');
            $phone = preg_replace('/^0/', '', (string) $this->input('phone'));

            $this->merge([
                'phone' => $phone,
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string'],
            'tour_id' => ['required', 'integer', 'exists:tours,id'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Models\Tour;
use App\Models\TourPassenger;
use Illuminate\Foundation\Http\FormRequest;

class TourRegistrationRequest extends FormRequest
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
        $tourId = $this->route('id');

        return [
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => [
                'required',
                'string',
                'regex:/^[0-9]{9}$/',
                function (string $attribute, mixed $value, \Closure $fail) use ($tourId) {
                    $fullPhone = config('finot.phone_prefix', '+251').$value;

                    if (TourPassenger::where('tour_id', $tourId)->where('phone', $fullPhone)->exists()) {
                        $fail('This phone number is already registered for this tour');
                    }
                },
            ],
            'passenger_count' => [
                'required',
                'integer',
                'min:1',
                'max:20',
                function (string $attribute, mixed $value, \Closure $fail) use ($tourId) {
                    $tour = Tour::find($tourId);

                    if (! $tour || ! $tour->max_capacity) {
                        return;
                    }

                    $currentConfirmed = $tour->confirmedPassengers->sum('passenger_count');

                    if ($currentConfirmed + (int) $value > $tour->max_capacity) {
                        $fail('Not enough capacity available');
                    }
                },
            ],
            'passenger_names' => ['nullable', 'array'],
            'passenger_names.*' => ['required_with:passenger_names', 'string', 'max:255'],
            'receipt_image' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'honeypot' => ['nullable', 'string', 'max:0'], // Bot prevention
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'phone.unique' => 'This phone number is already registered for this tour',
            'passenger_count.min' => 'At least one passenger is required',
            'passenger_count.max' => 'Maximum 20 passengers allowed',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'full_name' => 'full name',
            'passenger_count' => 'number of passengers',
            'receipt_image' => 'receipt image',
        ];
    }

    /**
     * Configure the validator instance.
     *
     * Adds a custom honeypot check after validation.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator) {
            if (! empty($this->input('honeypot'))) {
                $validator->errors()->add('honeypot', 'Invalid submission');
            }
        });
    }

    /**
     * Handle a passed validation attempt.
     *
     * Returns the full phone number with prefix applied.
     */
    public function getFullPhone(): string
    {
        return config('finot.phone_prefix', '+251').$this->validated('phone');
    }
}

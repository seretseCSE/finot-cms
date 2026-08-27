<?php

namespace App\Http\Requests\Api\V1\Concerns;

use App\Enums\Gender;
use App\Enums\HealthSeverity;
use App\Rules\BirthDate;
use App\Rules\EthiopianPhone;
use App\Support\Languages;
use Illuminate\Validation\Rule;

/**
 * The student profile fields shared by store + update. Everything beyond the
 * name/gender essentials is optional — registration is progressive-disclosure
 * by design (a rural school may capture names only; a private school the full
 * profile).
 */
trait StudentProfileRules
{
    public const array BLOOD_TYPES = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

    /**
     * @return array<string, mixed>
     */
    protected function profileRules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'father_name' => ['required', 'string', 'max:255'],
            'grandfather_name' => ['nullable', 'string', 'max:255'],
            'mother_name' => ['nullable', 'string', 'max:255'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'date_of_birth' => ['nullable', 'date', new BirthDate(minAgeYears: 1)],
            'fayda_id' => ['nullable', 'string', 'max:50'],

            // Contact + civil status (older/adult students may have their own).
            'primary_phone' => ['nullable', 'string', 'max:20', new EthiopianPhone],
            'email' => ['nullable', 'email', 'max:255'],
            'citizenship' => ['nullable', 'string', 'max:100'],
            'marital_status' => ['nullable', Rule::in(['single', 'married', 'divorced', 'widowed'])],

            // Home languages — codes from the platform catalog, Amharic default.
            'languages' => ['sometimes', 'array', 'max:10'],
            'languages.*' => ['string', Rule::in(Languages::codes())],

            // Health profile loose ends; the condition list is a child array.
            'blood_type' => ['nullable', Rule::in(self::BLOOD_TYPES)],
            'health_notes' => ['nullable', 'string', 'max:2000'],
            'health_conditions' => ['sometimes', 'array', 'max:20'],
            'health_conditions.*.health_condition_id' => ['required', 'integer', Rule::exists('health_conditions', 'id')],
            'health_conditions.*.severity' => ['nullable', Rule::enum(HealthSeverity::class)],
            'health_conditions.*.notes' => ['nullable', 'string', 'max:1000'],
            'health_conditions.*.medication' => ['nullable', 'string', 'max:255'],

            // Birthplace + current address (employee/branch field convention).
            'birth_country' => ['nullable', 'string', 'max:100'],
            'birth_state' => ['nullable', 'string', 'max:100'],
            'birth_city' => ['nullable', 'string', 'max:100'],
            'birth_sub_city' => ['nullable', 'string', 'max:100'],
            'birth_woreda' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'sub_city' => ['nullable', 'string', 'max:100'],
            'woreda' => ['nullable', 'string', 'max:100'],
            'house_no' => ['nullable', 'string', 'max:50'],

            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}

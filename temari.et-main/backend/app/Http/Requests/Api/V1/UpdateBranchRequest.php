<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\NormalizesPhoneFields;
use App\Models\SchoolProgram;
use App\Rules\EthiopianContactPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBranchRequest extends FormRequest
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
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('branches', 'code')->ignore($this->route('branch'))],
            'country' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'sub_city' => ['nullable', 'string', 'max:100'],
            'woreda' => ['nullable', 'string', 'max:100'],
            'house_no' => ['nullable', 'string', 'max:50'],
            // Office line — mobile OR geographic landline (e.g. 0111…), like
            // the school's own phone; only duplicates within the school block.
            'phone' => [
                'nullable', 'string', 'max:20', new EthiopianContactPhone(),
                Rule::unique('branches', 'phone')
                    ->where('school_id', $this->route('branch')?->school_id)
                    ->whereNull('deleted_at')
                    ->ignore($this->route('branch')),
            ],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'is_active' => ['sometimes', 'boolean'],

            // Education programs with their grade offering (the grade × program
            // matrix) — additive program sync (existing programs absent from
            // the payload keep their grades); removals of in-use cells reject.
            'programs' => ['sometimes', 'array'],
            'programs.*' => ['array'],
            'programs.*.type' => ['required', 'string', Rule::in(array_keys(SchoolProgram::CATALOG))],
            'programs.*.grade_level_ids' => ['nullable', 'array'],
            'programs.*.grade_level_ids.*' => ['integer', 'exists:grade_levels,id'],
        ];
    }

    /**
     * @return list<string>
     */
    protected function contactPhoneFields(): array
    {
        return ['phone'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.unique' => 'This phone number is already used by another branch of this school.',
        ];
    }
}

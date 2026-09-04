<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\NormalizesPhoneFields;
use App\Models\SchoolProgram;
use App\Rules\EthiopianContactPhone;
use App\Rules\EthiopianPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBranchRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:50', 'unique:branches,code'],
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
                    ->where('school_id', $this->route('school')?->id)
                    ->whereNull('deleted_at'),
            ],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'is_active' => ['sometimes', 'boolean'],

            // Education programs the branch runs, each with the grades it is
            // offered in (the grade × program matrix). Omitted grade_level_ids
            // = every grade; omitted programs entirely = Regular × all grades.
            'programs' => ['nullable', 'array'],
            'programs.*' => ['array'],
            'programs.*.type' => ['required', 'string', Rule::in(array_keys(SchoolProgram::CATALOG))],
            'programs.*.grade_level_ids' => ['nullable', 'array'],
            'programs.*.grade_level_ids.*' => ['integer', 'exists:grade_levels,id'],

            'director_name' => ['nullable', 'required_with:director_phone', 'string', 'max:255'],
            'director_phone' => ['nullable', 'required_with:director_name', 'string', 'max:20', new EthiopianPhone()],
        ];
    }

    /**
     * @return list<string>
     */
    protected function phoneFields(): array
    {
        return ['director_phone'];
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

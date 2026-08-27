<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEnrollmentRequest extends FormRequest
{
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
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            // Section optional at enrollment (assigned later); grade required
            // directly when no section carries it.
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'grade_level_id' => ['nullable', 'required_without:section_id', 'integer', 'exists:grade_levels,id'],
            'school_program_id' => ['nullable', 'integer', 'exists:school_programs,id'],
            'previous_school_id' => ['nullable', 'integer', Rule::exists('school_directory', 'id')->whereNull('deleted_at')],
            'enrolled_on' => ['nullable', 'date', 'after:2000-01-01', 'before:2100-01-01'],
        ];
    }
}

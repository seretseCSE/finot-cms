<?php

namespace App\Http\Requests\Api\V1;

use App\Support\JobTitles;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSectionRequest extends FormRequest
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
        $section = $this->route('section');

        return [
            'name' => [
                'required', 'string', 'max:50',
                Rule::unique('sections', 'name')
                    ->where('branch_id', $section?->branch_id)
                    ->where('grade_level_id', $section?->grade_level_id)
                    ->whereNull('deleted_at')
                    ->ignore($section?->id),
            ],
            'room_number' => ['nullable', 'string', 'max:30'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'academic_year_id' => [
                'nullable', 'integer',
                Rule::exists('academic_years', 'id')->where('branch_id', $section?->branch_id),
            ],
            'homeroom_employee_id' => [
                'nullable', 'integer',
                Rule::exists('employees', 'id')->where('branch_id', $section?->branch_id)->whereNull('deleted_at'),
                Rule::exists('employee_positions', 'employee_id')
                    ->where('job_title', JobTitles::TEACHER)
                    ->whereNull('ended_on')
                    ->whereNull('deleted_at'),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}

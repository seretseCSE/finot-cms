<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\ResolvesTargetBranchId;
use App\Support\GradeOffering;
use App\Support\JobTitles;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSectionRequest extends FormRequest
{
    use ResolvesTargetBranchId;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $branchId = $this->targetBranchId();

        return [
            'grade_level_id' => [
                'required', 'integer', 'exists:grade_levels,id',
                // The branch's grade × program offering gates section creation.
                function (string $attribute, mixed $value, Closure $fail) use ($branchId): void {
                    if ($branchId !== null && ! GradeOffering::isOffered($branchId, (int) $value)) {
                        $fail('This grade level is not offered at the selected branch.');
                    }
                },
            ],
            'name' => [
                'required', 'string', 'max:50',
                Rule::unique('sections', 'name')
                    ->where('branch_id', $branchId)
                    ->where('grade_level_id', $this->integer('grade_level_id'))
                    ->whereNull('deleted_at'),
            ],
            'room_number' => ['nullable', 'string', 'max:30'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'academic_year_id' => [
                'nullable', 'integer',
                Rule::exists('academic_years', 'id')->where('branch_id', $branchId),
            ],
            // The homeroom teacher must be branch staff holding an active
            // teacher position.
            'homeroom_employee_id' => [
                'nullable', 'integer',
                Rule::exists('employees', 'id')->where('branch_id', $branchId)->whereNull('deleted_at'),
                Rule::exists('employee_positions', 'employee_id')
                    ->where('job_title', JobTitles::TEACHER)
                    ->whereNull('ended_on')
                    ->whereNull('deleted_at'),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}

<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\AcademicYearStatus;
use App\Http\Requests\Api\V1\Concerns\ChecksAcademicYearOverlap;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateAcademicYearRequest extends FormRequest
{
    use ChecksAcademicYearOverlap;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $year = $this->route('academic_year');

        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('academic_years', 'name')
                    ->where('branch_id', $year?->branch_id)
                    ->whereNull('deleted_at')
                    ->ignore($year?->id),
            ],
            'starts_on' => ['required', 'date', 'after:2000-01-01', 'before:2100-01-01'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'status' => ['sometimes', Rule::enum(AcademicYearStatus::class)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $year = $this->route('academic_year');
                $overlap = $this->overlappingAcademicYear($year?->branch_id, $year?->id);
                if ($overlap) {
                    $validator->errors()->add('starts_on', $this->academicYearOverlapMessage($overlap));
                }
            },
        ];
    }
}

<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\AcademicYearStatus;
use App\Http\Requests\Api\V1\Concerns\ChecksAcademicYearOverlap;
use App\Http\Requests\Api\V1\Concerns\ResolvesTargetBranchId;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAcademicYearRequest extends FormRequest
{
    use ChecksAcademicYearOverlap;
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
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('academic_years', 'name')
                    ->where('branch_id', $branchId)
                    ->whereNull('deleted_at'),
            ],
            'starts_on' => ['required', 'date', 'after:2000-01-01', 'before:2100-01-01'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'status' => ['sometimes', Rule::enum(AcademicYearStatus::class)],
            // How many semesters/terms the year runs (auto-provisioned).
            'terms_count' => ['sometimes', 'integer', 'min:1', 'max:5'],
            // Opt-in ONLY: pre-build each provisioned semester's section/
            // subject/teacher grid from the curriculum + teacher capabilities.
            'auto_generate_assignments' => ['sometimes', 'boolean'],
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

                $overlap = $this->overlappingAcademicYear($this->targetBranchId());
                if ($overlap) {
                    $validator->errors()->add('starts_on', $this->academicYearOverlapMessage($overlap));
                }
            },
        ];
    }
}

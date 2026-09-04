<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\ChecksTermOverlap;
use App\Models\SchoolProgram;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateTermRequest extends FormRequest
{
    use ChecksTermOverlap;

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
            'name' => ['required', 'string', 'max:100'],
            // Program comes as a catalog type slug; the branch program row is
            // resolved (and created if missing) in the controller.
            'program_type' => ['nullable', Rule::in(array_keys(SchoolProgram::CATALOG))],
            'starts_on' => ['nullable', 'date', 'after:2000-01-01', 'before:2100-01-01'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'class_starts_at' => ['nullable', 'date_format:H:i'],
            'class_ends_at' => ['nullable', 'date_format:H:i', 'after:class_starts_at'],
            'period_minutes' => ['sometimes', 'integer', 'min:5', 'max:240'],
            'is_quarter' => ['sometimes', 'boolean'],
            // Which semester a quarter belongs to (yearly-roster grouping).
            'semester' => ['nullable', 'integer', Rule::in([1, 2])],
            // status + is_current change only through PATCH terms/{term}/status —
            // the transition endpoint enforces the one-active-per-program rule.
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

                $term = $this->route('term');
                if (! $term?->academicYear) {
                    return;
                }

                $overlap = $this->overlappingTerm($term->academicYear, $term->id);
                if ($overlap) {
                    $validator->errors()->add('starts_on', $this->termOverlapMessage($overlap));
                }
            },
        ];
    }
}

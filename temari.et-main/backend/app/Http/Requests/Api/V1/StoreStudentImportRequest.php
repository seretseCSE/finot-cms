<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\ResolvesTargetBranchId;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Opens an import session: the target year (+ optional grade/section/program
 * defaults rows may override), the original file name, and the studio's
 * header mapping. Branch targeting follows the school-wide write pattern
 * (explicit branch_id wins, else the X-Branch-Id context); cross-branch
 * integrity of the ids is enforced here so a session can never mix branches.
 */
class StoreStudentImportRequest extends FormRequest
{
    use ResolvesTargetBranchId;

    public function authorize(): bool
    {
        return true; // students.create at the target branch is checked in the controller.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $branchId = $this->targetBranchId();

        return [
            'branch_id' => ['sometimes', 'integer', Rule::exists('branches', 'id')->whereNull('deleted_at')],
            'academic_year_id' => [
                'required', 'integer',
                Rule::exists('academic_years', 'id')->where('branch_id', $branchId)->whereNull('deleted_at'),
            ],
            'grade_level_id' => ['nullable', 'integer', Rule::exists('grade_levels', 'id')],
            'section_id' => [
                'nullable', 'integer',
                Rule::exists('sections', 'id')->where('branch_id', $branchId)->whereNull('deleted_at'),
            ],
            'school_program_id' => [
                'nullable', 'integer',
                Rule::exists('school_programs', 'id')->where('branch_id', $branchId)->whereNull('deleted_at'),
            ],
            'file_name' => ['required', 'string', 'max:255'],
            'column_map' => ['nullable', 'array'],
            'options' => ['nullable', 'array'],
            'options.send_sms' => ['sometimes', 'boolean'],
            'options.create_student_accounts' => ['sometimes', 'boolean'],
        ];
    }
}

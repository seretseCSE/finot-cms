<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreSubjectAssignmentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'term_id' => ['required', 'integer', 'exists:terms,id'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'periods_per_week' => ['integer', 'min:0', 'max:30'],
        ];
    }
}

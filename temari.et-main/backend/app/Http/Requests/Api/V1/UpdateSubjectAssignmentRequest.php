<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSubjectAssignmentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'periods_per_week' => ['integer', 'min:0', 'max:30'],
        ];
    }
}

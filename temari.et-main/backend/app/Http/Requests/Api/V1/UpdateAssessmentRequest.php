<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAssessmentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'string', Rule::in(['quiz', 'test', 'mid_exam', 'final_exam', 'assignment', 'project'])],
            'name' => ['sometimes', 'string', 'max:100'],
            'max_score' => ['sometimes', 'numeric', 'min:1', 'max:100'],
            'weight' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'conducted_on' => ['nullable', 'date', 'after:2000-01-01', 'before:2100-01-01'],
        ];
    }
}

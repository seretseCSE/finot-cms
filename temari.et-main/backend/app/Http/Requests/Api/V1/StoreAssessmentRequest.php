<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssessmentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(['quiz', 'test', 'mid_exam', 'final_exam', 'assignment', 'project'])],
            'name' => ['required', 'string', 'max:100'],
            'max_score' => ['required', 'numeric', 'min:1', 'max:100'],
            'weight' => ['required', 'numeric', 'min:0', 'max:100'],
            'conducted_on' => ['nullable', 'date', 'after:2000-01-01', 'before:2100-01-01'],
        ];
    }
}

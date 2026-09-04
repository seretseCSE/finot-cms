<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class BulkUpsertResultsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'results' => ['required', 'array', 'min:1'],
            'results.*.student_id' => ['required', 'integer', 'exists:students,id'],
            'results.*.score' => ['nullable', 'numeric', 'min:0'],
            'results.*.is_absent' => ['boolean'],
            'results.*.remarks' => ['nullable', 'string', 'max:255'],
        ];
    }
}

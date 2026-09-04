<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Subject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubjectRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', Rule::unique('subjects', 'code')->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:100'],
            'category' => ['nullable', Rule::in(Subject::CATEGORIES)],
            // Explicit grade set; empty/absent = taught in every grade.
            'grade_level_ids' => ['nullable', 'array', 'max:20'],
            'grade_level_ids.*' => ['integer', Rule::exists('grade_levels', 'id')],
            'weight' => ['nullable', 'integer', 'min:1', 'max:5'],
            'room_type' => ['nullable', Rule::in(Subject::ROOM_TYPES)],
            'is_active' => ['boolean'],
        ];
    }
}

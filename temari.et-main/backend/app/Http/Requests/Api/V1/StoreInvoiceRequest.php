<?php

namespace App\Http\Requests\Api\V1;

use App\Support\Ethiopia;
use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
{
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
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'fee_structure_id' => ['nullable', 'integer', 'exists:fee_structures,id'],
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'term_id' => ['nullable', 'integer', 'exists:terms,id'],
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999'],
            // A manual invoice can't be born overdue — billing late means due
            // today at the earliest (Addis wall clock).
            'due_date' => ['nullable', 'date', 'after_or_equal:'.Ethiopia::today(), 'before:2100-01-01'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['due_date.after_or_equal' => __('dates.due_past')];
    }
}

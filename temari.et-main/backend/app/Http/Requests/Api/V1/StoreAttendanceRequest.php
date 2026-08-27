<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\AttendanceStatus;
use App\Support\Ethiopia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttendanceRequest extends FormRequest
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
            // "Today" on the Addis wall clock — the app clock is UTC, and the
            // Ethiopian date is already tomorrow between 21:00 and 24:00 UTC.
            'date' => ['required', 'date', 'before_or_equal:'.Ethiopia::today()],
            'records' => ['required', 'array', 'min:1'],
            'records.*.student_id' => ['required', 'integer', 'exists:students,id'],
            'records.*.status' => ['required', Rule::enum(AttendanceStatus::class)],
            'records.*.check_in' => ['nullable', 'date_format:H:i'],
            'records.*.check_out' => ['nullable', 'date_format:H:i'],
            'records.*.note' => ['nullable', 'string', 'max:255'],
        ];
    }
}

<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\FeeStructureRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreFeeStructureRequest extends FormRequest
{
    use FeeStructureRules;

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
            ...$this->feeRules(),
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
        ];
    }
}

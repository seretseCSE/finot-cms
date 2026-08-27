<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\EmployeeProfileRules;
use App\Http\Requests\Api\V1\Concerns\NormalizesPhoneFields;
use App\Rules\EthiopianPhone;
use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
{
    use EmployeeProfileRules, NormalizesPhoneFields;

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
            ...$this->profileRules(),
            'phone' => ['required', 'string', 'max:20', new EthiopianPhone],
            // A new staff member must be hired into at least one job.
            'positions' => ['required', 'array', 'min:1', 'max:10'],
            // Opt out of the portal account (policy-eligible titles only;
            // role-mapped titles always provision — memberships need a user).
            'create_user_account' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return list<string>
     */
    protected function phoneFields(): array
    {
        return ['phone'];
    }
}

<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\EmployeeProfileRules;
use App\Http\Requests\Api\V1\Concerns\NormalizesPhoneFields;
use App\Rules\EthiopianPhone;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeRequest extends FormRequest
{
    use EmployeeProfileRules;
    use NormalizesPhoneFields;

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
            // The contact phone is editable like any other profile field; the
            // per-branch duplicate guard (and the login-phone follow-through)
            // lives in EmployeeController@update where the record is at hand.
            'phone' => ['sometimes', 'required', 'string', 'max:20', new EthiopianPhone()],
            // Grant a portal account to an account-less employee (eligible
            // titles only; role-mapped titles auto-provision regardless).
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

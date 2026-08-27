<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\Gender;
use App\Enums\GuardianRelationship;
use App\Http\Requests\Api\V1\Concerns\NormalizesPhoneFields;
use App\Models\StudentGuardian;
use App\Rules\EthiopianPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGuardianRequest extends FormRequest
{
    use NormalizesPhoneFields;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return list<string>
     */
    protected function phoneFields(): array
    {
        return ['phone', 'secondary_phone'];
    }

    /**
     * Link fields (relationship, consent flags…) always apply. The optional
     * profile fields fix typos in the parent's PERSON record (parents are
     * global, ADR-011) — phone/email must stay unique across users since the
     * phone is the login identifier.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var StudentGuardian $guardian */
        $guardian = $this->route('guardian');
        $userId = $guardian->parentProfile?->user_id;

        return [
            'relationship' => ['required', Rule::enum(GuardianRelationship::class)],
            'can_view_grades' => ['sometimes', 'boolean'],
            'can_view_attendance' => ['sometimes', 'boolean'],
            'can_pay_fees' => ['sometimes', 'boolean'],
            'can_receive_sms' => ['sometimes', 'boolean'],
            'is_primary' => ['sometimes', 'boolean'],
            'emergency_contact' => ['sometimes', 'boolean'],
            'priority_order' => ['nullable', 'integer', 'min:1', 'max:99'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],

            // Parent profile corrections.
            'first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'father_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'grandfather_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'gender' => ['sometimes', 'nullable', Rule::enum(Gender::class)],
            'occupation' => ['sometimes', 'nullable', 'string', 'max:255'],
            'secondary_phone' => ['sometimes', 'nullable', 'string', 'max:20', new EthiopianPhone],
            'phone' => [
                'sometimes', 'required', 'string', 'max:20', new EthiopianPhone,
                Rule::unique('users', 'phone')->ignore($userId),
            ],
            'email' => [
                'sometimes', 'nullable', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
        ];
    }
}

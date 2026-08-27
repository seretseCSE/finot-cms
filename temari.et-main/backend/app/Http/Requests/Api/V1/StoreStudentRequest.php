<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\ConcessionCategory;
use App\Enums\DiscountType;
use App\Enums\FeeType;
use App\Enums\PaymentMethod;
use App\Http\Requests\Api\V1\Concerns\GuardianRules;
use App\Http\Requests\Api\V1\Concerns\NormalizesPhoneFields;
use App\Http\Requests\Api\V1\Concerns\StudentProfileRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreStudentRequest extends FormRequest
{
    use GuardianRules, NormalizesPhoneFields, StudentProfileRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return list<string>
     */
    protected function phoneFields(): array
    {
        return ['primary_phone'];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeDeclaredPhones();
        $this->normalizeNestedPhones('guardians', ['phone', 'secondary_phone']);

        // Every student needs login credentials from day one — omitting the
        // flag means "create". Only an explicit false (e.g. a lane that
        // provisions accounts separately) skips it.
        if (! $this->has('create_user_account')) {
            $this->merge(['create_user_account' => true]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->profileRules(),

            'national_student_id' => [
                'nullable', 'string', 'max:50',
                Rule::unique('students', 'national_student_id')->whereNull('deleted_at'),
            ],

            // Optional one-step enrollment: year + grade level (section may be
            // assigned later).
            'academic_year_id' => ['nullable', 'required_with:section_id,grade_level_id', 'integer', 'exists:academic_years,id'],
            // The section carries the grade when given; otherwise the grade is
            // required directly.
            'grade_level_id' => [
                'nullable',
                Rule::requiredIf(fn (): bool => $this->filled('academic_year_id') && ! $this->filled('section_id')),
                'integer', 'exists:grade_levels,id',
            ],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'school_program_id' => ['nullable', 'integer', 'exists:school_programs,id'],
            'previous_school_id' => ['nullable', 'integer', Rule::exists('school_directory', 'id')->whereNull('deleted_at')],
            'enrolled_on' => ['nullable', 'date', 'after:2000-01-01', 'before:2100-01-01'],

            // Fees assigned at registration (validated against the enrollment's
            // year + grade in the action) with optional inline payments.
            'fee_structure_ids' => ['sometimes', 'array', 'max:20'],
            'fee_structure_ids.*' => ['integer', Rule::exists('fee_structures', 'id')->whereNull('deleted_at')],
            'pay_now' => ['sometimes', 'array', 'max:20'],
            'pay_now.*.fee_structure_id' => ['required', 'integer'],
            'pay_now.*.amount' => ['nullable', 'numeric', 'min:0.01', 'max:9999999999'],
            'pay_now.*.method' => ['nullable', Rule::enum(PaymentMethod::class)],
            // Which collection account/wallet received the money (bank and
            // wallet channels) — usability is enforced in the action.
            'pay_now.*.bank_account_id' => ['nullable', 'integer', Rule::exists('bank_accounts', 'id')->whereNull('deleted_at')],
            'pay_now.*.reference' => ['nullable', 'string', 'max:255'],
            // Scholarships granted at registration (full scholarship + reason).
            'scholarships' => ['sometimes', 'array', 'max:20'],
            'scholarships.*.fee_structure_id' => ['required', 'integer'],
            'scholarships.*.reason' => ['required', 'string', 'max:255'],

            // Optional standing concession filed WITH the registration, created
            // before invoicing so the very first bill already carries the
            // discount (fees.manage gated in the controller).
            'concession' => ['sometimes', 'array'],
            'concession.category' => ['required_with:concession', Rule::enum(ConcessionCategory::class)],
            'concession.discount_type' => ['required_with:concession', Rule::in([
                DiscountType::Percentage->value, DiscountType::Fixed->value, DiscountType::FullScholarship->value,
            ])],
            'concession.discount_value' => [
                'nullable', 'numeric', 'min:0.01', 'max:9999999999',
                Rule::requiredIf(fn (): bool => in_array($this->input('concession.discount_type'), [
                    DiscountType::Percentage->value, DiscountType::Fixed->value,
                ], true)),
            ],
            'concession.fee_types' => ['nullable', 'array', 'min:1'],
            'concession.fee_types.*' => [Rule::enum(FeeType::class)],
            'concession.reason' => ['nullable', 'string', 'max:255'],

            // ON by default (merged in prepareForValidation): every student
            // gets a login at registration. With a primary_phone the account
            // is keyed by it; without one the student gets a phone-less
            // ID-login account (student ID + PIN, setup SMS to the primary
            // guardian). Uniqueness is enforced in LinkStudentLoginAction.
            'create_user_account' => ['sometimes', 'boolean'],

            // Guardians registered in the same step — every student must have
            // at least one parent/guardian on file.
            'guardians' => ['required', 'array', 'min:1', 'max:6'],
            ...self::guardianRowRules('guardians.*.'),
        ];
    }

    /**
     * The student's own phone is the STUDENT's — a number reused from a
     * guardian row is the exact mix-up the wizard exists to prevent, so
     * reject it at the field before any account is provisioned.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $studentPhone = $this->input('primary_phone');

            if (! is_string($studentPhone) || trim($studentPhone) === '') {
                return;
            }

            foreach ((array) $this->input('guardians', []) as $index => $guardian) {
                if (is_array($guardian) && ($guardian['phone'] ?? null) === $studentPhone) {
                    $v->errors()->add(
                        'primary_phone',
                        'This is guardian #'.($index + 1)."'s phone number. The student's phone must be their own — leave it empty if they don't have one, and they will sign in with their student ID instead.",
                    );

                    return;
                }
            }
        });
    }
}

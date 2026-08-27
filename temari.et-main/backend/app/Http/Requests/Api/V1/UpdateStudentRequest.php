<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\NormalizesPhoneFields;
use App\Http\Requests\Api\V1\Concerns\StudentProfileRules;
use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateStudentRequest extends FormRequest
{
    use NormalizesPhoneFields, StudentProfileRules;

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

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $student = $this->route('student');

        return [
            ...$this->profileRules(),

            'national_student_id' => [
                'nullable', 'string', 'max:50',
                Rule::unique('students', 'national_student_id')
                    ->whereNull('deleted_at')
                    ->ignore($student?->id),
            ],
        ];
    }

    /**
     * The student's own phone is the STUDENT's (mirrors StoreStudentRequest):
     * editing the profile must not smuggle a linked guardian's number into
     * `primary_phone` — that mix-up would poison ID-login delivery and any
     * later account provisioning.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $phone = $this->input('primary_phone');

            if (! is_string($phone) || trim($phone) === '') {
                return;
            }

            /** @var Student|null $student */
            $student = $this->route('student');

            $isGuardianPhone = $student !== null && $student->guardians()
                ->whereHas('parentProfile.user', fn ($q) => $q->where('phone', $phone))
                ->exists();

            if ($isGuardianPhone) {
                $v->errors()->add(
                    'primary_phone',
                    "This is a guardian's phone number. The student's phone must be their own — leave it empty if they don't have one, and they will sign in with their student ID instead.",
                );
            }
        });
    }
}

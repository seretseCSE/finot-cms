<?php

namespace App\Http\Requests\Api\V1\Concerns;

use App\Enums\EmploymentType;
use App\Models\EmployeeQualification;
use App\Rules\BirthDate;
use App\Support\AllowanceTypes;
use App\Support\JobTitles;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

/**
 * The HR profile fields shared by store + update. Everything beyond name/phone/
 * positions is optional — the form is progressive-disclosure by design.
 * Positions, qualifications, allowances and deductions arrive as child arrays;
 * position/qualification rows carry an optional `id` for in-place updates.
 */
trait EmployeeProfileRules
{
    /**
     * @return array<string, mixed>
     */
    protected function profileRules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'father_name' => ['nullable', 'string', 'max:255'],
            'grandfather_name' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],

            // Personal — minimum working age per Labour Proclamation 1156/2019.
            'birth_date' => ['nullable', 'date', new BirthDate(minAgeYears: 15)],
            'email' => ['nullable', 'email', 'max:255'],
            'marital_status' => ['nullable', Rule::in(['single', 'married', 'divorced', 'widowed'])],
            'nationality' => ['nullable', 'string', 'max:100'],

            // Address
            'state' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'sub_city' => ['nullable', 'string', 'max:100'],
            'woreda' => ['nullable', 'string', 'max:100'],
            'house_no' => ['nullable', 'string', 'max:50'],

            // Person-level career facts
            'professional_level' => ['nullable', 'string', 'max:255'],
            'retirement_on' => ['nullable', 'date', 'after:1970-01-01', 'before:2100-01-01'],

            // Positions — the jobs this person holds (job title → type/salary/dates)
            'positions' => ['sometimes', 'array', 'min:1', 'max:10'],
            'positions.*.id' => ['nullable', 'integer'],
            'positions.*.job_title' => ['required', Rule::in(JobTitles::ALL)],
            'positions.*.employment_type' => ['nullable', new Enum(EmploymentType::class)],
            'positions.*.salary_level' => ['nullable', 'integer', 'min:1', 'max:10'],
            'positions.*.salary' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            // Required: Art. 77 leave entitlement grows with service, which
            // cannot be computed without the hire date.
            'positions.*.hired_on' => ['required', 'date', 'after:1950-01-01', 'before:2100-01-01'],
            'positions.*.last_promoted_on' => ['nullable', 'date', 'after:1950-01-01', 'before:2100-01-01'],
            'positions.*.ended_on' => ['nullable', 'date', 'after:1950-01-01', 'before:2100-01-01'],
            'positions.*.is_primary' => ['sometimes', 'boolean'],

            // Qualifications — academic credentials (many per person)
            'qualifications' => ['sometimes', 'array', 'max:10'],
            'qualifications.*.id' => ['nullable', 'integer'],
            'qualifications.*.education_level' => ['required', Rule::in(EmployeeQualification::EDUCATION_LEVELS)],
            'qualifications.*.field_of_study' => ['nullable', 'string', 'max:255'],
            'qualifications.*.institution' => ['nullable', 'string', 'max:255'],
            'qualifications.*.graduation_year' => ['nullable', 'integer', 'min:1950', 'max:2100'],

            // Teaching capability — subject × grade rows (teachers only)
            'teacher_subjects' => ['sometimes', 'array', 'max:100'],
            'teacher_subjects.*.subject_id' => ['required', 'integer', Rule::exists('subjects', 'id')],
            'teacher_subjects.*.grade_level_id' => ['required', 'integer', Rule::exists('grade_levels', 'id')],

            // Attendance window
            'check_in' => ['nullable', 'date_format:H:i'],
            'check_out' => ['nullable', 'date_format:H:i', 'after:check_in'],

            'allowances' => ['nullable', 'array', 'max:20'],
            'allowances.*.name' => ['required', Rule::in(AllowanceTypes::ALL)],
            'allowances.*.amount' => ['required', 'numeric', 'min:0', 'max:9999999999'],

            'deductions' => ['nullable', 'array', 'max:20'],
            'deductions.*.name' => ['required', 'string', 'max:100'],
            'deductions.*.amount' => ['required', 'numeric', 'min:0', 'max:9999999999'],

            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Cross-row position integrity: active job titles must be unique and
     * exactly one active position must be primary (the salary anchor).
     * Cross-field date order (retirement vs birth, position dates vs hire)
     * lives here too — the `after:` rule can't reference a nullable sibling.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $birth = self::parseDate($this->input('birth_date'));
                $retirement = self::parseDate($this->input('retirement_on'));

                if ($birth !== null && $retirement !== null && $retirement <= $birth) {
                    $validator->errors()->add('retirement_on', __('dates.retirement_before_birth'));
                }

                $positions = $this->input('positions');

                if (! is_array($positions) || $positions === []) {
                    return;
                }

                foreach ($positions as $index => $position) {
                    if (! is_array($position)) {
                        continue;
                    }

                    $hired = self::parseDate($position['hired_on'] ?? null);
                    $ended = self::parseDate($position['ended_on'] ?? null);
                    $promoted = self::parseDate($position['last_promoted_on'] ?? null);

                    if ($hired !== null && $ended !== null && $ended < $hired) {
                        $validator->errors()->add("positions.{$index}.ended_on", __('dates.ended_before_hired'));
                    }

                    if ($hired !== null && $promoted !== null && $promoted < $hired) {
                        $validator->errors()->add("positions.{$index}.last_promoted_on", __('dates.promoted_before_hired'));
                    }
                }

                $active = array_values(array_filter($positions, fn ($p) => empty($p['ended_on'])));

                if ($active === []) {
                    $validator->errors()->add('positions', 'At least one position must be current (no end date).');

                    return;
                }

                $jobTitles = array_column($active, 'job_title');
                if (count($jobTitles) !== count(array_unique($jobTitles))) {
                    $validator->errors()->add('positions', 'Each job title may only be held once at a time.');
                }

                $primaries = array_filter($active, fn ($p) => ! empty($p['is_primary']));
                if (count($primaries) !== 1) {
                    $validator->errors()->add('positions', 'Exactly one current position must be marked primary.');
                }
            },
        ];
    }

    private static function parseDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}

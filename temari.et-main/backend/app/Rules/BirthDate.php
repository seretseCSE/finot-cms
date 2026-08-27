<?php

namespace App\Rules;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The one birth-date rule for the whole app: never in the future, and the
 * person's age must fall inside a role-appropriate window (students ≥ 1 year,
 * employees ≥ 15 years per Labour Proclamation 1156/2019; everyone ≤ 100).
 * Pair with `nullable` + `date` — a non-date value is left for the `date`
 * rule to report.
 */
class BirthDate implements ValidationRule
{
    public function __construct(
        private readonly int $minAgeYears = 1,
        private readonly int $maxAgeYears = 100,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $date = CarbonImmutable::parse($value)->startOfDay();
        } catch (\Throwable) {
            return;
        }

        $today = CarbonImmutable::today();

        if ($date->greaterThan($today)) {
            $fail('dates.birth_future')->translate();

            return;
        }

        if ($date->greaterThan($today->subYears($this->minAgeYears))) {
            $fail('dates.birth_too_young')->translate(['years' => $this->minAgeYears]);

            return;
        }

        if ($date->lessThan($today->subYears($this->maxAgeYears))) {
            $fail('dates.birth_too_old')->translate(['years' => $this->maxAgeYears]);
        }
    }
}

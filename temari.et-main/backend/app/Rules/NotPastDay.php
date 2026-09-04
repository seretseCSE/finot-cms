<?php

namespace App\Rules;

use App\Support\Ethiopia;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The value's calendar day must not be before today (Addis wall time — the
 * school-day anchor, matching the attendance cutoff). Works for bare dates
 * and datetimes alike: only the day part is judged, so "today at 08:00"
 * always passes regardless of the current hour.
 *
 * Pass the record's CURRENT value on updates — a payload that re-sends an
 * already-past date untouched still validates; only NEW past picks refuse.
 * Pair with `nullable` + `date` — a non-date value is left for the `date`
 * rule to report.
 */
class NotPastDay implements ValidationRule
{
    public function __construct(private readonly mixed $previous = null)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $parsed = CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return;
        }

        if ($this->previous !== null) {
            try {
                if ($parsed->equalTo(CarbonImmutable::parse($this->previous))) {
                    return; // unchanged — never brick an edit over an old date
                }
            } catch (\Throwable) {
            }
        }

        // Judge the day on Addis wall time. Offset-carrying instants land on
        // their true Addis day; naive local strings can only shift FORWARD
        // (UTC assumed, +3h), so a same-day pick is never wrongly refused.
        if ($parsed->setTimezone(Ethiopia::TIMEZONE)->toDateString() < Ethiopia::today()) {
            $fail('dates.day_past')->translate();
        }
    }
}

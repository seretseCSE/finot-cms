<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Ethiopian locale constants. The app clock is UTC; anything anchored to the
 * school day (attendance dates, cutoffs, "today") runs on Addis wall time.
 */
class Ethiopia
{
    public const TIMEZONE = 'Africa/Addis_Ababa';

    public static function today(): string
    {
        return Carbon::now(self::TIMEZONE)->toDateString();
    }

    public static function now(): Carbon
    {
        return Carbon::now(self::TIMEZONE);
    }
}

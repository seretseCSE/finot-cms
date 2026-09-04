<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\School;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Lang;

/**
 * The single backend authority for HUMAN-facing dates and times (SMS, email,
 * PDFs). Storage is always Gregorian/UTC — this class only changes how a
 * moment is WRITTEN, per the school's display settings:
 *
 *  - calendar_mode: `ethiopian` (default) renders Ge'ez-calendar dates with
 *    the locale's month names; `gregorian` renders Gregorian ones.
 *  - clock_mode: `standard` renders 8:00 AM; `ethiopian` renders the day
 *    counted from dawn — "2:00 ጠዋት" — how schools speak their bell times.
 *
 * Official documents always print BOTH calendars via dual() regardless of the
 * school setting, so a transcript or receipt is legible anywhere.
 *
 * Timestamps are rendered on Addis wall time (Ethiopia::TIMEZONE) — Ethiopia
 * has no DST, the offset is a constant +3.
 */
class DateFormatter
{
    /**
     * Effective display modes for a scope, branch override first. Cached —
     * notification fan-outs resolve this once per scope, not per recipient.
     *
     * @return array{calendar: string, clock: string}
     */
    /** @var array<string, array{calendar: string, clock: string}> */
    private static array $memo = [];

    /**
     * Drop the request-level memo. A queue worker is a LONG-LIVED process:
     * without this the first modes it resolves for a scope would be pinned for
     * the worker's lifetime, and the `Cache::forget` a settings save fires in
     * the web process could never reach it — a school that switched to the
     * Gregorian calendar would keep getting Ethiopian dates in its SMS and
     * PDFs until someone restarted the worker. Called between jobs.
     */
    public static function flushMemo(): void
    {
        self::$memo = [];
    }

    public static function modesFor(?int $schoolId, ?int $branchId = null): array
    {
        if ($schoolId === null) {
            return ['calendar' => 'ethiopian', 'clock' => 'ethiopian'];
        }

        $key = "display-modes:{$schoolId}:".($branchId ?? 0);

        // Request-level memo first — a notification feed page resolves the
        // same scope dozens of times.
        return self::$memo[$key] ??= Cache::remember($key, 300, function () use ($schoolId, $branchId): array {
            $branch = $branchId !== null ? Branch::query()->with('school')->find($branchId) : null;

            if ($branch !== null) {
                return ['calendar' => $branch->effectiveCalendarMode(), 'clock' => $branch->effectiveClockMode()];
            }

            $school = School::query()->find($schoolId);

            return [
                'calendar' => $school?->calendarMode() ?? 'ethiopian',
                'clock' => $school?->clockMode() ?? 'ethiopian',
            ];
        });
    }

    /**
     * "Hamle 15, 2018" / "ሐምሌ 15 ቀን 2018" — or the Gregorian equivalent when
     * the calendar says so. Accepts a date string or Carbon; null-safe.
     */
    public static function date(
        CarbonInterface|string|null $date,
        string $calendar = 'ethiopian',
        string $locale = 'en',
        bool $withEra = false,
        bool $withWeekday = false,
    ): string {
        $day = self::toAddisDay($date);
        if ($day === null) {
            return '';
        }

        $weekday = $withWeekday
            ? Lang::get('dates.weekdays.'.$day->dayOfWeek, [], $locale).($locale === 'am' ? '፣ ' : ', ')
            : '';

        if ($calendar === 'gregorian') {
            $month = Lang::get('dates.greg_months.'.$day->month, [], $locale);
            $era = $withEra ? ' '.Lang::get('dates.era_gregorian', [], $locale) : '';

            return $weekday.self::joinDate($month, $day->day, $day->year, $locale).$era;
        }

        $eth = EthiopianDate::fromGregorian($day);
        $month = Lang::get('dates.eth_months.'.$eth['month'], [], $locale);
        $era = $withEra ? ' '.Lang::get('dates.era_ethiopian', [], $locale) : '';

        return $weekday.self::joinDate($month, $eth['day'], $eth['year'], $locale).$era;
    }

    /**
     * The official-document form: both calendars, Ethiopian first —
     * "Hamle 15, 2018 E.C. (July 22, 2026 G.C.)". Used on every generated PDF
     * regardless of the school's display setting.
     */
    public static function dual(CarbonInterface|string|null $date, string $locale = 'en'): string
    {
        if (self::toAddisDay($date) === null) {
            return '';
        }

        return self::date($date, 'ethiopian', $locale, withEra: true)
            .' ('.self::date($date, 'gregorian', $locale, withEra: true).')';
    }

    /**
     * A time of day: `standard` → "8:00 AM"; `ethiopian` → "2:00 ጠዋት" (dawn
     * count). Accepts "HH:MM"(:SS) strings or Carbon instants (rendered on
     * Addis wall time).
     */
    public static function time(
        CarbonInterface|string|null $time,
        string $clock = 'ethiopian',
        string $locale = 'en',
    ): string {
        [$hour, $minute] = self::toHourMinute($time);
        if ($hour === null) {
            return '';
        }

        $mm = str_pad((string) $minute, 2, '0', STR_PAD_LEFT);

        if ($clock === 'ethiopian') {
            $ethHour = (($hour + 5) % 12) + 1;
            $period = match (true) {
                $hour < 6 => 'period_night',
                $hour < 12 => 'period_morning',
                $hour < 18 => 'period_afternoon',
                default => 'period_evening',
            };

            return "{$ethHour}:{$mm} ".Lang::get('dates.'.$period, [], $locale);
        }

        $h12 = $hour % 12 === 0 ? 12 : $hour % 12;

        return "{$h12}:{$mm} ".($hour < 12 ? 'AM' : 'PM');
    }

    /** Date + time in one line, per the scope's modes. */
    public static function dateTime(
        CarbonInterface|string|null $moment,
        string $calendar = 'ethiopian',
        string $clock = 'ethiopian',
        string $locale = 'en',
    ): string {
        $day = self::toAddisDay($moment);
        if ($day === null) {
            return '';
        }

        return self::date($moment, $calendar, $locale).', '.self::time($moment, $clock, $locale);
    }

    /** "Hamle 2018" / "July 2026" — month-granularity labels. */
    public static function monthYear(int $year, int $month, string $calendar = 'ethiopian', string $locale = 'en'): string
    {
        $key = $calendar === 'gregorian' ? 'dates.greg_months.' : 'dates.eth_months.';

        return Lang::get($key.$month, [], $locale).' '.$year;
    }

    /** Normalize any accepted input to an Addis-wall-time Carbon day, or null. */
    private static function toAddisDay(CarbonInterface|string|null $date): ?CarbonImmutable
    {
        if ($date === null || $date === '') {
            return null;
        }

        try {
            $carbon = $date instanceof CarbonInterface
                ? CarbonImmutable::instance($date)
                : CarbonImmutable::parse($date);
        } catch (\Throwable) {
            return null;
        }

        // Bare dates ("2026-07-22") must not shift; instants take the Addis
        // wall-clock DAY. Re-parse as a plain date so later calendar math
        // never mixes timezones (a +03 Carbon against UTC anchors truncates
        // diffInDays).
        if (is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $carbon->startOfDay();
        }

        return CarbonImmutable::parse($carbon->setTimezone(Ethiopia::TIMEZONE)->toDateString());
    }

    /** @return array{0: int|null, 1: int|null} */
    private static function toHourMinute(CarbonInterface|string|null $time): array
    {
        if ($time instanceof CarbonInterface) {
            $addis = CarbonImmutable::instance($time)->setTimezone(Ethiopia::TIMEZONE);

            return [$addis->hour, $addis->minute];
        }

        if (is_string($time) && preg_match('/^(\d{1,2}):(\d{2})/', $time, $m) && (int) $m[1] < 24 && (int) $m[2] < 60) {
            return [(int) $m[1], (int) $m[2]];
        }

        return [null, null];
    }

    /** Amharic writes "ሐምሌ 15 ቀን 2018"; Latin scripts "Hamle 15, 2018". */
    private static function joinDate(string $month, int $day, int $year, string $locale): string
    {
        return $locale === 'am'
            ? "{$month} {$day} ቀን {$year}"
            : "{$month} {$day}, {$year}";
    }
}

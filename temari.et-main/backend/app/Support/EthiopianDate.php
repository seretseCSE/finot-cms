<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Ethiopian (Ge'ez) calendar arithmetic. Anchored on the Meskerem 1 rule the
 * app already trusts (LeavePolicy): Ethiopian new year falls on Sep 11, or
 * Sep 12 when the FOLLOWING Gregorian year is a leap year. From that anchor
 * every month is exactly 30 days, with Pagume (month 13) holding the
 * remaining 5–6 days. Valid for the Gregorian years 1900–2099.
 *
 * Monthly fee billing runs on THIS calendar — an Ethiopian school bills
 * "Meskerem tuition", never "September tuition".
 */
class EthiopianDate
{
    /** Latinised month names — the i18n files carry the Amharic/Oromo forms. */
    public const array MONTHS = [
        1 => 'Meskerem', 2 => 'Tikimt', 3 => 'Hidar', 4 => 'Tahsas',
        5 => 'Tir', 6 => 'Yekatit', 7 => 'Megabit', 8 => 'Miazia',
        9 => 'Ginbot', 10 => 'Sene', 11 => 'Hamle', 12 => 'Nehase',
        13 => 'Pagume',
    ];

    /** Meskerem 1 of the Ethiopian year whose new year falls in $gregorianYear. */
    public static function meskerem1(int $gregorianYear): CarbonImmutable
    {
        $day = CarbonImmutable::create($gregorianYear + 1, 1, 1)->isLeapYear() ? 12 : 11;

        return CarbonImmutable::create($gregorianYear, 9, $day);
    }

    /** Gregorian date of Ethiopian new year's day for Ethiopian year $year. */
    public static function newYearOf(int $year): CarbonImmutable
    {
        // EC 2017 begins in GC 2024 — the offset is a constant 7.
        return self::meskerem1($year + 7);
    }

    /**
     * Convert a Gregorian date to its Ethiopian (year, month, day).
     *
     * @return array{year: int, month: int, day: int}
     */
    public static function fromGregorian(CarbonImmutable $date): array
    {
        $date = $date->startOfDay();
        $year = $date->year - 8;
        if ($date->greaterThanOrEqualTo(self::newYearOf($year + 1))) {
            $year++;
        }

        $offset = (int) self::newYearOf($year)->diffInDays($date);

        return [
            'year' => $year,
            'month' => intdiv($offset, 30) + 1,
            'day' => $offset % 30 + 1,
        ];
    }

    /** Convert an Ethiopian date to Gregorian. */
    public static function toGregorian(int $year, int $month, int $day): CarbonImmutable
    {
        if ($month < 1 || $month > 13 || $day < 1 || $day > self::daysInMonth($year, $month)) {
            throw new InvalidArgumentException("Invalid Ethiopian date: {$year}-{$month}-{$day}");
        }

        return self::newYearOf($year)->addDays(($month - 1) * 30 + $day - 1);
    }

    /** 30 for the twelve months; 5 or 6 for Pagume. */
    public static function daysInMonth(int $year, int $month): int
    {
        if ($month < 13) {
            return 30;
        }

        return (int) self::newYearOf($year)->diffInDays(self::newYearOf($year + 1)) - 360;
    }

    /** Gregorian window covered by an Ethiopian month. */
    public static function monthStart(int $year, int $month): CarbonImmutable
    {
        return self::toGregorian($year, $month, 1);
    }

    public static function monthEnd(int $year, int $month): CarbonImmutable
    {
        return self::toGregorian($year, $month, self::daysInMonth($year, $month));
    }

    /** "Meskerem 2018" — the invoice-title label for a billing month. */
    public static function monthLabel(int $year, int $month): string
    {
        return self::MONTHS[$month].' '.$year;
    }

    /**
     * Step forward $stride Ethiopian months (Pagume folds into the year end:
     * billing months only ever use 1–12; stepping past Nehase rolls the year).
     *
     * @return array{year: int, month: int}
     */
    public static function addMonths(int $year, int $month, int $stride): array
    {
        $index = ($year * 12) + ($month - 1) + $stride;

        return ['year' => intdiv($index, 12), 'month' => $index % 12 + 1];
    }
}

<?php

namespace App\Helpers;

use Andegna\DateTime as EthiopianDateTime;
use Andegna\DateTimeFactory;
use Carbon\Carbon;

/**
 * EthiopianDateHelper using the andegna/calender package.
 * Provides Gregorian to Ethiopian date conversion throughout the system.
 */
class EthiopianDateHelper
{
    /**
     * Standard Gregorian months
     */
    protected array $months = [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June',
        7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
    ];

    /**
     * Ethiopian months
     */
    protected array $ethiopianMonths = [
        1 => ['en' => 'Meskerem', 'am' => 'መስከረም'],
        2 => ['en' => 'Tikimt', 'am' => 'ጥቅምት'],
        3 => ['en' => 'Hidar', 'am' => 'ኅዳር'],
        4 => ['en' => 'Tahsas', 'am' => 'ታኅሣሥ'],
        5 => ['en' => 'Tir', 'am' => 'ጥር'],
        6 => ['en' => 'Yekatit', 'am' => 'የካቲት'],
        7 => ['en' => 'Megabit', 'am' => 'መጋቢት'],
        8 => ['en' => 'Miazia', 'am' => 'ሚያዝያ'],
        9 => ['en' => 'Ginbot', 'am' => 'ግንቦት'],
        10 => ['en' => 'Sene', 'am' => 'ሰኔ'],
        11 => ['en' => 'Hamle', 'am' => 'ሐምሌ'],
        12 => ['en' => 'Nehasse', 'am' => 'ነሐሴ'],
        13 => ['en' => 'Pagume', 'am' => 'ጳጉሜን'],
    ];

    /**
     * Get months for contribution selection
     * Allows choice between Ethiopian and Gregorian names
     */
    public static function getMonthsForContribution(string $type = 'ethiopian'): array
    {
        $instance = new self();

        if ($type === 'gregorian') {
            return $instance->months;
        }

        $options = [];
        foreach ($instance->ethiopianMonths as $key => $month) {
            if ($key === 13) {
                continue;
            } // Exclude Pagume for contributions
            $options[$month['en']] = $month['en'].' ('.$month['am'].')';
        }

        return $options;
    }

    /**
     * Get all available month options (combined)
     */
    public static function getAllMonthOptions(): array
    {
        $instance = new self();
        $options = [];

        // Add Ethiopian Months
        foreach ($instance->ethiopianMonths as $key => $month) {
            if ($key === 13) {
                continue;
            }
            $options['Ethiopian Months'][$month['en']] = $month['en'].' ('.$month['am'].')';
        }

        // Add Gregorian Months
        foreach ($instance->months as $month) {
            $options['Gregorian Months'][$month] = $month;
        }

        return $options;
    }

    /**
     * Get all possible contribution months (flat array)
     * Used for database consistency and ordering
     */
    public static function getContributionMonths(): array
    {
        $instance = new self();
        $months = [];

        // Ethiopian Months
        foreach ($instance->ethiopianMonths as $key => $month) {
            if ($key === 13) {
                continue;
            }
            $months[] = $month['en'];
        }

        return $months;
    }

    /**
     * Convert Gregorian to Ethiopian using the andegna/calender package.
     */
    public static function toEthiopian($gregorianDate): array
    {
        if (is_string($gregorianDate)) {
            $gregorianDate = Carbon::parse($gregorianDate);
        }

        $ethDate = new EthiopianDateTime($gregorianDate->toDateTime());

        return [
            'year' => $ethDate->getYear(),
            'month' => $ethDate->getMonth(),
            'day' => $ethDate->getDay(),
            'month_name_am' => $ethDate->getTextualMonth(),
            'month_name_en' => (new self())->ethiopianMonths[$ethDate->getMonth()]['en'] ?? 'Unknown',
        ];
    }

    /**
     * Convert Ethiopian to Gregorian using the andegna/calender package.
     */
    public function toGregorian(int $day, int $month, int $year): Carbon
    {
        $ethDate = DateTimeFactory::of($year, $month, $day);

        return Carbon::instance($ethDate->toGregorian());
    }

    /**
     * Format date in Ethiopian calendar.
     */
    public function format($date, string $format = 'short', string $locale = 'am'): string
    {
        if (is_string($date)) {
            $date = Carbon::parse($date);
        }

        $ethDate = new EthiopianDateTime($date->toDateTime());

        switch ($format) {
            case 'short':
                return $ethDate->format('j/n/Y');
            case 'long':
                return $ethDate->format('F j, Y');
            case 'full':
                return $ethDate->format('l, F j, Y');
            default:
                return $ethDate->format('F j, Y');
        }
    }

    /**
     * Get Ethiopian year range.
     */
    public function getYearRange(int $yearsBefore = 5, int $yearsAfter = 3): array
    {
        $currentYear = (new EthiopianDateTime())->getYear();
        $range = [];

        for ($i = $currentYear - $yearsBefore; $i <= $currentYear + $yearsAfter; $i++) {
            $range[] = $i;
        }

        return $range;
    }

    /**
     * Convert to Ethiopian date string.
     */
    public function toString($date): string
    {
        if (is_string($date)) {
            $date = Carbon::parse($date);
        }

        $ethDate = new EthiopianDateTime($date->toDateTime());

        return $ethDate->format('F j, Y');
    }

    /**
     * Get Ethiopian month name.
     */
    public static function getEthiopianMonthName(int $month): string
    {
        $instance = new self();

        return $instance->ethiopianMonths[$month]['en'] ?? 'Unknown';
    }

    /**
     * Get Ethiopian year from a Gregorian date.
     */
    public static function getEthiopianYear(int $gregorianYear): int
    {
        $ethDate = new EthiopianDateTime(Carbon::create($gregorianYear)->toDateTime());

        return $ethDate->getYear();
    }

    /**
     * Get all Ethiopian month names (1-13) with Amharic.
     */
    public static function getAllEthiopianMonthNames(): array
    {
        $instance = new self();
        $options = [];

        foreach ($instance->ethiopianMonths as $key => $month) {
            $options[$key] = $month['en'] . ' (' . $month['am'] . ')';
        }

        return $options;
    }
}

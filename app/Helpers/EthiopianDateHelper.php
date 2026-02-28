<?php

namespace App\Helpers;

use Carbon\Carbon;

/**
 * Simple stub EthiopianDateHelper to avoid breaking existing code
 * This provides basic functionality without complex Ethiopian date conversion
 */
class EthiopianDateHelper
{
    /**
     * Standard Gregorian months
     */
    protected array $months = [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June',
        7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
    ];

    /**
     * Get current date as array (simplified)
     */
    public function now(): array
    {
        $now = now();
        return [
            'year' => $now->year,
            'month' => $now->month,
            'day' => $now->day,
            'month_name_am' => $this->months[$now->month] ?? '',
            'month_name_en' => $this->months[$now->month] ?? '',
        ];
    }

    /**
     * Convert Gregorian to Ethiopian (simplified - just returns Gregorian)
     */
    public static function toEthiopian(Carbon|string $gregorianDate): array
    {
        $instance = new self();
        
        if (is_string($gregorianDate)) {
            $gregorianDate = Carbon::parse($gregorianDate);
        }

        return [
            'year' => $gregorianDate->year,
            'month' => $gregorianDate->month,
            'day' => $gregorianDate->day,
            'month_name_am' => $instance->months[$gregorianDate->month] ?? '',
            'month_name_en' => $instance->months[$gregorianDate->month] ?? '',
        ];
    }

    /**
     * Convert Ethiopian to Gregorian (simplified - just returns as-is)
     */
    public function toGregorian(int $day, int $month, int $year): Carbon
    {
        return Carbon::create($year, $month, $day);
    }

    /**
     * Get standard months (Gregorian months)
     */
    public function getStandardMonths(bool $excludePagume = true): array
    {
        return $this->months;
    }

    /**
     * Get all months
     */
    public function getAllMonths(): array
    {
        return $this->months;
    }

    /**
     * Get months for contribution
     */
    public static function getMonthsForContribution(): array
    {
        $instance = new self();
        return $instance->months;
    }

    /**
     * Format date (simplified)
     */
    public function format($date, string $format = 'short', string $locale = 'am'): string
    {
        if (is_string($date)) {
            $date = Carbon::parse($date);
        }

        switch ($format) {
            case 'short':
                return $date->format('d/m/Y');
            case 'long':
                return $date->format('d F Y');
            case 'full':
                return $date->format('l, d F Y');
            default:
                return $date->format('d F Y');
        }
    }

    /**
     * Get year range
     */
    public function getYearRange(int $yearsBefore = 5, int $yearsAfter = 3): array
    {
        $currentYear = now()->year;
        $range = [];
        
        for ($i = $currentYear - $yearsBefore; $i <= $currentYear + $yearsAfter; $i++) {
            $range[] = $i;
        }
        
        return $range;
    }

    /**
     * Convert to string
     */
    public function toString($date): string
    {
        if (is_string($date)) {
            $date = Carbon::parse($date);
        }
        
        return $date->format('d F Y');
    }
}

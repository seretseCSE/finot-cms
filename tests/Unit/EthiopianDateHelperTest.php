<?php

namespace Tests\Unit;

use App\Helpers\EthiopianDateHelper;
use Carbon\Carbon;
use Tests\TestCase;

class EthiopianDateHelperTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    protected EthiopianDateHelper $helper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->helper = new EthiopianDateHelper();
    }

    /**
     * Test toEthiopian returns current date as array with expected keys.
     */
    public function test_to_ethiopian_returns_array_with_expected_keys(): void
    {
        $result = EthiopianDateHelper::toEthiopian(now());

        $this->assertIsArray($result);
        $this->assertArrayHasKey('year', $result);
        $this->assertArrayHasKey('month', $result);
        $this->assertArrayHasKey('day', $result);
        $this->assertArrayHasKey('month_name_am', $result);
        $this->assertArrayHasKey('month_name_en', $result);

        // Ethiopian year is approximately 7-8 years behind Gregorian
        $this->assertLessThan(now()->year, $result['year']);
        $this->assertGreaterThan(now()->year - 10, $result['year']);
    }

    /**
     * Test toEthiopian converts a known Gregorian date.
     */
    public function test_to_ethiopian_converts_gregorian_date(): void
    {
        $gregorianDate = Carbon::create(2024, 1, 15);
        $result = EthiopianDateHelper::toEthiopian($gregorianDate);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('year', $result);
        $this->assertArrayHasKey('month', $result);
        $this->assertArrayHasKey('day', $result);
        $this->assertArrayHasKey('month_name_am', $result);
        $this->assertArrayHasKey('month_name_en', $result);

        // 2024-01-15 Gregorian should be around Ethiopian year 2016
        $this->assertEquals(2016, $result['year']);
    }

    /**
     * Test toEthiopian handles string input.
     */
    public function test_to_ethiopian_handles_string_input(): void
    {
        $result = EthiopianDateHelper::toEthiopian('2024-06-20');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('year', $result);
        $this->assertArrayHasKey('month', $result);
        $this->assertArrayHasKey('day', $result);

        // Should return Ethiopian year, not Gregorian
        $this->assertLessThan(2024, $result['year']);
    }

    /**
     * Test toGregorian returns Carbon instance.
     */
    public function test_to_gregorian_returns_carbon_instance(): void
    {
        $result = $this->helper->toGregorian(15, 6, 2016);

        $this->assertInstanceOf(Carbon::class, $result);
    }

    /**
     * Test getMonthsForContribution returns 12 Ethiopian months.
     */
    public function test_get_months_for_contribution_returns_months(): void
    {
        $result = EthiopianDateHelper::getMonthsForContribution();

        $this->assertIsArray($result);
        $this->assertCount(12, $result);
        $this->assertArrayHasKey('Meskerem', $result);
    }

    /**
     * Test getAllMonthOptions returns both Ethiopian and Gregorian months.
     */
    public function test_get_all_month_options_returns_months(): void
    {
        $result = EthiopianDateHelper::getAllMonthOptions();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('Ethiopian Months', $result);
        $this->assertArrayHasKey('Gregorian Months', $result);
    }

    /**
     * Test format() returns non-empty string.
     */
    public function test_format_returns_non_empty_string(): void
    {
        $date = Carbon::create(2024, 6, 15);
        $result = $this->helper->format($date, 'short');

        $this->assertNotEmpty($result);
        $this->assertIsString($result);
    }

    /**
     * Test format() with long format returns non-empty string.
     */
    public function test_format_long_format(): void
    {
        $date = Carbon::create(2024, 6, 15);
        $result = $this->helper->format($date, 'long');

        $this->assertNotEmpty($result);
        $this->assertIsString($result);
    }

    /**
     * Test format() with full format returns non-empty string.
     */
    public function test_format_full_format(): void
    {
        $date = Carbon::create(2024, 6, 15);
        $result = $this->helper->format($date, 'full');

        $this->assertNotEmpty($result);
        $this->assertIsString($result);
    }

    /**
     * Test format() handles string input.
     */
    public function test_format_handles_string_input(): void
    {
        $result = $this->helper->format('2024-06-15', 'short');

        $this->assertNotEmpty($result);
        $this->assertIsString($result);
    }

    /**
     * Test getYearRange returns an array of Ethiopian years.
     */
    public function test_get_year_range_returns_correct_range(): void
    {
        $result = $this->helper->getYearRange(5, 3);

        $this->assertIsArray($result);
        $this->assertCount(9, $result); // 5 before + current + 3 after = 9

        // All values should be reasonable Ethiopian years
        foreach ($result as $year) {
            $this->assertIsInt($year);
            $this->assertGreaterThan(1900, $year);
        }
    }

    /**
     * Test toString() returns non-empty string.
     */
    public function test_to_string_returns_non_empty_string(): void
    {
        $date = Carbon::create(2024, 6, 15);
        $result = $this->helper->toString($date);

        $this->assertNotEmpty($result);
        $this->assertIsString($result);
    }

    /**
     * Test toString() handles string input.
     */
    public function test_to_string_handles_string_input(): void
    {
        $result = $this->helper->toString('2024-06-15');

        $this->assertNotEmpty($result);
        $this->assertIsString($result);
    }

    /**
     * Test Pagume month handling (13th month in Ethiopian calendar).
     */
    public function test_pagume_month_handling(): void
    {
        $result = EthiopianDateHelper::getEthiopianMonthName(13);

        $this->assertEquals('Pagume', $result);
    }

    /**
     * Test leap year handling.
     */
    public function test_leap_year_handling(): void
    {
        // Test leap year dates - the helper should handle any valid date
        $leapYearDate = Carbon::create(2024, 2, 29); // 2024 is a leap year
        $result = EthiopianDateHelper::toEthiopian($leapYearDate);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('year', $result);
        $this->assertArrayHasKey('month', $result);
        $this->assertArrayHasKey('day', $result);

        // Test non-leap year
        $nonLeapYearDate = Carbon::create(2023, 2, 28);
        $result = EthiopianDateHelper::toEthiopian($nonLeapYearDate);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('year', $result);
        $this->assertArrayHasKey('month', $result);
        $this->assertArrayHasKey('day', $result);
    }

    /**
     * Test date validation - valid dates.
     */
    public function test_valid_date_conversion(): void
    {
        $dates = [
            Carbon::create(2024, 1, 1),
            Carbon::create(2024, 6, 15),
            Carbon::create(2024, 12, 31),
        ];

        foreach ($dates as $date) {
            $result = EthiopianDateHelper::toEthiopian($date);
            $this->assertIsArray($result);
            $this->assertArrayHasKey('year', $result);
            $this->assertArrayHasKey('month', $result);
            $this->assertArrayHasKey('day', $result);
        }
    }

    /**
     * Test edge cases - first day of year.
     */
    public function test_first_day_of_year(): void
    {
        $date = Carbon::create(2024, 1, 1);
        $result = EthiopianDateHelper::toEthiopian($date);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('month', $result);
        $this->assertArrayHasKey('day', $result);
    }

    /**
     * Test edge cases - last day of year.
     */
    public function test_last_day_of_year(): void
    {
        $date = Carbon::create(2024, 12, 31);
        $result = EthiopianDateHelper::toEthiopian($date);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('month', $result);
        $this->assertArrayHasKey('day', $result);
    }
}

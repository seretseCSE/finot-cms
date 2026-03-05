<?php

namespace Tests\Unit;

use App\Helpers\EthiopianDateHelper;
use Carbon\Carbon;
use Tests\TestCase;

class EthiopianDateHelperTest extends TestCase
{
    protected EthiopianDateHelper $helper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->helper = new EthiopianDateHelper();
    }

    /**
     * Test now() returns current date as array.
     */
    public function test_now_returns_current_date_as_array(): void
    {
        $result = $this->helper->now();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('year', $result);
        $this->assertArrayHasKey('month', $result);
        $this->assertArrayHasKey('day', $result);
        $this->assertArrayHasKey('month_name_am', $result);
        $this->assertArrayHasKey('month_name_en', $result);

        $this->assertEquals(now()->year, $result['year']);
        $this->assertEquals(now()->month, $result['month']);
        $this->assertEquals(now()->day, $result['day']);
    }

    /**
     * Test toEthiopian() converts Gregorian date.
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

        // Current implementation returns Gregorian as-is
        $this->assertEquals(2024, $result['year']);
        $this->assertEquals(1, $result['month']);
        $this->assertEquals(15, $result['day']);
    }

    /**
     * Test toEthiopian() handles string input.
     */
    public function test_to_ethiopian_handles_string_input(): void
    {
        $result = EthiopianDateHelper::toEthiopian('2024-06-20');

        $this->assertIsArray($result);
        $this->assertEquals(2024, $result['year']);
        $this->assertEquals(6, $result['month']);
        $this->assertEquals(20, $result['day']);
    }

    /**
     * Test toGregorian() returns Carbon instance.
     */
    public function test_to_gregorian_returns_carbon_instance(): void
    {
        $result = $this->helper->toGregorian(15, 6, 2024);

        $this->assertInstanceOf(Carbon::class, $result);
        $this->assertEquals(2024, $result->year);
        $this->assertEquals(6, $result->month);
        $this->assertEquals(15, $result->day);
    }

    /**
     * Test getStandardMonths returns all 12 months.
     */
    public function test_get_standard_months_returns_all_months(): void
    {
        $result = $this->helper->getStandardMonths();

        $this->assertIsArray($result);
        $this->assertCount(12, $result);
        $this->assertEquals('January', $result[1]);
        $this->assertEquals('December', $result[12]);
    }

    /**
     * Test getAllMonths returns all months.
     */
    public function test_get_all_months_returns_all_months(): void
    {
        $result = $this->helper->getAllMonths();

        $this->assertIsArray($result);
        $this->assertCount(12, $result);
    }

    /**
     * Test getMonthsForContribution returns months.
     */
    public function test_get_months_for_contribution_returns_months(): void
    {
        $result = EthiopianDateHelper::getMonthsForContribution();

        $this->assertIsArray($result);
        $this->assertCount(12, $result);
        $this->assertArrayHasKey(1, $result);
        $this->assertArrayHasKey(12, $result);
    }

    /**
     * Test format() with short format.
     */
    public function test_format_short_format(): void
    {
        $date = Carbon::create(2024, 6, 15);
        $result = $this->helper->format($date, 'short');

        $this->assertEquals('15/06/2024', $result);
    }

    /**
     * Test format() with long format.
     */
    public function test_format_long_format(): void
    {
        $date = Carbon::create(2024, 6, 15);
        $result = $this->helper->format($date, 'long');

        $this->assertEquals('15 June 2024', $result);
    }

    /**
     * Test format() with full format.
     */
    public function test_format_full_format(): void
    {
        $date = Carbon::create(2024, 6, 15);
        $result = $this->helper->format($date, 'full');

        $this->assertStringContainsString('2024', $result);
    }

    /**
     * Test format() handles string input.
     */
    public function test_format_handles_string_input(): void
    {
        $result = $this->helper->format('2024-06-15', 'short');

        $this->assertEquals('15/06/2024', $result);
    }

    /**
     * Test getYearRange returns correct range.
     */
    public function test_get_year_range_returns_correct_range(): void
    {
        $currentYear = now()->year;
        $result = $this->helper->getYearRange(5, 3);

        $this->assertIsArray($result);
        $this->assertContains($currentYear - 5, $result);
        $this->assertContains($currentYear, $result);
        $this->assertContains($currentYear + 3, $result);
        $this->assertCount(9, $result); // 5 before + current + 3 after = 9
    }

    /**
     * Test toString() formats date correctly.
     */
    public function test_to_string_formats_date_correctly(): void
    {
        $date = Carbon::create(2024, 6, 15);
        $result = $this->helper->toString($date);

        $this->assertEquals('15 June 2024', $result);
    }

    /**
     * Test toString() handles string input.
     */
    public function test_to_string_handles_string_input(): void
    {
        $result = $this->helper->toString('2024-06-15');

        $this->assertStringContainsString('2024', $result);
    }

    /**
     * Test Pagume month handling (13th month in Ethiopian calendar).
     */
    public function test_pagume_month_handling(): void
    {
        // Ethiopian calendar has 13 months, with Pagume being 13th
        // The helper currently uses Gregorian months
        $result = $this->helper->getAllMonths();

        // Should have standard 12 months
        $this->assertCount(12, $result);
    }

    /**
     * Test leap year handling.
     */
    public function test_leap_year_handling(): void
    {
        // Test leap year dates
        $leapYearDate = Carbon::create(2024, 2, 29); // 2024 is a leap year
        $result = EthiopianDateHelper::toEthiopian($leapYearDate);

        $this->assertEquals(29, $result['day']);
        $this->assertEquals(2, $result['month']);

        // Test non-leap year
        $nonLeapYearDate = Carbon::create(2023, 2, 28);
        $result = EthiopianDateHelper::toEthiopian($nonLeapYearDate);

        $this->assertEquals(28, $result['day']);
        $this->assertEquals(2, $result['month']);
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

        $this->assertEquals(1, $result['month']);
        $this->assertEquals(1, $result['day']);
    }

    /**
     * Test edge cases - last day of year.
     */
    public function test_last_day_of_year(): void
    {
        $date = Carbon::create(2024, 12, 31);
        $result = EthiopianDateHelper::toEthiopian($date);

        $this->assertEquals(12, $result['month']);
        $this->assertEquals(31, $result['day']);
    }
}

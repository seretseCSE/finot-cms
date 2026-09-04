<?php

namespace App\Support;

/**
 * Monthly employment income tax per Income Tax (Amendment) Proclamation
 * No. 1395/2025 (effective Hamle 2017 E.C. / July 2025): first 2,000 ETB
 * exempt, six progressive bands up to 35%. Pension per private-organisation
 * scheme: employee 7%, employer 11% of basic salary.
 *
 * Update BRACKETS when the proclamation changes — everything else derives.
 */
class EthiopianIncomeTax
{
    public const PENSION_EMPLOYEE_RATE = 0.07;

    public const PENSION_EMPLOYER_RATE = 0.11;

    /**
     * [upper bound (null = ∞), marginal rate].
     *
     * @var list<array{0: float|null, 1: float}>
     */
    public const BRACKETS = [
        [2000.00, 0.00],
        [4000.00, 0.15],
        [7000.00, 0.20],
        [10000.00, 0.25],
        [14000.00, 0.30],
        [null, 0.35],
    ];

    /** Progressive tax on a monthly taxable income, rounded to cents. */
    public static function tax(float $monthlyTaxable): float
    {
        $tax = 0.0;
        $lower = 0.0;

        foreach (self::BRACKETS as [$upper, $rate]) {
            $slice = $upper === null
                ? max(0.0, $monthlyTaxable - $lower)
                : max(0.0, min($monthlyTaxable, $upper) - $lower);

            $tax += $slice * $rate;

            if ($upper === null || $monthlyTaxable <= $upper) {
                break;
            }

            $lower = $upper;
        }

        return round($tax, 2);
    }

    public static function employeePension(float $basicSalary): float
    {
        return round($basicSalary * self::PENSION_EMPLOYEE_RATE, 2);
    }

    public static function employerPension(float $basicSalary): float
    {
        return round($basicSalary * self::PENSION_EMPLOYER_RATE, 2);
    }
}

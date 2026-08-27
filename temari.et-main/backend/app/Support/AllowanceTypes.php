<?php

namespace App\Support;

/**
 * The fixed catalog of salary allowance names (mirrors data/allowance-types.json
 * and the frontend list — keep the three in sync).
 */
class AllowanceTypes
{
    public const ALL = [
        'Housing Allowance',
        'Transport Allowance',
        'Medical Allowance',
        'Education Allowance',
        'Meal Allowance',
        'Uniform Allowance',
        'Communication Allowance',
        'Overtime Allowance',
    ];
}

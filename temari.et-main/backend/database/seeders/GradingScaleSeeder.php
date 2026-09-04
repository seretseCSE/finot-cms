<?php

namespace Database\Seeders;

use App\Support\GradingDefaults;
use Illuminate\Database\Seeder;

/**
 * Platform default grading scales (school_id null). Definitions live in
 * App\Support\GradingDefaults so the resolver can also self-provision.
 */
class GradingScaleSeeder extends Seeder
{
    public function run(): void
    {
        GradingDefaults::provision();
    }
}

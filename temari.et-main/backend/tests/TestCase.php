<?php

namespace Tests;

use App\Support\Authorization\PermissionCatalog;
use App\Support\FinanceControls;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Carbon;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // These are memoized per process; each test gets a fresh database,
        // so the memos must not leak across tests.
        PermissionCatalog::flush();
        FinanceControls::flush();

        $this->pinClockToAddisEdgeIfRequested();
    }

    /**
     * CLOCK_EDGE=1 pins the clock to 22:30 UTC — inside the daily window
     * (21:00–24:00 UTC) where the UTC date is still the Addis day BEFORE.
     *
     * The app clock is UTC but every school-day judgement runs on Addis wall
     * time (App\Support\Ethiopia), so a test that builds a date with now()
     * instead of Ethiopia::today() passes 21 hours a day and fails for 3.
     * That is invisible on a normal run and reproducible under this flag.
     *
     * Keep the REAL date and shift only the time of day, so seeded relative
     * dates (academic years, terms) still line up.
     */
    private function pinClockToAddisEdgeIfRequested(): void
    {
        if (! filter_var(env('CLOCK_EDGE', false), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        Carbon::setTestNow(Carbon::now('UTC')->setTime(22, 30));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }
}

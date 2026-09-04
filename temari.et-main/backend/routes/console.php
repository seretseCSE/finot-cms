<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Device attendance mode: after each branch's cutoff, unscanned students are
// marked absent (idempotent — fills missing rows only) which fires the
// guardian alerts. Cheap when no branch qualifies, so a tight cadence is fine.
Schedule::command('attendance:auto-absent')->everyTenMinutes();

// The finance day, on Addis wall time. Order matters: bill the new periods
// first, then accrue penalties on what is overdue, then remind — so the
// morning's reminders always quote today's true balance. All three are
// idempotent (unique keys / pure recomputes); a missed day self-heals on the
// next run.
Schedule::command('fees:generate-recurring')->dailyAt('01:30')->timezone('Africa/Addis_Ababa');
Schedule::command('fees:apply-penalties')->dailyAt('02:00')->timezone('Africa/Addis_Ababa');
Schedule::command('fees:send-reminders')->dailyAt('08:30')->timezone('Africa/Addis_Ababa');

// The in-app feed is delivery state, not history — keep the table bounded.
Schedule::command('notifications:prune')->dailyAt('03:00')->timezone('Africa/Addis_Ababa');

// The tutoring marketplace day: bill the new Ethiopian month first, then
// auto-confirm sessions past the 72h window, then (only when the operator
// enables auto-release) settle finished months into tutor wallets. All
// idempotent — a missed day self-heals on the next run.
Schedule::command('tutoring:generate-cycles')->dailyAt('01:45')->timezone('Africa/Addis_Ababa');
Schedule::command('tutoring:auto-confirm')->hourly();
Schedule::command('tutoring:release-due')->dailyAt('02:15')->timezone('Africa/Addis_Ababa');

// Temari AI: Monday-morning leadership briefings (School Plan schools) and
// premium-parent child digests — both in-app only, week-deduped.
Schedule::command('ai:weekly-briefings')->weeklyOn(1, '06:00')->timezone('Africa/Addis_Ababa');
Schedule::command('ai:parent-digests')->weeklyOn(5, '16:00')->timezone('Africa/Addis_Ababa');

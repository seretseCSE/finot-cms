<?php

use App\Actions\SaveAcademicYearAction;
use App\Enums\Role;
use App\Models\Term;
use App\Support\DateFormatter;
use App\Support\EthiopianDate;
use App\Support\NotificationCatalog;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

// ── Ethiopian calendar arithmetic (the anchor everything trusts) ─────────

it('round-trips Gregorian ↔ Ethiopian across new-year and Pagume edges', function () {
    // EC 2018 new year = 11 Sep 2025 (GC 2026 is a leap year → still the 11th
    // because the rule looks at the year FOLLOWING the Meskerem-1 Gregorian year).
    expect(EthiopianDate::fromGregorian(CarbonImmutable::parse('2025-09-11')))
        ->toBe(['year' => 2018, 'month' => 1, 'day' => 1]);

    // The day before is Pagume of EC 2017 (5 days that year → Pagume 6 of a
    // leap EC year lands 2026-09-10 ahead of EC 2019).
    expect(EthiopianDate::fromGregorian(CarbonImmutable::parse('2025-09-10'))['month'])->toBe(13);

    // 22 Jul 2026 = Hamle 15, 2018 — the worked example in every spec.
    expect(EthiopianDate::fromGregorian(CarbonImmutable::parse('2026-07-22')))
        ->toBe(['year' => 2018, 'month' => 11, 'day' => 15]);

    // Full-year round trip: every day maps back to itself.
    $day = CarbonImmutable::parse('2025-09-01');
    for ($i = 0; $i < 400; $i++) {
        $eth = EthiopianDate::fromGregorian($day);
        expect(EthiopianDate::toGregorian($eth['year'], $eth['month'], $eth['day'])->toDateString())
            ->toBe($day->toDateString());
        $day = $day->addDay();
    }
});

// ── DateFormatter ────────────────────────────────────────────────────────

it('formats dates per calendar and locale', function () {
    expect(DateFormatter::date('2026-07-22'))->toBe('Hamle 15, 2018');
    expect(DateFormatter::date('2026-07-22', 'gregorian'))->toBe('July 22, 2026');
    expect(DateFormatter::date('2026-07-22', 'ethiopian', 'am'))->toBe('ሐምሌ 15 ቀን 2018');
    expect(DateFormatter::date('2026-07-22', 'ethiopian', 'om'))->toBe('Adooleessa 15, 2018');
    expect(DateFormatter::date('2026-07-22', 'ethiopian', 'en', withEra: true))->toBe('Hamle 15, 2018 E.C.');
    expect(DateFormatter::date(null))->toBe('');
});

it('always prints BOTH calendars on the official dual form', function () {
    expect(DateFormatter::dual('2026-07-22'))
        ->toBe('Hamle 15, 2018 E.C. (July 22, 2026 G.C.)');
});

it('renders Addis wall time and the Ethiopian dawn-count clock', function () {
    // A UTC instant renders on Addis wall time (+3) — Ethiopian clock by DEFAULT.
    expect(DateFormatter::time(CarbonImmutable::parse('2026-07-22T05:00:00Z')))->toBe('2:00 morning');
    expect(DateFormatter::time(CarbonImmutable::parse('2026-07-22T05:00:00Z'), 'standard'))->toBe('8:00 AM');

    // The Ethiopian clock counts from dawn: 8:00 → 2:00 morning; 13:30 → 7:30 afternoon.
    expect(DateFormatter::time('08:00', 'ethiopian'))->toBe('2:00 morning');
    expect(DateFormatter::time('13:30', 'ethiopian', 'am'))->toBe('7:30 ከሰዓት');
    expect(DateFormatter::time('00:15', 'ethiopian'))->toBe('6:15 night');
    expect(DateFormatter::time('18:00', 'ethiopian'))->toBe('12:00 evening');

    // An instant crossing midnight in Addis lands on the NEXT Ethiopian day.
    expect(DateFormatter::date(CarbonImmutable::parse('2026-07-22T22:30:00Z')))->toBe('Hamle 16, 2018');
});

// ── Settings: school default, branch override, exposure ──────────────────

it('defaults every school to the Ethiopian calendar AND the Ethiopian clock', function () {
    $branch = makeBranch();

    expect($branch->school->calendarMode())->toBe('ethiopian');
    expect($branch->school->clockMode())->toBe('ethiopian');
    expect($branch->effectiveCalendarMode())->toBe('ethiopian');
    expect($branch->effectiveClockMode())->toBe('ethiopian');
});

it('lets a principal set the school calendar and a branch override it', function () {
    $branch = makeBranch();
    $principal = schoolPrincipal($branch);
    Sanctum::actingAs($principal);

    $this->withHeaders(branchContext($branch))
        ->patchJson("/api/v1/schools/{$branch->school_id}/settings", [
            'calendar_mode' => 'gregorian',
            'clock_mode' => 'ethiopian',
        ])->assertOk()
        ->assertJsonPath('data.calendar_mode', 'gregorian')
        ->assertJsonPath('data.clock_mode', 'ethiopian');

    expect($branch->fresh()->effectiveCalendarMode())->toBe('gregorian');

    // Branch override pins back to Ethiopian for this branch only.
    $this->withHeaders(branchContext($branch))
        ->patchJson("/api/v1/branches/{$branch->id}/settings", ['calendar_mode' => 'ethiopian'])
        ->assertOk()
        ->assertJsonPath('data.effective.calendar_mode', 'ethiopian')
        ->assertJsonPath('data.overrides.calendar_mode', 'ethiopian')
        ->assertJsonPath('data.school_defaults.calendar_mode', 'gregorian');

    // Clearing the override re-inherits the school default.
    $this->withHeaders(branchContext($branch))
        ->patchJson("/api/v1/branches/{$branch->id}/settings", ['calendar_mode' => null])
        ->assertOk()
        ->assertJsonPath('data.effective.calendar_mode', 'gregorian');
});

it('rejects unknown calendar and clock values', function () {
    $branch = makeBranch();
    Sanctum::actingAs(schoolPrincipal($branch));

    $this->withHeaders(branchContext($branch))
        ->patchJson("/api/v1/schools/{$branch->school_id}/settings", ['calendar_mode' => 'julian'])
        ->assertUnprocessable();
    $this->withHeaders(branchContext($branch))
        ->patchJson("/api/v1/schools/{$branch->school_id}/settings", ['clock_mode' => 'martian'])
        ->assertUnprocessable();
});

it('carries the effective modes on the context-switcher payload', function () {
    $branch = makeBranch();
    $school = $branch->school;
    $school->update(['settings' => ['calendar_mode' => 'gregorian']]);
    $branch->update(['settings' => ['clock_mode' => 'standard']]);

    Sanctum::actingAs(memberOf($branch, Role::Director));

    $schools = $this->getJson('/api/v1/auth/contexts')->assertOk()->json('data.schools');

    expect($schools[0]['calendar_mode'])->toBe('gregorian');
    expect($schools[0]['clock_mode'])->toBe('ethiopian');                   // the default
    expect($schools[0]['branches'][0]['calendar_mode'])->toBe('gregorian'); // inherits
    expect($schools[0]['branches'][0]['clock_mode'])->toBe('standard');     // pinned
});

// ── Semester windows at year creation ────────────────────────────────────

it('splits a Meskerem–Sene year into the conventional semester windows', function () {
    // Meskerem 1 – Sene 30, 2018 E.C. → Sem 1 Meskerem–Tir, Sem 2 Yekatit–Sene.
    $windows = SaveAcademicYearAction::termWindows('2025-09-11', '2026-07-07', 2);

    expect($windows)->toBe([
        ['starts_on' => '2025-09-11', 'ends_on' => '2026-02-07'],  // Meskerem 1 – Tir 30
        ['starts_on' => '2026-02-08', 'ends_on' => '2026-07-07'],  // Yekatit 1 – Sene 30
    ]);

    // Four quarters: 3+3+2+2 whole months, gapless.
    $quarters = SaveAcademicYearAction::termWindows('2025-09-11', '2026-07-07', 4);
    expect($quarters)->toHaveCount(4);
    expect($quarters[0]['starts_on'])->toBe('2025-09-11');
    expect($quarters[3]['ends_on'])->toBe('2026-07-07');
    foreach (range(1, 3) as $i) {
        expect($quarters[$i]['starts_on'])
            ->toBe(CarbonImmutable::parse($quarters[$i - 1]['ends_on'])->addDay()->toDateString());
    }

    // Unaligned window falls back to an even day split, still gapless.
    $uneven = SaveAcademicYearAction::termWindows('2025-09-15', '2026-06-20', 2);
    expect($uneven[0]['starts_on'])->toBe('2025-09-15');
    expect($uneven[1]['ends_on'])->toBe('2026-06-20');
    expect($uneven[1]['starts_on'])
        ->toBe(CarbonImmutable::parse($uneven[0]['ends_on'])->addDay()->toDateString());
});

it('seeds new-year semesters with dates and class hours', function () {
    $branch = makeBranch();
    Sanctum::actingAs(schoolPrincipal($branch));

    $terms = $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/academic-years', [
            'branch_id' => $branch->id,
            'name' => '2018 E.C.',
            'starts_on' => '2025-09-11',
            'ends_on' => '2026-07-07',
            'status' => 'planned',
            'terms_count' => 2,
        ])->assertCreated()->json('data.terms');

    expect($terms)->toHaveCount(2);
    expect($terms[0]['starts_on'])->toBe('2025-09-11');
    expect($terms[0]['ends_on'])->toBe('2026-02-07');
    expect($terms[1]['starts_on'])->toBe('2026-02-08');
    expect($terms[1]['ends_on'])->toBe('2026-07-07');
    // First year ever: the standard school day.
    expect(substr((string) $terms[0]['class_starts_at'], 0, 5))->toBe('08:30');
    expect(substr((string) $terms[0]['class_ends_at'], 0, 5))->toBe('14:45');

    // The NEXT year inherits the branch's running bell schedule (latest term).
    Term::query()->where('branch_id', $branch->id)->orderByDesc('id')->first()
        ->update(['class_starts_at' => '08:00', 'class_ends_at' => '15:30', 'period_minutes' => 40]);

    $nextTerms = $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/academic-years', [
            'branch_id' => $branch->id,
            'name' => '2019 E.C.',
            'starts_on' => '2026-09-11',
            'ends_on' => '2027-07-07',
            'status' => 'planned',
            'terms_count' => 2,
        ])->assertCreated()->json('data.terms');

    expect(substr((string) $nextTerms[0]['class_starts_at'], 0, 5))->toBe('08:00');
    expect($nextTerms[0]['period_minutes'])->toBe(40);
});

// ── Notification date localization ───────────────────────────────────────

it('renders bare ISO-date params as human dates in the reader language and school calendar', function () {
    $eth = NotificationCatalog::localizeParams(['date' => '2026-07-22'], 'am');
    expect($eth['date'])->toBe('ሐምሌ 15 ቀን 2018');

    $greg = NotificationCatalog::localizeParams(
        ['date' => '2026-07-22'],
        'en',
        ['calendar' => 'gregorian', 'clock' => 'standard'],
    );
    expect($greg['date'])->toBe('July 22, 2026');

    // Non-date strings pass through untouched.
    expect(NotificationCatalog::localizeParams(['name' => 'Abebe'], 'en')['name'])->toBe('Abebe');
});

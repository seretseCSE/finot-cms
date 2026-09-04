<?php

use App\Enums\CycleStatus;
use App\Enums\TutoringSessionStatus;
use App\Enums\TutorStatus;
use App\Models\ParentProfile;
use App\Models\PlatformSetting;
use App\Models\Student;
use App\Models\StudentGuardian;
use App\Models\Subject;
use App\Models\TutoringCycle;
use App\Models\TutoringEngagement;
use App\Models\TutoringRequest;
use App\Models\TutoringSession;
use App\Models\TutorPayout;
use App\Models\TutorProfile;
use App\Models\User;
use App\Support\PaymentGateways;
use Carbon\CarbonImmutable;
use Database\Seeders\GradeLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SubjectSeeder;
use Illuminate\Http\Testing\File;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

/*
 * Guard rail for the tutoring marketplace: application → review → public
 * directory, hire → engagement → funded Ethiopian-month escrow → sessions →
 * release (commission + credit carry) → reviews → payouts — plus the
 * relationship-lane isolation (strangers see nothing, ever).
 */

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(GradeLevelSeeder::class);
    $this->seed(SubjectSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    PlatformSetting::set(PaymentGateways::SETTING_KEY, [
        'fake' => ['enabled' => true, 'purposes' => ['tutoring_cycle', 'profile_boost']],
    ]);
});

function draftApplication(User $user, array $overrides = []): TutorProfile
{
    Sanctum::actingAs($user);

    test()->putJson('/api/v1/tutoring/profile', array_merge([
        'headline' => 'Mathematics tutor for Grades 7–12',
        'bio' => 'Experienced and patient math tutor.',
        'hourly_rate' => 300,
        'mode' => 'both',
        'city' => 'Addis Ababa',
        'languages' => ['am', 'en'],
        'fayda_id' => '614'.str_pad((string) random_int(1, 9999999999), 10, '0', STR_PAD_LEFT),
        'subjects' => [['subject_id' => Subject::query()->value('id'), 'grade_sorts' => []]],
    ], $overrides))->assertOk();

    Storage::fake(config('filesystems.default'));

    test()->postJson('/api/v1/tutoring/profile/attachments', [
        'name' => 'Degree.pdf',
        'file' => File::fake()->create('degree.pdf', 100, 'application/pdf'),
    ])->assertCreated();

    return $user->tutorProfile()->firstOrFail();
}

function approvedTutor(): TutorProfile
{
    $profile = draftApplication(User::factory()->create());

    Sanctum::actingAs($profile->user);
    test()->postJson('/api/v1/tutoring/profile/submit')->assertOk();

    Sanctum::actingAs(platformAdmin());
    test()->postJson("/api/v1/marketplace/tutors/{$profile->id}/approve")->assertOk();

    return $profile->fresh();
}

/** Guardian + child pair the family lane hires with. */
function hiringFamily(): array
{
    $guardian = User::factory()->create();
    $parent = ParentProfile::create(['user_id' => $guardian->id]);
    $student = Student::create(['first_name' => 'Liya', 'father_name' => 'Tesfaye', 'gender' => 'female']);
    StudentGuardian::create([
        'student_id' => $student->id, 'parent_id' => $parent->id,
        'relationship' => 'mother', 'is_active' => true,
    ]);

    return [$guardian, $student];
}

/** Full happy path up to a FUNDED current cycle. */
function fundedEngagement(): array
{
    $tutor = approvedTutor();
    [$guardian, $student] = hiringFamily();

    Sanctum::actingAs($guardian);
    test()->postJson('/api/v1/me/tutoring/requests', [
        'tutor_slug' => $tutor->slug,
        'student_id' => $student->id,
        'subject_ids' => [Subject::query()->value('id')],
        'mode' => 'online',
        'sessions_per_week' => 2,
        'hours_per_session' => 1,
    ])->assertCreated();

    $request = TutoringRequest::firstOrFail();

    Sanctum::actingAs($tutor->user);
    test()->postJson("/api/v1/tutoring/requests/{$request->id}/accept")->assertOk();

    $engagement = TutoringEngagement::firstOrFail();
    $cycle = TutoringCycle::firstOrFail();

    Sanctum::actingAs($guardian);
    $txRef = test()->postJson("/api/v1/me/tutoring/cycles/{$cycle->id}/pay", ['gateway' => 'fake'])
        ->assertOk()->json('data.tx_ref');
    test()->postJson("/api/v1/payments/simulate/{$txRef}", ['outcome' => 'paid'])->assertOk();

    return [$tutor, $guardian, $engagement, $cycle->fresh()];
}

// ───────────────────────── application + review ─────────────────────────

it('walks an application from draft to the public directory', function () {
    $profile = draftApplication(User::factory()->create());

    // Not listed while draft.
    $this->getJson('/api/v1/public/tutors')->assertOk()->assertJsonCount(0, 'data');

    Sanctum::actingAs($profile->user);
    $this->postJson('/api/v1/tutoring/profile/submit')->assertOk();
    expect($profile->fresh()->status)->toBe(TutorStatus::Pending);

    // Vetting is platform-only.
    Sanctum::actingAs($profile->user);
    $this->postJson("/api/v1/marketplace/tutors/{$profile->id}/approve")->assertForbidden();

    Sanctum::actingAs(platformAdmin());
    $this->getJson('/api/v1/marketplace/tutors?status=pending')->assertOk()->assertJsonCount(1, 'data');
    // The reviewer sees the decrypted Fayda for manual vetting.
    $this->getJson("/api/v1/marketplace/tutors/{$profile->id}")
        ->assertOk()->assertJsonPath('data.fayda_id', $profile->fresh()->fayda_id);
    $this->postJson("/api/v1/marketplace/tutors/{$profile->id}/approve")->assertOk();

    $profile = $profile->fresh();
    expect($profile->status)->toBe(TutorStatus::Approved)->and($profile->slug)->not->toBeNull();

    // Now publicly listed, anonymously.
    auth()->forgetGuards();
    $this->getJson('/api/v1/public/tutors')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson("/api/v1/public/tutors/{$profile->slug}")
        ->assertOk()
        ->assertJsonPath('data.verified', true);
});

it('rejects a second application with the same Fayda ID', function () {
    draftApplication(User::factory()->create(), ['fayda_id' => '6140212345678']);

    Sanctum::actingAs(User::factory()->create());
    $this->putJson('/api/v1/tutoring/profile', [
        'headline' => 'Another tutor', 'mode' => 'online', 'fayda_id' => '6140212345678',
    ])->assertStatus(422);
});

it('supports decline with reason and re-application', function () {
    $profile = draftApplication(User::factory()->create());

    Sanctum::actingAs($profile->user);
    $this->postJson('/api/v1/tutoring/profile/submit')->assertOk();

    Sanctum::actingAs(platformAdmin());
    $this->postJson("/api/v1/marketplace/tutors/{$profile->id}/decline", ['reason' => 'Unreadable documents'])
        ->assertOk();

    expect($profile->fresh()->status)->toBe(TutorStatus::Declined);

    // Declined profiles may edit + resubmit.
    Sanctum::actingAs($profile->user);
    $this->postJson('/api/v1/tutoring/profile/submit')->assertOk();
    expect($profile->fresh()->status)->toBe(TutorStatus::Pending);
});

it('ranks boosted tutors first in the recommended ordering', function () {
    $a = approvedTutor();
    $b = approvedTutor();
    $b->update(['boosted_until' => now()->addDays(7), 'rating_avg' => null]);
    $a->update(['rating_avg' => 4.9, 'rating_count' => 12]);

    auth()->forgetGuards();
    $slugs = collect($this->getJson('/api/v1/public/tutors')->json('data'))->pluck('slug');

    expect($slugs->first())->toBe($b->fresh()->slug);
});

// ───────────────────────── the money loop ─────────────────────────

it('runs the full escrow loop: hire → fund → sessions → release with commission and credit carry', function () {
    [$tutor, $guardian, $engagement, $cycle] = fundedEngagement();

    expect($cycle->status)->toBe(CycleStatus::Funded)
        ->and((float) $cycle->gross_amount)->toBe(2400.0); // 2×1h×4 weeks × 300

    // The tutor schedules two upcoming 1h sessions, then logs them after
    // they happen — travel to month start, book ahead, travel past them.
    Sanctum::actingAs($tutor->user);
    $when = CarbonImmutable::parse($cycle->starts_on, 'Africa/Addis_Ababa')->setTime(17, 0);
    $this->travelTo($when);

    foreach ([1, 3] as $offset) {
        $this->postJson("/api/v1/tutoring/engagements/{$engagement->id}/sessions", [
            'scheduled_at' => $when->addDays($offset)->toDateTimeString(),
            'duration_hours' => 1,
        ])->assertCreated();
    }

    $this->travelTo($when->addDays(4));
    $sessions = TutoringSession::orderBy('id')->get();
    foreach ($sessions as $session) {
        $this->postJson("/api/v1/tutoring/sessions/{$session->id}/log")->assertOk();
    }

    // Family confirms one; the other auto-confirms after 72h.
    Sanctum::actingAs($guardian);
    $this->postJson("/api/v1/me/tutoring/sessions/{$sessions[0]->id}/confirm")->assertOk();

    $this->travel(4)->days();
    $this->artisan('tutoring:auto-confirm')->assertSuccessful();
    expect($sessions[1]->fresh()->status)->toBe(TutoringSessionStatus::Confirmed);

    // Release blocked until the month ends.
    Sanctum::actingAs(platformAdmin());
    $this->postJson("/api/v1/marketplace/cycles/{$cycle->id}/release")->assertStatus(422);

    $this->travelTo(CarbonImmutable::parse($cycle->ends_on)->addDays(2));
    $this->postJson("/api/v1/marketplace/cycles/{$cycle->id}/release")->assertOk();

    $cycle = $cycle->fresh();
    // 2 confirmed hours × 300 = 600; commission 10% = 60; net 540;
    // unfulfilled 2400−600 = 1800 carries as credit.
    expect((float) $cycle->confirmed_value)->toBe(600.0)
        ->and((float) $cycle->commission_amount)->toBe(60.0)
        ->and((float) $cycle->released_amount)->toBe(540.0)
        ->and((float) $cycle->credit_carried)->toBe(1800.0)
        ->and((float) $tutor->fresh()->wallet_balance)->toBe(540.0);

    // Next month consumes the credit before asking for money.
    $this->artisan('tutoring:generate-cycles')->assertSuccessful();
    $next = TutoringCycle::orderByDesc('id')->first();
    expect((float) $next->credit_applied)->toBe(1800.0)
        ->and((float) $next->amount_due)->toBe(600.0);
});

it('blocks sessions on an unpaid month', function () {
    $tutor = approvedTutor();
    [$guardian, $student] = hiringFamily();

    Sanctum::actingAs($guardian);
    $this->postJson('/api/v1/me/tutoring/requests', [
        'tutor_slug' => $tutor->slug, 'student_id' => $student->id,
        'subject_ids' => [Subject::query()->value('id')],
        'mode' => 'online', 'sessions_per_week' => 2, 'hours_per_session' => 1,
    ])->assertCreated();

    Sanctum::actingAs($tutor->user);
    $this->postJson('/api/v1/tutoring/requests/'.TutoringRequest::value('id').'/accept')->assertOk();

    $cycle = TutoringCycle::firstOrFail();
    expect($cycle->status)->toBe(CycleStatus::AwaitingPayment);

    $this->postJson('/api/v1/tutoring/engagements/'.TutoringEngagement::value('id').'/sessions', [
        'scheduled_at' => now('Africa/Addis_Ababa')->addDay()->toDateTimeString(),
        'duration_hours' => 1,
    ])->assertStatus(422);
});

it('freezes only a disputed session and pays the rest on resolution', function () {
    [$tutor, $guardian, $engagement, $cycle] = fundedEngagement();

    Sanctum::actingAs($tutor->user);
    $when = CarbonImmutable::parse($cycle->starts_on, 'Africa/Addis_Ababa')->setTime(17, 0);
    $this->travelTo($when);

    $sessionId = $this->postJson("/api/v1/tutoring/engagements/{$engagement->id}/sessions", [
        'scheduled_at' => $when->addDay()->toDateTimeString(), 'duration_hours' => 1,
    ])->assertCreated()->json('data.id');

    $this->travelTo($when->addDays(2));
    $this->postJson("/api/v1/tutoring/sessions/{$sessionId}/log")->assertOk();

    Sanctum::actingAs($guardian);
    $this->postJson("/api/v1/me/tutoring/sessions/{$sessionId}/dispute", ['reason' => 'No lesson happened'])
        ->assertOk();

    // A disputed session blocks the release…
    Sanctum::actingAs(platformAdmin());
    $this->travelTo(CarbonImmutable::parse($cycle->ends_on)->addDay());
    $this->postJson("/api/v1/marketplace/cycles/{$cycle->id}/release")->assertStatus(422);

    // …until Temari.et upholds it (value canceled) — then the release pays 0.
    $this->postJson("/api/v1/marketplace/sessions/{$sessionId}/resolve", ['resolution' => 'upheld'])->assertOk();
    $this->postJson("/api/v1/marketplace/cycles/{$cycle->id}/release")->assertOk();

    expect((float) $cycle->fresh()->released_amount)->toBe(0.0)
        ->and((float) $tutor->fresh()->wallet_balance)->toBe(0.0);
});

// ───────────────────────── reviews + payouts ─────────────────────────

it('allows reviews only on released cycles and aggregates the public rating', function () {
    [$tutor, $guardian, $engagement, $cycle] = fundedEngagement();

    Sanctum::actingAs($guardian);
    $this->postJson("/api/v1/me/tutoring/cycles/{$cycle->id}/review", ['rating' => 5])
        ->assertStatus(422); // not released yet

    Sanctum::actingAs(platformAdmin());
    $this->travelTo(CarbonImmutable::parse($cycle->ends_on)->addDay());
    $this->postJson("/api/v1/marketplace/cycles/{$cycle->id}/release")->assertOk();

    Sanctum::actingAs($guardian);
    $this->postJson("/api/v1/me/tutoring/cycles/{$cycle->id}/review", ['rating' => 4, 'comment' => 'Great tutor'])
        ->assertCreated();
    // One review per cycle per direction.
    $this->postJson("/api/v1/me/tutoring/cycles/{$cycle->id}/review", ['rating' => 5])->assertStatus(422);

    expect((float) $tutor->fresh()->rating_avg)->toBe(4.0)
        ->and($tutor->fresh()->rating_count)->toBe(1);
});

it('reserves the wallet at payout approval and reverses on failure', function () {
    [$tutor] = fundedEngagement();

    // Give the wallet money via a released cycle.
    $cycle = TutoringCycle::firstOrFail();
    Sanctum::actingAs($tutor->user);
    $when = CarbonImmutable::parse($cycle->starts_on, 'Africa/Addis_Ababa')->setTime(17, 0);
    $this->travelTo($when);
    $sid = $this->postJson('/api/v1/tutoring/engagements/'.TutoringEngagement::value('id').'/sessions', [
        'scheduled_at' => $when->addDay()->toDateTimeString(), 'duration_hours' => 2,
    ])->assertCreated()->json('data.id');
    $this->travelTo($when->addDays(2));
    $this->postJson("/api/v1/tutoring/sessions/{$sid}/log")->assertOk();
    $this->travel(4)->days();
    $this->artisan('tutoring:auto-confirm');
    Sanctum::actingAs(platformAdmin());
    $this->travelTo(CarbonImmutable::parse($cycle->ends_on)->addDay());
    $this->postJson("/api/v1/marketplace/cycles/{$cycle->id}/release")->assertOk();

    $balance = (float) $tutor->fresh()->wallet_balance; // 2h×300×0.9 = 540

    // No payout account → refused.
    Sanctum::actingAs($tutor->user);
    $this->postJson('/api/v1/tutoring/payouts', ['amount' => 200])->assertStatus(422);

    $this->putJson('/api/v1/tutoring/profile/payout-account', [
        'payout_bank_code' => '855', 'payout_bank_name' => 'telebirr',
        'payout_account_number' => '0911000000', 'payout_account_name' => 'Tutor Test',
    ])->assertOk();

    // Over-balance refused.
    $this->postJson('/api/v1/tutoring/payouts', ['amount' => $balance + 1])->assertStatus(422);
    $payoutId = $this->postJson('/api/v1/tutoring/payouts', ['amount' => 200])->assertCreated()->json('data.id');

    Sanctum::actingAs(platformAdmin());
    $this->postJson("/api/v1/marketplace/payouts/{$payoutId}/approve")->assertOk();
    expect((float) $tutor->fresh()->wallet_balance)->toBe($balance - 200);

    // Failed transfer credits the reservation back.
    $this->postJson("/api/v1/marketplace/payouts/{$payoutId}/reverse", ['status' => 'failed', 'reason' => 'Bad account'])
        ->assertOk();
    expect((float) $tutor->fresh()->wallet_balance)->toBe($balance)
        ->and(TutorPayout::first()->status->value)->toBe('failed');
});

// ───────────────────────── isolation ─────────────────────────

it('keeps strangers out of every marketplace surface', function () {
    [$tutor, , $engagement, $cycle] = fundedEngagement();
    $stranger = User::factory()->create();

    Sanctum::actingAs($stranger);

    $this->getJson('/api/v1/tutoring/dashboard')->assertForbidden(); // no profile
    $this->getJson("/api/v1/tutoring/engagements/{$engagement->id}")->assertNotFound();
    $this->getJson("/api/v1/tutoring/engagements/{$engagement->id}/sessions")->assertNotFound();
    $this->postJson("/api/v1/me/tutoring/cycles/{$cycle->id}/pay", ['gateway' => 'fake'])->assertNotFound();
    $this->getJson('/api/v1/marketplace/tutors')->assertForbidden();
    $this->getJson('/api/v1/marketplace/cycles')->assertForbidden();
    $this->getJson('/api/v1/marketplace/payouts')->assertForbidden();
});

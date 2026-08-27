<?php

use App\Enums\CycleStatus;
use App\Enums\GatewayPurpose;
use App\Enums\GatewayTransactionStatus;
use App\Enums\TutorStatus;
use App\Models\GatewayTransaction;
use App\Models\PlatformSetting;
use App\Models\TutoringEngagement;
use App\Models\TutorProfile;
use App\Models\User;
use App\Services\Tutoring\CycleBiller;
use App\Support\Marketplace;
use App\Support\PaymentGateways;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

/*
 * Guard rail for the payment gateway layer: the operator purposes matrix,
 * the checkout → settle pipeline (fake driver), fulfilment idempotency,
 * webhook safety and the platform settings endpoints. School fees never
 * appear here by design — the gateway carries Temari.et's own money only.
 */

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

/** Enable the simulator for the given purposes (platform matrix). */
function enableFakeGateway(array $purposes = ['tutoring_cycle', 'profile_boost']): void
{
    PlatformSetting::set(PaymentGateways::SETTING_KEY, [
        'fake' => ['enabled' => true, 'purposes' => $purposes],
    ]);
}

/** A funded-ready marketplace pair: approved tutor + engagement + first cycle. */
function marketplacePair(): array
{
    $tutorUser = User::factory()->create();
    $tutor = TutorProfile::create([
        'user_id' => $tutorUser->id,
        'headline' => 'Math tutor', 'bio' => 'bio', 'hourly_rate' => 300,
        'mode' => 'online', 'status' => TutorStatus::Approved->value, 'slug' => 'math-tutor',
    ]);

    $payer = User::factory()->create();

    $engagement = TutoringEngagement::create([
        'tutor_profile_id' => $tutor->id,
        'payer_user_id' => $payer->id,
        'subjects' => [],
        'mode' => 'online',
        'sessions_per_week' => 2,
        'hours_per_session' => 1,
        'hourly_rate' => 300,
        'commission_percent' => 10,
        'status' => 'active',
        'started_on' => now()->toDateString(),
    ]);

    $cycle = app(CycleBiller::class)->ensureCycleFor($engagement);

    return [$tutor, $payer, $engagement, $cycle];
}

it('offers only gateways enabled for the purpose', function () {
    enableFakeGateway(['profile_boost']);

    expect(PaymentGateways::availableFor(GatewayPurpose::ProfileBoost))->toContain('fake')
        ->and(PaymentGateways::availableFor(GatewayPurpose::TutoringCycle))->not->toContain('fake')
        // Chapa has no credentials in testing → never offered even though
        // the default matrix enables it.
        ->and(PaymentGateways::availableFor(GatewayPurpose::TutoringCycle))->not->toContain('chapa');
});

it('rejects a checkout on a gateway not enabled for the purpose', function () {
    enableFakeGateway(['profile_boost']); // tutoring NOT ticked
    [, $payer, , $cycle] = marketplacePair();

    Sanctum::actingAs($payer);

    $this->postJson("/api/v1/me/tutoring/cycles/{$cycle->id}/pay", ['gateway' => 'fake'])
        ->assertStatus(422);
});

it('funds a tutoring cycle through checkout + simulated payment, exactly once', function () {
    enableFakeGateway();
    [, $payer, , $cycle] = marketplacePair();

    Sanctum::actingAs($payer);

    $checkout = $this->postJson("/api/v1/me/tutoring/cycles/{$cycle->id}/pay", ['gateway' => 'fake'])
        ->assertOk()
        ->json('data');

    expect($checkout['checkout_url'])->toContain('/pay/simulate');

    $this->postJson("/api/v1/payments/simulate/{$checkout['tx_ref']}", ['outcome' => 'paid'])
        ->assertOk()
        ->assertJsonPath('data.status', 'paid');

    expect($cycle->fresh()->status)->toBe(CycleStatus::Funded);

    // Idempotency: verifying again never re-runs fulfilment or errors.
    $this->getJson("/api/v1/payments/transactions/{$checkout['tx_ref']}")
        ->assertOk()
        ->assertJsonPath('data.status', 'paid');

    expect(GatewayTransaction::where('status', GatewayTransactionStatus::Paid->value)->count())->toBe(1)
        ->and($cycle->fresh()->status)->toBe(CycleStatus::Funded);
});

it('marks the transaction failed on a simulated failure and leaves the cycle unpaid', function () {
    enableFakeGateway();
    [, $payer, , $cycle] = marketplacePair();

    Sanctum::actingAs($payer);

    $txRef = $this->postJson("/api/v1/me/tutoring/cycles/{$cycle->id}/pay", ['gateway' => 'fake'])
        ->json('data.tx_ref');

    $this->postJson("/api/v1/payments/simulate/{$txRef}", ['outcome' => 'failed'])
        ->assertOk()
        ->assertJsonPath('data.status', 'failed');

    expect($cycle->fresh()->status)->toBe(CycleStatus::AwaitingPayment);
});

it('hides transactions from anyone but the payer', function () {
    enableFakeGateway();
    [, $payer, , $cycle] = marketplacePair();

    Sanctum::actingAs($payer);
    $txRef = $this->postJson("/api/v1/me/tutoring/cycles/{$cycle->id}/pay", ['gateway' => 'fake'])
        ->json('data.tx_ref');

    Sanctum::actingAs(User::factory()->create());
    $this->getJson("/api/v1/payments/transactions/{$txRef}")->assertNotFound();
});

it('refuses cycle payment from anyone but the engagement payer', function () {
    enableFakeGateway();
    [, , , $cycle] = marketplacePair();

    Sanctum::actingAs(User::factory()->create());

    $this->postJson("/api/v1/me/tutoring/cycles/{$cycle->id}/pay", ['gateway' => 'fake'])
        ->assertNotFound();
});

it('answers webhooks with 200 and settles nothing without a valid signature', function () {
    enableFakeGateway();
    [, $payer, , $cycle] = marketplacePair();

    Sanctum::actingAs($payer);
    $txRef = $this->postJson("/api/v1/me/tutoring/cycles/{$cycle->id}/pay", ['gateway' => 'fake'])
        ->json('data.tx_ref');

    // Chapa webhook with no configured secret → ignored, still 200 (the
    // gateway must never see an error and retry-storm us).
    $this->postJson('/api/v1/webhooks/payments/chapa', ['tx_ref' => $txRef, 'status' => 'success'])
        ->assertOk();

    expect($cycle->fresh()->status)->toBe(CycleStatus::AwaitingPayment);
});

it('gates the gateway settings on gateways.manage and persists the matrix', function () {
    Sanctum::actingAs(User::factory()->create());
    $this->getJson('/api/v1/payment-gateways')->assertForbidden();

    Sanctum::actingAs(platformAdmin());

    $this->getJson('/api/v1/payment-gateways')
        ->assertOk()
        ->assertJsonPath('data.marketplace.commission_percent', 10);

    $this->putJson('/api/v1/payment-gateways', [
        'gateways' => [
            'fake' => ['enabled' => true, 'purposes' => ['tutoring_cycle']],
            'chapa' => ['enabled' => false, 'purposes' => []],
        ],
        'marketplace' => ['commission_percent' => 12.5, 'auto_release_days' => 3],
    ])->assertOk();

    expect(PaymentGateways::matrix()['fake']['enabled'])->toBeTrue()
        ->and(PaymentGateways::matrix()['chapa']['enabled'])->toBeFalse()
        ->and(Marketplace::settings()['commission_percent'])->toBe(12.5)
        ->and(Marketplace::settings()['auto_release_days'])->toBe(3);
});

it('lists gateway transactions for platform staff only', function () {
    enableFakeGateway();
    [, $payer, , $cycle] = marketplacePair();

    Sanctum::actingAs($payer);
    $this->postJson("/api/v1/me/tutoring/cycles/{$cycle->id}/pay", ['gateway' => 'fake']);

    $this->getJson('/api/v1/payment-gateways/transactions')->assertForbidden();

    Sanctum::actingAs(platformAdmin());
    $this->getJson('/api/v1/payment-gateways/transactions')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

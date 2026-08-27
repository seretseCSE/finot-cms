<?php

use App\Models\User;
use App\Services\Sms\TiltekSmsClient;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

/*
|--------------------------------------------------------------------------
| PhoneNumber::normalize — the single standard
|--------------------------------------------------------------------------
*/

dataset('valid_phones', [
    // [input, canonical]
    'ethio telecom local' => ['0911234567', '0911234567'],
    'ethio telecom e164' => ['+251911234567', '0911234567'],
    'e164 without plus' => ['251911234567', '0911234567'],
    'no leading zero' => ['911234567', '0911234567'],
    'with spaces' => ['0911 234 567', '0911234567'],
    'with dashes' => ['091-123-4567', '0911234567'],
    'intl with spaces' => ['+251 91 123 4567', '0911234567'],
]);

it('normalises every accepted shape to the canonical local form', function (string $input, string $canonical) {
    expect(PhoneNumber::normalize($input))->toBe($canonical);
    expect(PhoneNumber::isValid($input))->toBeTrue();
})->with('valid_phones');

dataset('invalid_phones', [
    'empty' => [''],
    'too short' => ['09123'],
    'too long' => ['09112345678'],
    'wrong operator (08)' => ['0811234567'],
    'wrong operator local (06)' => ['0611234567'],
    'other country' => ['+254711234567'],
    'letters' => ['not-a-phone'],
    'null' => [null],
]);

it('rejects anything that is not an Ethiopian mobile number', function (?string $input) {
    expect(PhoneNumber::normalize($input))->toBeNull();
    expect(PhoneNumber::isValid($input))->toBeFalse();
})->with('invalid_phones');

it('produces the international SMS form', function () {
    expect(PhoneNumber::forSms('0911234567'))->toBe('251911234567');
    expect(PhoneNumber::forSms('garbage'))->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Safaricom (07…) gate — sms.allow_safaricom, default OFF
|--------------------------------------------------------------------------
*/

dataset('safaricom_shapes', [
    'safaricom local' => ['0711234567', '0711234567'],
    'safaricom e164' => ['+251711234567', '0711234567'],
]);

it('rejects Safaricom numbers everywhere while the flag is off', function (string $input) {
    expect(PhoneNumber::allowSafaricom())->toBeFalse();
    expect(PhoneNumber::normalize($input))->toBeNull();
    expect(PhoneNumber::isValid($input))->toBeFalse();
    expect(PhoneNumber::normalizeContact($input))->toBeNull();
    expect(PhoneNumber::forSms($input))->toBeNull();
})->with('safaricom_shapes');

it('accepts Safaricom numbers once sms.allow_safaricom is on', function (string $input, string $canonical) {
    config()->set('sms.allow_safaricom', true);

    expect(PhoneNumber::normalize($input))->toBe($canonical);
    expect(PhoneNumber::isValid($input))->toBeTrue();
    expect(PhoneNumber::forSms($input))->toBe('251'.substr($canonical, 1));
})->with('safaricom_shapes');

it('rejects a Safaricom phone on signup while the flag is off', function () {
    $this->postJson('/api/v1/auth/signup/request-otp', [
        'phone' => '0711234567',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('phone');
});

/*
|--------------------------------------------------------------------------
| Storage is always canonical, whatever shape was written
|--------------------------------------------------------------------------
*/

it('stores the phone in canonical local form regardless of input shape', function () {
    $user = User::factory()->create(['phone' => '+251911234567']);

    expect($user->fresh()->phone)->toBe('0911234567');
});

/*
|--------------------------------------------------------------------------
| Login accepts any shape — all map to the same account
|--------------------------------------------------------------------------
*/

dataset('login_shapes', [
    'local' => '0911234567',
    'international' => '+251911234567',
    'international no plus' => '251911234567',
    'spaced' => '0911 234 567',
]);

it('logs into one account stored as 09… no matter how the phone is typed', function (string $typed) {
    $user = User::factory()->create([
        'phone' => '0911234567',
        'password' => Hash::make('secret123'),
    ]);

    $this->postJson('/api/v1/auth/login', [
        'identifier' => $typed,
        'password' => 'secret123',
    ])
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.phone', '0911234567');
})->with('login_shapes');

it('accepts a Safaricom 07 number on signup when the flag is on', function () {
    config()->set('sms.allow_safaricom', true);

    $this->postJson('/api/v1/auth/signup/request-otp', [
        'phone' => '0711234567',
    ])->assertOk();

    $this->assertDatabaseHas('signup_otps', ['phone' => '0711234567']);
});

it('rejects an invalid phone on signup with the shared message', function () {
    $this->postJson('/api/v1/auth/signup/request-otp', [
        'phone' => '0811234567',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('phone');
});

/*
|--------------------------------------------------------------------------
| Contact / office lines (school official phone) — mobile + landline
|--------------------------------------------------------------------------
*/

dataset('valid_contact_phones', [
    'addis landline local' => ['0116629800', '0116629800'],
    'addis landline intl spaced' => ['+251 11 662 98 00', '0116629800'],
    'addis landline e164' => ['+251116629800', '0116629800'],
    'mobile still accepted' => ['0911234567', '0911234567'],
]);

it('normalises office contact phones including landlines', function (string $input, string $canonical) {
    expect(PhoneNumber::normalizeContact($input))->toBe($canonical);
    expect(PhoneNumber::isValidContact($input))->toBeTrue();
})->with('valid_contact_phones');

it('rejects landlines from the mobile-only normaliser', function () {
    expect(PhoneNumber::normalize('+251 11 662 98 00'))->toBeNull();
    expect(PhoneNumber::isValid('+251 11 662 98 00'))->toBeFalse();
});

it('formats landline contact phones for display', function () {
    expect(PhoneNumber::formatContact('+251 11 662 98 00'))->toBe('011 662 98 00');
    expect(PhoneNumber::formatContact('0911234567'))->toBe('0911 234 567');
});

/*
|--------------------------------------------------------------------------
| SMS batches never fail because of a stored undeliverable number
|--------------------------------------------------------------------------
*/

it('drops undeliverable Safaricom recipients from an SMS batch instead of failing it', function () {
    config()->set('sms.enabled', true);
    config()->set('sms.account_id', 'acc');
    config()->set('sms.token', 'tok');
    Http::fake();

    app(TiltekSmsClient::class)->send(['0911234567', '0711234567'], 'hello');

    Http::assertSent(fn ($request) => $request['to'] === ['0911234567']);
});

it('sends no SMS at all when every recipient is undeliverable', function () {
    config()->set('sms.enabled', true);
    Http::fake();

    app(TiltekSmsClient::class)->send('0711234567', 'hello');

    Http::assertNothingSent();
});

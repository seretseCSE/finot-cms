<?php

use App\Models\PasswordSetupToken;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('logs in with phone and password', function () {
    $user = User::factory()->create([
        'phone' => '0911111111',
        'password' => Hash::make('secret123'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'identifier' => '0911111111',
        'password' => 'secret123',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.phone', '0911111111')
        ->assertJsonStructure(['data', 'meta' => ['token']]);
});

it('rejects invalid credentials', function () {
    User::factory()->create([
        'phone' => '0911111111',
        'password' => Hash::make('secret123'),
    ]);

    $this->postJson('/api/v1/auth/login', [
        'identifier' => '0911111111',
        'password' => 'wrong',
    ])->assertStatus(422);
});

it('rejects login for a provisioned account without a password', function () {
    User::factory()->create([
        'phone' => '0922222222',
        'password' => null,
    ]);

    $this->postJson('/api/v1/auth/login', [
        'identifier' => '0922222222',
        'password' => 'anything',
    ])->assertStatus(422);
});

it('sets a password from a valid setup token and returns an api token', function () {
    $user = User::factory()->create(['password' => null]);

    PasswordSetupToken::create([
        'user_id' => $user->id,
        'token' => hash('sha256', 'plain-token'),
        'expires_at' => now()->addDay(),
    ]);

    $response = $this->postJson('/api/v1/auth/set-password', [
        'token' => 'plain-token',
        'password' => 'newsecret123',
        'password_confirmation' => 'newsecret123',
    ]);

    $response->assertOk()->assertJsonStructure(['data', 'meta' => ['token']]);

    expect(Hash::check('newsecret123', $user->fresh()->password))->toBeTrue();
    expect(PasswordSetupToken::first()->used_at)->not->toBeNull();
    // Setting the PIN from an invite link IS a sign-in — the portal chip
    // must not keep reading "Never logged in".
    expect($user->fresh()->last_login_at)->not->toBeNull();
});

it('rejects an expired setup token', function () {
    $user = User::factory()->create(['password' => null]);

    PasswordSetupToken::create([
        'user_id' => $user->id,
        'token' => hash('sha256', 'plain-token'),
        'expires_at' => now()->subMinute(),
    ]);

    $this->postJson('/api/v1/auth/set-password', [
        'token' => 'plain-token',
        'password' => 'newsecret123',
        'password_confirmation' => 'newsecret123',
    ])->assertStatus(422);
});

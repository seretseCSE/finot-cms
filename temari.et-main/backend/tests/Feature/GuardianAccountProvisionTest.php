<?php

use App\Models\ParentProfile;
use App\Models\PasswordSetupToken;
use App\Models\User;
use App\Services\Sms\SmsClient;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

/*
 * Reproduction: registering a student with a NEW primary guardian must always
 * provision the guardian's user account (keyed by the guardian's own phone)
 * and text them a password-setup link.
 */

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->sms = Mockery::spy(SmsClient::class);
    app()->instance(SmsClient::class, $this->sms);
});

it('provisions the primary guardian account with setup SMS at registration', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/students', [
            'first_name' => 'Lensa', 'father_name' => 'Gemechu', 'gender' => 'female',
            'languages' => ['am'],
            'guardians' => [[
                'first_name' => 'Gemechu',
                'father_name' => 'Tolla',
                'phone' => '0911889900',
                'relationship' => 'father',
                'is_primary' => true,
                'can_receive_sms' => true,
            ]],
        ])
        ->assertCreated();

    $user = User::where('phone', '0911889900')->first();

    expect($user)->not->toBeNull();
    expect(ParentProfile::where('user_id', $user->id)->exists())->toBeTrue();
    expect(PasswordSetupToken::where('user_id', $user->id)->exists())->toBeTrue();

    $this->sms->shouldHaveReceived('send')->withArgs(
        fn ($to, $body) => $to === '0911889900' && str_contains($body, '/set-password?token='),
    );
});

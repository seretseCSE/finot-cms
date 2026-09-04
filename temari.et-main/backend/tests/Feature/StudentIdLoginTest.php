<?php

use App\Actions\LinkStudentLoginAction;
use App\Enums\Role;
use App\Models\Membership;
use App\Models\ParentProfile;
use App\Models\PasswordResetToken;
use App\Models\PasswordSetupToken;
use App\Models\Student;
use App\Models\StudentGuardian;
use App\Models\User;
use App\Services\Sms\SmsClient;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

/*
 * The student-ID login lane: a phone-less account reachable ONLY through
 * students.public_id, never a staff or guardian handle; setup + PIN-reset SMS
 * route to the primary guardian. Guard rail for App\Support\LoginIdentifier.
 */

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->sms = Mockery::spy(SmsClient::class);
    app()->instance(SmsClient::class, $this->sms);
    RateLimiter::clear('login:'.sha1(mb_strtolower('0911111111').'|127.0.0.1'));
});

function idLoginStudent($branch, array $attributes = []): Student
{
    return Student::create(array_merge([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => 'Abel', 'father_name' => 'Tesfaye', 'gender' => 'male',
    ], $attributes));
}

function idLoginGuardianFor(Student $student, string $phone, array $link = []): ParentProfile
{
    $user = User::factory()->create(['phone' => $phone, 'password' => null]);
    $parent = ParentProfile::create(['user_id' => $user->id]);
    StudentGuardian::create(array_merge([
        'student_id' => $student->id, 'parent_id' => $parent->id,
        'relationship' => 'father', 'is_active' => true,
        'is_primary' => true, 'can_receive_sms' => true,
    ], $link));

    return $parent;
}

// ── ID login ────────────────────────────────────────────────────────────────

it('logs a student in with their public id and PIN', function () {
    $branch = makeBranch();
    $student = idLoginStudent($branch);
    $user = User::factory()->create(['phone' => null, 'password' => Hash::make('4321')]);
    $student->forceFill(['user_id' => $user->id])->save();

    $this->postJson('/api/v1/auth/login', [
        'identifier' => strtolower($student->public_id), // typed sloppily
        'password' => '4321',
    ])
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonStructure(['meta' => ['token']]);
});

it('rejects a wrong PIN on an id login with a generic message', function () {
    $branch = makeBranch();
    $student = idLoginStudent($branch);
    $user = User::factory()->create(['phone' => null, 'password' => Hash::make('4321')]);
    $student->forceFill(['user_id' => $user->id])->save();

    $this->postJson('/api/v1/auth/login', [
        'identifier' => $student->public_id,
        'password' => '9999',
    ])->assertStatus(422)->assertJsonValidationErrors('identifier');
});

it('never resolves a student id to an account holding staff authority', function () {
    $branch = makeBranch();
    $student = idLoginStudent($branch);
    $user = User::factory()->create(['password' => Hash::make('4321')]);
    Membership::create([
        'user_id' => $user->id, 'role' => Role::Teacher->value, 'scope' => Role::Teacher->scope()->value,
        'school_id' => $branch->school_id, 'branch_id' => $branch->id, 'is_active' => true,
    ]);
    $student->forceFill(['user_id' => $user->id])->save();

    // Correct PIN — still refused through the ID lane.
    $this->postJson('/api/v1/auth/login', [
        'identifier' => $student->public_id,
        'password' => '4321',
    ])->assertStatus(422);
});

it('never resolves a student id to a guardian account', function () {
    $branch = makeBranch();
    $student = idLoginStudent($branch);
    $parent = idLoginGuardianFor($student, '0911777777');
    $parent->user->forceFill(['password' => Hash::make('4321')])->save();
    $student->forceFill(['user_id' => $parent->user_id])->save();

    $this->postJson('/api/v1/auth/login', [
        'identifier' => $student->public_id,
        'password' => '4321',
    ])->assertStatus(422);
});

it('throttles repeated failed logins per identifier', function () {
    $branch = makeBranch();
    $student = idLoginStudent($branch);
    $user = User::factory()->create(['phone' => null, 'password' => Hash::make('4321')]);
    $student->forceFill(['user_id' => $user->id])->save();

    foreach (range(1, 5) as $i) {
        $this->postJson('/api/v1/auth/login', [
            'identifier' => $student->public_id, 'password' => '0000',
        ])->assertStatus(422);
    }

    // Sixth attempt hits the limiter — even with the CORRECT pin.
    $response = $this->postJson('/api/v1/auth/login', [
        'identifier' => $student->public_id, 'password' => '4321',
    ]);
    $response->assertStatus(422);
    expect($response->json('errors.identifier.0'))->toContain('Too many attempts');
});

// ── Phone-less provisioning ─────────────────────────────────────────────────

it('provisions a phone-less id login and texts the setup link to the primary guardian', function () {
    $branch = makeBranch();
    $student = idLoginStudent($branch);
    idLoginGuardianFor($student, '0911500001');

    app(LinkStudentLoginAction::class)->execute($student, null);

    $student->refresh();
    expect($student->user_id)->not->toBeNull();
    expect($student->user->phone)->toBeNull();
    expect(PasswordSetupToken::where('user_id', $student->user_id)->exists())->toBeTrue();

    // The SMS names the student's ID and lands on the GUARDIAN's phone.
    $this->sms->shouldHaveReceived('send')->withArgs(
        fn ($to, $body) => $to === '0911500001' && str_contains($body, $student->public_id),
    );
});

it('creates an id login from the staff portal-account endpoint without a phone', function () {
    $branch = makeBranch();
    $student = idLoginStudent($branch);
    idLoginGuardianFor($student, '0911500002');

    Sanctum::actingAs(directorOf($branch));

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/students/{$student->id}/portal-account", ['phone' => null])
        ->assertOk()
        ->assertJsonPath('data.login_mode', 'student_id');

    expect($student->fresh()->user->phone)->toBeNull();
});

it('rejects a student phone that matches a guardian phone at registration', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/students', [
            'first_name' => 'Sara', 'father_name' => 'Bekele', 'gender' => 'female',
            'languages' => ['am'],
            'primary_phone' => '0911223344',
            'guardians' => [guardianPayload()], // same 0911223344
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('primary_phone');
});

// ── PIN reset through the guardian ──────────────────────────────────────────

it('sends the PIN-reset OTP for an id login to the primary guardian and resets with it', function () {
    $branch = makeBranch();
    $student = idLoginStudent($branch);
    idLoginGuardianFor($student, '0911600001');
    $user = User::factory()->create(['phone' => null, 'password' => Hash::make('4321')]);
    $student->forceFill(['user_id' => $user->id])->save();

    $captured = null;
    $this->sms->shouldReceive('send')->andReturnUsing(function ($to, $body) use (&$captured) {
        $captured = ['to' => $to, 'body' => $body];
    });

    $this->postJson('/api/v1/auth/forgot-password', [
        'identifier' => $student->public_id,
    ])->assertOk();

    expect($captured)->not->toBeNull();
    expect($captured['to'])->toBe('0911600001');
    expect(PasswordResetToken::where('user_id', $user->id)->exists())->toBeTrue();

    preg_match('/\d{6}/', $captured['body'], $m);

    $this->postJson('/api/v1/auth/reset-password', [
        'identifier' => $student->public_id,
        'otp' => $m[0],
        'password' => '5678',
        'password_confirmation' => '5678',
    ])->assertOk()->assertJsonStructure(['meta' => ['token']]);

    expect(Hash::check('5678', $user->fresh()->password))->toBeTrue();
});

it('answers generically for an unknown identifier on forgot-password', function () {
    $this->postJson('/api/v1/auth/forgot-password', ['identifier' => 'ZZZZZZ'])
        ->assertOk();

    $this->sms->shouldNotHaveReceived('send');
});

<?php

use App\Models\AccountLinkRequest;
use App\Models\ParentProfile;
use App\Models\PasswordSetupToken;
use App\Models\SignupOtp;
use App\Models\Student;
use App\Models\StudentGuardian;
use App\Models\User;
use App\Services\Sms\SmsClient;
use Database\Seeders\GradeLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(GradeLevelSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    app()->instance(SmsClient::class, Mockery::spy(SmsClient::class));
});

function portalStudent($branch, array $attributes = []): Student
{
    return Student::create(array_merge([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => 'Hana', 'father_name' => 'Bekele', 'gender' => 'female',
    ], $attributes));
}

/** A provisioned-but-never-activated parent (users row, no password). */
function provisionedParentAt($branch, string $phone): ParentProfile
{
    $student = portalStudent($branch);
    $user = User::factory()->create(['phone' => $phone, 'password' => null, 'last_login_at' => null]);
    $parent = ParentProfile::create(['user_id' => $user->id]);
    StudentGuardian::create([
        'student_id' => $student->id, 'parent_id' => $parent->id,
        'relationship' => 'father', 'is_active' => true,
    ]);

    return $parent;
}

// ── Account payloads ────────────────────────────────────────────────────────

it('exposes the portal account state on the parent profile', function () {
    $branch = makeBranch();
    $parent = provisionedParentAt($branch, '0911200001');

    Sanctum::actingAs(directorOf($branch));

    $response = $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/parents/{$parent->id}")
        ->assertOk();

    expect($response->json('data.account.has_password'))->toBeFalse();
    expect($response->json('data.account.last_login_at'))->toBeNull();
    expect($response->json('data.account.phone'))->toBe('0911200001');
});

it('filters the parents register by login state', function () {
    $branch = makeBranch();
    provisionedParentAt($branch, '0911200010'); // no PIN yet
    $activated = provisionedParentAt($branch, '0911200011');
    $activated->user->forceFill(['password' => bcrypt('1234')])->save();

    Sanctum::actingAs(directorOf($branch));

    $with = $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/parents?has_login=true')->assertOk();
    expect($with->json('data'))->toHaveCount(1);
    expect($with->json('data.0.account.has_password'))->toBeTrue();

    $without = $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/parents?has_login=false')->assertOk();
    expect($without->json('data'))->toHaveCount(1);
    expect($without->json('data.0.account.has_password'))->toBeFalse();
});

// ── Student add-login ───────────────────────────────────────────────────────

it('creates and links a student login and texts a setup link', function () {
    $branch = makeBranch();
    $student = portalStudent($branch);

    Sanctum::actingAs(directorOf($branch));

    $response = $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/students/{$student->id}/portal-account", ['phone' => '0977000001'])
        ->assertOk();

    expect($response->json('data.has_password'))->toBeFalse();

    $student->refresh();
    expect($student->user_id)->not->toBeNull();
    expect($student->user->phone)->toBe('0977000001');
    expect(PasswordSetupToken::where('user_id', $student->user_id)->exists())->toBeTrue();
});

it('refuses to reuse a guardian phone for a student login', function () {
    $branch = makeBranch();
    $parent = provisionedParentAt($branch, '0911200002');
    $student = portalStudent($branch);

    Sanctum::actingAs(directorOf($branch));

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/students/{$student->id}/portal-account", ['phone' => '0911200002'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('phone');

    expect($student->fresh()->user_id)->toBeNull();
});

it('refuses a phone already linked to another student', function () {
    $branch = makeBranch();
    $first = portalStudent($branch);
    $second = portalStudent($branch, ['first_name' => 'Sara']);

    Sanctum::actingAs(directorOf($branch));

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/students/{$first->id}/portal-account", ['phone' => '0977000002'])
        ->assertOk();

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/students/{$second->id}/portal-account", ['phone' => '0977000002'])
        ->assertUnprocessable();
});

it('denies portal provisioning to staff of another school', function () {
    $branchA = makeBranch('AA-0001');
    $branchB = makeBranch('BB-0001');
    $student = portalStudent($branchA);

    Sanctum::actingAs(directorOf($branchB));

    $this->withHeaders(branchContext($branchB))
        ->postJson("/api/v1/students/{$student->id}/portal-account", ['phone' => '0977000003'])
        ->assertForbidden();
});

// ── Invites ─────────────────────────────────────────────────────────────────

it('re-sends a setup link for a never-activated parent account', function () {
    $branch = makeBranch();
    $parent = provisionedParentAt($branch, '0911200003');

    Sanctum::actingAs(directorOf($branch));

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/parents/{$parent->id}/portal-account/invite")
        ->assertOk();

    expect(PasswordSetupToken::where('user_id', $parent->user_id)->exists())->toBeTrue();
});

it('refuses an invite for an account already in use', function () {
    $branch = makeBranch();
    $parent = provisionedParentAt($branch, '0911200004');
    $parent->user->forceFill(['password' => bcrypt('1234'), 'last_login_at' => now()])->save();

    Sanctum::actingAs(directorOf($branch));

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/parents/{$parent->id}/portal-account/invite")
        ->assertUnprocessable();
});

// ── Self-signup ─────────────────────────────────────────────────────────────

function requestOtp(string $phone): string
{
    test()->postJson('/api/v1/auth/signup/request-otp', ['phone' => $phone])->assertOk();

    // The OTP is hashed at rest; recreate a known one for the assertion path.
    $otp = '123456';
    SignupOtp::where('phone', $phone)->update(['token' => hash('sha256', $otp)]);

    return $otp;
}

it('rejects an OTP request for a phone that already has a usable account', function () {
    User::factory()->create(['phone' => '0911300001']); // factory sets a password

    // 409 + code (not a validation error) so the signup screen can offer an
    // inline sign-in dialog instead of a dead-end field error.
    $this->postJson('/api/v1/auth/signup/request-otp', ['phone' => '0911300001'])
        ->assertStatus(409)
        ->assertJsonPath('code', 'account_exists');
});

it('creates a public account through phone + OTP + PIN', function () {
    $otp = requestOtp('0911300002');

    $response = $this->postJson('/api/v1/auth/signup', [
        'phone' => '0911300002', 'otp' => $otp,
        'name' => 'Public Learner',
        'password' => '4321', 'password_confirmation' => '4321',
    ])->assertCreated();

    expect($response->json('meta.linked'))->toBe('none');
    expect($response->json('meta.token'))->not->toBeNull();

    $user = User::where('phone', '0911300002')->first();
    expect($user)->not->toBeNull();
    expect($user->password)->not->toBeNull();
});

it('refuses signup with a wrong or reused OTP', function () {
    $otp = requestOtp('0911300003');

    $this->postJson('/api/v1/auth/signup', [
        'phone' => '0911300003', 'otp' => '999999',
        'name' => 'X', 'password' => '4321', 'password_confirmation' => '4321',
    ])->assertUnprocessable();

    $this->postJson('/api/v1/auth/signup', [
        'phone' => '0911300003', 'otp' => $otp,
        'name' => 'X', 'password' => '4321', 'password_confirmation' => '4321',
    ])->assertCreated();

    // Replay: the OTP was consumed.
    $this->postJson('/api/v1/auth/signup', [
        'phone' => '0911300003', 'otp' => $otp,
        'name' => 'X', 'password' => '4321', 'password_confirmation' => '4321',
    ])->assertUnprocessable();
});

it('activates a provisioned parent account and reports the parent link', function () {
    $branch = makeBranch();
    provisionedParentAt($branch, '0911300004');

    $otp = requestOtp('0911300004');

    $response = $this->postJson('/api/v1/auth/signup', [
        'phone' => '0911300004', 'otp' => $otp,
        'password' => '4321', 'password_confirmation' => '4321',
    ])->assertOk();

    expect($response->json('meta.linked'))->toBe('parent');

    $user = User::where('phone', '0911300004')->first();
    expect($user->password)->not->toBeNull();
});

it('auto-links a student ID when the verified phone matches the record', function () {
    $branch = makeBranch();
    $student = portalStudent($branch, ['primary_phone' => '0911300005']);

    $otp = requestOtp('0911300005');

    $response = $this->postJson('/api/v1/auth/signup', [
        'phone' => '0911300005', 'otp' => $otp,
        'name' => 'Hana Bekele',
        'password' => '4321', 'password_confirmation' => '4321',
        'student_public_id' => strtolower($student->public_id),
    ])->assertCreated();

    expect($response->json('meta.linked'))->toBe('student');
    expect($student->fresh()->user_id)->not->toBeNull();
});

it('files a pending claim when the phone does not match the student record', function () {
    $branch = makeBranch();
    $student = portalStudent($branch, ['primary_phone' => '0911999999']);

    $otp = requestOtp('0911300006');

    $response = $this->postJson('/api/v1/auth/signup', [
        'phone' => '0911300006', 'otp' => $otp,
        'name' => 'Unknown Phone',
        'password' => '4321', 'password_confirmation' => '4321',
        'student_public_id' => $student->public_id,
    ])->assertCreated();

    expect($response->json('meta.linked'))->toBe('claim_pending');
    expect($student->fresh()->user_id)->toBeNull();
    expect(AccountLinkRequest::where('student_id', $student->id)->where('status', 'pending')->exists())->toBeTrue();
});

// ── Claim review ────────────────────────────────────────────────────────────

function pendingClaim($branch): AccountLinkRequest
{
    $student = portalStudent($branch);
    $claimant = User::factory()->create(['phone' => '0977888777']);

    return AccountLinkRequest::create([
        'user_id' => $claimant->id, 'student_id' => $student->id, 'status' => 'pending',
    ]);
}

it('lists pending claims for the active context and approves the link', function () {
    $branch = makeBranch();
    $claim = pendingClaim($branch);

    Sanctum::actingAs(directorOf($branch));

    $list = $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/account-link-requests')
        ->assertOk();
    expect($list->json('data'))->toHaveCount(1);

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/account-link-requests/{$claim->id}/approve")
        ->assertOk();

    expect($claim->fresh()->status)->toBe('approved');
    expect($claim->student->fresh()->user_id)->toBe($claim->user_id);
});

it('rejects a claim without linking anything', function () {
    $branch = makeBranch();
    $claim = pendingClaim($branch);

    Sanctum::actingAs(directorOf($branch));

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/account-link-requests/{$claim->id}/reject")
        ->assertOk();

    expect($claim->fresh()->status)->toBe('rejected');
    expect($claim->student->fresh()->user_id)->toBeNull();
});

it('hides and protects claims from staff of another school', function () {
    $branchA = makeBranch('AA-0001');
    $branchB = makeBranch('BB-0001');
    $claim = pendingClaim($branchA);

    Sanctum::actingAs(directorOf($branchB));

    $list = $this->withHeaders(branchContext($branchB))
        ->getJson('/api/v1/account-link-requests')
        ->assertOk();
    expect($list->json('data'))->toHaveCount(0);

    $this->withHeaders(branchContext($branchB))
        ->postJson("/api/v1/account-link-requests/{$claim->id}/approve")
        ->assertForbidden();

    expect($claim->fresh()->status)->toBe('pending');
});

<?php

use App\Enums\Role;
use App\Models\Branch;
use App\Models\Membership;
use App\Models\School;
use App\Models\User;
use App\Services\Sms\SmsClient;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    app()->instance(SmsClient::class, Mockery::spy(SmsClient::class));
});

function principalOf(School $school): User
{
    $principal = User::factory()->create();
    Membership::create([
        'user_id' => $principal->id,
        'school_id' => $school->id,
        'role' => Role::Principal->value,
        'scope' => Role::Principal->scope()->value,
        'is_active' => true,
    ]);

    return $principal;
}

it('lets a principal create a branch which provisions a director', function () {
    $school = School::create(['name' => 'Unity Academy']);
    Sanctum::actingAs(principalOf($school));

    $response = $this->withHeaders(schoolContext($school))->postJson("/api/v1/schools/{$school->id}/branches", [
        'name' => 'Bole Branch',
        'code' => 'AA-0001',
        'city' => 'Addis Ababa',
        'director_name' => 'Mohammed Ali Hassan',
        'director_phone' => '0911999888',
    ]);

    $response->assertCreated()->assertJsonPath('data.name', 'Bole Branch');

    $branch = Branch::firstWhere('code', 'AA-0001');
    $director = User::firstWhere('phone', '0911999888');

    expect($branch->school_id)->toBe($school->id);
    expect(hasMembershipRole($director, Role::Director))->toBeTrue();

    $membership = Membership::firstWhere('user_id', $director->id);
    expect($membership->branch_id)->toBe($branch->id);
    expect($membership->scope)->toBe(Role::Director->scope());
});

it('accepts an office landline as the branch phone and blocks duplicates within the school', function () {
    $school = School::create(['name' => 'Unity Academy']);
    Sanctum::actingAs(principalOf($school));
    $headers = schoolContext($school);

    // Geographic landline (011…) — a real office line, normalised to local form.
    $this->withHeaders($headers)->postJson("/api/v1/schools/{$school->id}/branches", [
        'name' => 'Bole Branch', 'code' => 'AA-0101', 'phone' => '+251 11 123 45 67',
    ])->assertCreated();
    expect(Branch::firstWhere('code', 'AA-0101')->phone)->toBe('0111234567');

    // The same number on a sibling branch is a duplicate.
    $this->withHeaders($headers)->postJson("/api/v1/schools/{$school->id}/branches", [
        'name' => 'Kality Branch', 'code' => 'AA-0102', 'phone' => '0111234567',
    ])->assertUnprocessable()->assertJsonValidationErrors('phone');

    // A different landline is fine, and an update may keep the branch's own number.
    $this->withHeaders($headers)->postJson("/api/v1/schools/{$school->id}/branches", [
        'name' => 'Kality Branch', 'code' => 'AA-0102', 'phone' => '0111765432',
    ])->assertCreated();

    $kality = Branch::firstWhere('code', 'AA-0102');
    $this->withHeaders($headers)->putJson("/api/v1/branches/{$kality->id}", [
        'phone' => '0111765432',
    ])->assertOk();

    // …but not steal a sibling's.
    $this->withHeaders($headers)->putJson("/api/v1/branches/{$kality->id}", [
        'phone' => '0111234567',
    ])->assertUnprocessable()->assertJsonValidationErrors('phone');

    // Garbage prefixes still reject.
    $this->withHeaders($headers)->postJson("/api/v1/schools/{$school->id}/branches", [
        'name' => 'Bad Branch', 'code' => 'AA-0103', 'phone' => '0811234567',
    ])->assertUnprocessable()->assertJsonValidationErrors('phone');
});

it('hides geo coordinates from a principal but shows them to super admin', function () {
    $school = School::create(['name' => 'Unity Academy']);
    $branch = $school->branches()->create([
        'name' => 'Bole Branch',
        'code' => 'AA-0002',
        'longitude' => 38.7636,
        'latitude' => 9.0054,
    ]);

    Sanctum::actingAs(principalOf($school));
    $this->getJson("/api/v1/branches/{$branch->id}")
        ->assertOk()
        ->assertJsonMissingPath('data.latitude')
        ->assertJsonMissingPath('data.longitude');

    $superAdmin = User::factory()->create();
    grantPlatformRole($superAdmin, Role::SuperAdmin);
    Sanctum::actingAs($superAdmin);
    $this->getJson("/api/v1/branches/{$branch->id}")
        ->assertOk()
        ->assertJsonPath('data.latitude', '9.0054000')
        ->assertJsonPath('data.longitude', '38.7636000');
});

it('forbids a principal from creating a branch in another school', function () {
    $schoolA = School::create(['name' => 'School A']);
    $schoolB = School::create(['name' => 'School B']);

    Sanctum::actingAs(principalOf($schoolA));

    $this->postJson("/api/v1/schools/{$schoolB->id}/branches", [
        'name' => 'Rogue Branch',
        'code' => 'AA-9999',
    ])->assertForbidden();
});

it('lists every branch across every school for a platform admin', function () {
    $branchA = makeBranch('AA-0001');
    $branchB = makeBranch('AA-0002');

    Sanctum::actingAs(platformAdmin());

    $this->getJson('/api/v1/branches')
        ->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.0.school.name', $branchB->school->name)
        ->assertJsonPath('data.1.school.name', $branchA->school->name);
});

it('scopes the global branch list to a principal\'s managed school', function () {
    $ownBranch = makeBranch('AA-0001');
    $ownBranch->school()->update(['name' => 'Own School']);
    $otherBranch = makeBranch('AA-0002');

    Sanctum::actingAs(schoolPrincipal($ownBranch));

    $response = $this->withHeaders(schoolContext($ownBranch))->getJson('/api/v1/branches')->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toContain($ownBranch->id)
        ->and($ids)->not->toContain($otherBranch->id);
});

it('scopes branch access to the active context for a director-and-principal user', function () {
    // A user who is a director at School A and a principal at School B. The
    // permission the principal role grants must not leak into the director
    // context — the reported bug.
    $schoolA = School::create(['name' => 'School A']);
    $branchA = $schoolA->branches()->create(['name' => 'A Bole', 'code' => 'AA-0001']);
    $schoolB = School::create(['name' => 'School B']);
    $branchB = $schoolB->branches()->create(['name' => 'B Piassa', 'code' => 'BB-0001']);

    $user = User::factory()->create();
    Membership::create([
        'user_id' => $user->id, 'school_id' => $schoolA->id, 'branch_id' => $branchA->id,
        'role' => Role::Director->value, 'scope' => Role::Director->scope()->value, 'is_active' => true,
    ]);
    Membership::create([
        'user_id' => $user->id, 'school_id' => $schoolB->id,
        'role' => Role::Principal->value, 'scope' => Role::Principal->scope()->value, 'is_active' => true,
    ]);

    Sanctum::actingAs($user);

    // In the DIRECTOR context (School A / Branch A) branch management is denied,
    // even though the same account is a principal elsewhere.
    $directorCtx = ['X-School-Id' => (string) $schoolA->id, 'X-Branch-Id' => (string) $branchA->id];
    $this->withHeaders($directorCtx)->getJson('/api/v1/branches')->assertForbidden();
    $this->withHeaders($directorCtx)->getJson('/api/v1/branches/export')->assertForbidden();
    $this->withHeaders($directorCtx)
        ->postJson("/api/v1/schools/{$schoolA->id}/branches", ['name' => 'X', 'code' => 'AA-0002'])
        ->assertForbidden();

    // In the PRINCIPAL context (School B) it is allowed, and the list is scoped
    // to School B only — School A's branch never appears.
    $response = $this->withHeaders(['X-School-Id' => (string) $schoolB->id])
        ->getJson('/api/v1/branches')
        ->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toContain($branchB->id)->and($ids)->not->toContain($branchA->id);
});

it('forbids a director from the branch management module', function () {
    $branch = makeBranch('AA-0001');
    $school = $branch->school;

    Sanctum::actingAs(directorOf($branch));

    // A director never sees Branch Management: no list, no export, and no
    // create / update / delete — only operational modules inside their branch.
    $this->getJson('/api/v1/branches')->assertForbidden();
    $this->getJson('/api/v1/branches/export')->assertForbidden();

    $this->postJson("/api/v1/schools/{$school->id}/branches", [
        'name' => 'New Branch',
        'code' => 'AA-0002',
    ])->assertForbidden();

    $this->putJson("/api/v1/branches/{$branch->id}", ['name' => 'Renamed'])
        ->assertForbidden();

    $this->deleteJson("/api/v1/branches/{$branch->id}")->assertForbidden();
});

it('filters the cross-school branch index by search term', function () {
    $school = School::create(['name' => 'Unity Academy']);
    Branch::create(['school_id' => $school->id, 'name' => 'Bole Branch', 'code' => 'AA-0001', 'city' => 'Addis Ababa', 'is_active' => true]);
    Branch::create(['school_id' => $school->id, 'name' => 'Piassa Branch', 'code' => 'AA-0002', 'city' => 'Addis Ababa', 'is_active' => true]);
    Sanctum::actingAs(platformAdmin());

    $this->getJson('/api/v1/branches?search=bole')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Bole Branch');
});

it('exports branches respecting the active filters', function () {
    $school = School::create(['name' => 'Unity Academy']);
    Branch::create(['school_id' => $school->id, 'name' => 'Bole Branch', 'code' => 'AA-0001', 'is_active' => true]);
    Branch::create(['school_id' => $school->id, 'name' => 'Piassa Branch', 'code' => 'AA-0002', 'is_active' => false]);
    Sanctum::actingAs(platformAdmin());

    $this->getJson('/api/v1/branches/export?is_active=true')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Bole Branch');
});

it('exposes the branch director on the resource', function () {
    $school = School::create(['name' => 'Unity Academy']);
    Sanctum::actingAs(principalOf($school));

    $this->withHeaders(schoolContext($school))->postJson("/api/v1/schools/{$school->id}/branches", [
        'name' => 'Bole Branch',
        'code' => 'AA-0001',
        'director_name' => 'Meron Alemu',
        'director_phone' => '0911223344',
    ])->assertCreated();

    $branch = Branch::firstWhere('code', 'AA-0001');

    $this->withHeaders(schoolContext($school))->getJson("/api/v1/branches/{$branch->id}")
        ->assertOk()
        ->assertJsonPath('data.director.name', 'Meron Alemu')
        ->assertJsonPath('data.director.phone', '0911223344');
});

it('lets a principal replace a branch director', function () {
    $school = School::create(['name' => 'Unity Academy']);
    $branch = $school->branches()->create(['name' => 'Bole', 'code' => 'AA-0001']);
    $director = directorOf($branch);
    $oldMembership = Membership::where('branch_id', $branch->id)
        ->where('role', Role::Director->value)->first();

    Sanctum::actingAs(principalOf($school));

    $this->withHeaders(schoolContext($school))->putJson("/api/v1/branches/{$branch->id}/director", [
        'name' => 'Hanna Girma',
        'phone' => '0922334455',
    ])
        ->assertOk()
        ->assertJsonPath('data.director.name', 'Hanna Girma');

    expect($oldMembership->fresh()->is_active)->toBeFalse();

    $new = User::firstWhere('phone', '0922334455');
    expect(hasMembershipRole($new, Role::Director))->toBeTrue();

    $active = Membership::where('branch_id', $branch->id)
        ->where('role', Role::Director->value)
        ->where('is_active', true)->get();
    expect($active)->toHaveCount(1);
    expect($active->first()->user_id)->toBe($new->id);
});

it('forbids a director from replacing their branch director', function () {
    $school = School::create(['name' => 'Unity Academy']);
    $branch = $school->branches()->create(['name' => 'Bole', 'code' => 'AA-0001']);
    Sanctum::actingAs(directorOf($branch));

    $this->putJson("/api/v1/branches/{$branch->id}/director", [
        'name' => 'Someone Else',
        'phone' => '0933445566',
    ])->assertForbidden();
});

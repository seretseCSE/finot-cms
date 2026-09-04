<?php

use App\Enums\Role;
use App\Models\Employee;
use App\Models\Membership;
use App\Models\School;
use App\Models\User;
use App\Services\Sms\SmsClient;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->sms = Mockery::spy(SmsClient::class);
    app()->instance(SmsClient::class, $this->sms);
});

function superAdmin(): User
{
    $user = User::factory()->create();
    grantPlatformRole($user, Role::SuperAdmin);

    return $user;
}

it('creates a school and provisions the principal with an SMS link', function () {
    Sanctum::actingAs(superAdmin());

    $response = $this->postJson('/api/v1/schools', [
        'name' => 'Unity Academy',
        'principal_name' => 'Abebe Kebede Girma',
        'principal_phone' => '0911223344',
    ]);

    $response->assertCreated()->assertJsonPath('data.name', 'Unity Academy');

    $school = School::firstWhere('name', 'Unity Academy');
    $principal = User::firstWhere('phone', '0911223344');

    expect($principal)->not->toBeNull();
    expect(hasMembershipRole($principal, Role::Principal))->toBeTrue();

    $membership = Membership::firstWhere('user_id', $principal->id);
    expect($membership->role)->toBe(Role::Principal);
    expect($membership->school_id)->toBe($school->id);
    expect($membership->branch_id)->toBeNull();

    $employee = Employee::firstWhere('user_id', $principal->id);
    expect($employee->school_id)->toBe($school->id);
    expect($employee->first_name)->toBe('Abebe');
    expect($employee->father_name)->toBe('Kebede');
    expect($employee->grandfather_name)->toBe('Girma');

    $this->sms->shouldHaveReceived('send')->once();
});

it('accepts an Ethiopian office landline as the school phone', function () {
    Sanctum::actingAs(superAdmin());

    $this->postJson('/api/v1/schools', [
        'name' => 'Unity Academy',
        'phone' => '+251 11 662 98 00',
        'principal_name' => 'Abebe Kebede',
        'principal_phone' => '0911223344',
    ])
        ->assertCreated()
        ->assertJsonPath('data.phone', '0116629800');

    expect(School::firstWhere('name', 'Unity Academy')->phone)->toBe('0116629800');
});

it('still requires a mobile number for the principal phone', function () {
    Sanctum::actingAs(superAdmin());

    $this->postJson('/api/v1/schools', [
        'name' => 'Unity Academy',
        'phone' => '+251 11 662 98 00',
        'principal_name' => 'Abebe Kebede',
        'principal_phone' => '+251 11 662 98 00',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('principal_phone');
});

it('provisions an optional technical contact as school_admin', function () {
    Sanctum::actingAs(superAdmin());

    $this->postJson('/api/v1/schools', [
        'name' => 'Unity Academy',
        'principal_name' => 'Abebe Kebede',
        'principal_phone' => '0911223344',
        'technical_name' => 'Sara Tesfaye',
        'technical_phone' => '0911556677',
    ])->assertCreated();

    $tech = User::firstWhere('phone', '0911556677');
    expect(hasMembershipRole($tech, Role::SchoolAdmin))->toBeTrue();
});

it('forbids a principal from viewing another school', function () {
    $schoolA = School::create(['name' => 'School A']);
    $schoolB = School::create(['name' => 'School B']);

    $principal = User::factory()->create();
    Membership::create([
        'user_id' => $principal->id,
        'school_id' => $schoolA->id,
        'role' => Role::Principal->value,
        'scope' => Role::Principal->scope()->value,
        'is_active' => true,
    ]);

    Sanctum::actingAs($principal);

    $this->getJson("/api/v1/schools/{$schoolA->id}")->assertOk();
    $this->getJson("/api/v1/schools/{$schoolB->id}")->assertForbidden();
});

it('forbids a principal from the school management list and export', function () {
    $school = School::create(['name' => 'School A']);

    $principal = User::factory()->create();
    Membership::create([
        'user_id' => $principal->id,
        'school_id' => $school->id,
        'role' => Role::Principal->value,
        'scope' => Role::Principal->scope()->value,
        'is_active' => true,
    ]);

    Sanctum::actingAs($principal);

    // School Management (the list + export) is Temari.et staff only. A principal
    // may still open their own school profile via GET /schools/{id}.
    $this->getJson('/api/v1/schools')->assertForbidden();
    $this->getJson('/api/v1/schools/export')->assertForbidden();
    $this->getJson("/api/v1/schools/{$school->id}")->assertOk();
});

it('forbids a director from viewing any school profile', function () {
    $school = School::create(['name' => 'Unity Academy']);
    $branch = $school->branches()->create(['name' => 'Bole', 'code' => 'AA-0001']);

    Sanctum::actingAs(directorOf($branch));

    // A director operates inside their branch and has no school-level access at
    // all — not the list, not a single school profile.
    $this->getJson('/api/v1/schools')->assertForbidden();
    $this->getJson("/api/v1/schools/{$school->id}")->assertForbidden();
});

it('filters the school index by search term and active status', function () {
    School::create(['name' => 'Unity Academy', 'is_active' => true]);
    School::create(['name' => 'Bright Future School', 'is_active' => false]);
    Sanctum::actingAs(platformAdmin());

    $this->getJson('/api/v1/schools?search=unity')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Unity Academy');

    $this->getJson('/api/v1/schools?is_active=false')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Bright Future School');
});

it('sorts the school index by a whitelisted column', function () {
    School::create(['name' => 'Zebra School']);
    School::create(['name' => 'Alpha School']);
    Sanctum::actingAs(platformAdmin());

    $response = $this->getJson('/api/v1/schools?sort=name&dir=asc')->assertOk();

    expect($response->json('data.0.name'))->toBe('Alpha School');
    expect($response->json('data.1.name'))->toBe('Zebra School');
});

it('exports schools respecting the active filters', function () {
    School::create(['name' => 'Unity Academy']);
    School::create(['name' => 'Bright Future School']);
    Sanctum::actingAs(platformAdmin());

    $this->getJson('/api/v1/schools/export?search=bright')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Bright Future School');
});

it('exposes the principal and IT admin contacts on the school resource', function () {
    Sanctum::actingAs(platformAdmin());

    $this->postJson('/api/v1/schools', [
        'name' => 'Unity Academy',
        'principal_name' => 'Abebe Kebede',
        'principal_phone' => '0911223344',
        'technical_name' => 'Sara Tesfaye',
        'technical_phone' => '0911556677',
    ])->assertCreated();

    $school = School::firstWhere('name', 'Unity Academy');

    $this->getJson("/api/v1/schools/{$school->id}")
        ->assertOk()
        ->assertJsonPath('data.principal.name', 'Abebe Kebede')
        ->assertJsonPath('data.principal.phone', '0911223344')
        ->assertJsonPath('data.principal.is_active', true)
        ->assertJsonPath('data.school_admin.name', 'Sara Tesfaye');
});

it('lets a platform admin replace the school principal', function () {
    Sanctum::actingAs(platformAdmin());

    $this->postJson('/api/v1/schools', [
        'name' => 'Unity Academy',
        'principal_name' => 'Abebe Kebede',
        'principal_phone' => '0911223344',
    ])->assertCreated();

    $school = School::firstWhere('name', 'Unity Academy');
    $oldMembership = Membership::where('school_id', $school->id)
        ->where('role', Role::Principal->value)->first();

    $this->putJson("/api/v1/schools/{$school->id}/contacts", [
        'role' => 'principal',
        'name' => 'Lidiya Mekonnen',
        'phone' => '0922334455',
    ])
        ->assertOk()
        ->assertJsonPath('data.principal.name', 'Lidiya Mekonnen')
        ->assertJsonPath('data.principal.phone', '0922334455');

    expect($oldMembership->fresh()->is_active)->toBeFalse();

    $new = User::firstWhere('phone', '0922334455');
    expect($new)->not->toBeNull();
    expect(hasMembershipRole($new, Role::Principal))->toBeTrue();

    $active = Membership::where('school_id', $school->id)
        ->where('role', Role::Principal->value)
        ->where('is_active', true)->get();
    expect($active)->toHaveCount(1);
    expect($active->first()->user_id)->toBe($new->id);
});

it('forbids a principal from replacing a school-level contact', function () {
    $school = School::create(['name' => 'Unity Academy']);

    $principal = User::factory()->create();
    Membership::create([
        'user_id' => $principal->id,
        'school_id' => $school->id,
        'role' => Role::Principal->value,
        'scope' => Role::Principal->scope()->value,
        'is_active' => true,
    ]);
    Sanctum::actingAs($principal);

    $this->putJson("/api/v1/schools/{$school->id}/contacts", [
        'role' => 'principal',
        'name' => 'Someone Else',
        'phone' => '0933445566',
    ])->assertForbidden();
});

// ───────────────────────── school logo ─────────────────────────

it('lets platform staff set and remove the school logo', function () {
    Storage::fake(config('filesystems.default'));

    $school = School::create(['name' => 'Unity Academy']);
    Sanctum::actingAs(superAdmin());

    $res = $this->postJson("/api/v1/schools/{$school->id}/logo", [
        'logo' => UploadedFile::fake()->image('logo.png', 300, 300),
    ])->assertOk();

    expect($res->json('data.logo_url'))->not->toBeNull();
    $school->refresh();
    expect($school->logo_path)->not->toBeNull();
    Storage::disk(config('filesystems.default'))->assertExists($school->logo_path);

    // The resource carries the logo for every reader.
    $this->getJson("/api/v1/schools/{$school->id}")
        ->assertOk()
        ->assertJsonPath('data.logo_url', fn ($url) => $url !== null);

    $path = $school->logo_path;
    $this->deleteJson("/api/v1/schools/{$school->id}/logo")->assertOk();
    expect($school->refresh()->logo_path)->toBeNull();
    Storage::disk(config('filesystems.default'))->assertMissing($path);
});

it('forbids school managers from touching the logo', function () {
    Storage::fake(config('filesystems.default'));

    $school = School::create(['name' => 'Unity Academy']);
    $branch = $school->branches()->create(['name' => 'Main', 'code' => 'AA-0001']);

    // The principal MANAGES the school — but the logo prints on official
    // documents, so it stays platform-only.
    Sanctum::actingAs(schoolPrincipal($branch));
    $this->withHeaders(schoolContext($school))
        ->postJson("/api/v1/schools/{$school->id}/logo", [
            'logo' => UploadedFile::fake()->image('logo.png'),
        ])->assertForbidden();
    $this->withHeaders(schoolContext($school))
        ->deleteJson("/api/v1/schools/{$school->id}/logo")
        ->assertForbidden();

    // A branch director has even less standing.
    Sanctum::actingAs(directorOf($branch));
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/schools/{$school->id}/logo", [
            'logo' => UploadedFile::fake()->image('logo.png'),
        ])->assertForbidden();
});

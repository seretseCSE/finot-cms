<?php

use App\Enums\Role;
use App\Models\Branch;
use App\Models\ParentProfile;
use App\Models\SchoolDirectoryEntry;
use App\Models\Student;
use App\Models\User;
use App\Services\Sms\SmsClient;
use Database\Seeders\GradeLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(GradeLevelSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->sms = Mockery::spy(SmsClient::class);
    app()->instance(SmsClient::class, $this->sms);
});

function relStudentIn(Branch $branch, string $first = 'Kid'): Student
{
    return Student::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => $first, 'father_name' => 'Test', 'gender' => 'male',
    ]);
}

/* ------------------------------------------------------------------ */
/* Guardian search + attach existing (cross-school reuse) */
/* ------------------------------------------------------------------ */

it('attaches an existing parent from another school without duplicating the account', function () {
    $branchA = makeBranch('AA-0001');
    $branchB = makeBranch('BB-0001');

    // Parent created at school A.
    Sanctum::actingAs(directorOf($branchA));
    $this->withHeaders(branchContext($branchA))
        ->postJson('/api/v1/students/'.relStudentIn($branchA)->id.'/guardians', [
            'first_name' => 'Mulu', 'father_name' => 'Bekele',
            'phone' => '0911223344', 'relationship' => 'mother',
        ])->assertCreated();

    $parent = ParentProfile::first();
    expect($parent->user->name)->toBe('Mulu Bekele');

    // School B finds them by public id and attaches — no second user/profile.
    Sanctum::actingAs(directorOf($branchB));
    $search = $this->withHeaders(branchContext($branchB))
        ->getJson('/api/v1/guardians/search?q='.strtolower($parent->user->public_id))
        ->assertOk();

    expect($search->json('data'))->toHaveCount(1);
    expect($search->json('data.0.parent_id'))->toBe($parent->id);
    // Phone is masked — never fully exposed in cross-school search.
    expect($search->json('data.0.phone'))->not->toBe('0911223344');

    $this->withHeaders(branchContext($branchB))
        ->postJson('/api/v1/students/'.relStudentIn($branchB, 'Second')->id.'/guardians', [
            'parent_id' => $parent->id, 'relationship' => 'mother',
        ])->assertCreated();

    expect(ParentProfile::count())->toBe(1);
    expect(User::where('phone', '0911223344')->count())->toBe(1);
    expect($parent->guardianships()->count())->toBe(2);
});

it('rejects a new guardian whose email belongs to another account, keyed to the row', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));
    User::factory()->create(['email' => 'taken@example.com']);

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/students', [
            'first_name' => 'Kid', 'father_name' => 'Test', 'gender' => 'male',
            'guardians' => [[
                'first_name' => 'Mulu', 'father_name' => 'Bekele',
                'phone' => '0911000006', 'email' => 'taken@example.com',
                'relationship' => 'father',
            ]],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('guardians.0.email');

    // The whole registration rolled back — no orphan student.
    expect(Student::where('first_name', 'Kid')->exists())->toBeFalse();
});

it('denies guardian search without guardians.manage in the active context', function () {
    $branch = makeBranch();
    $teacher = memberOf($branch, Role::Teacher);
    Sanctum::actingAs($teacher);

    $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/guardians/search?q=0911')
        ->assertForbidden();
});

it('texts a setup link to new guardian accounts and a notice to existing ones', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));

    // New account → password-setup SMS (contains a set-password link).
    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/students/'.relStudentIn($branch, 'First')->id.'/guardians', [
            'first_name' => 'Abeba', 'father_name' => 'Haile',
            'phone' => '0911000001', 'relationship' => 'mother',
        ])->assertCreated();

    $this->sms->shouldHaveReceived('send')
        ->withArgs(fn ($to, $body) => $to === '0911000001' && str_contains($body, 'set-password'))
        ->once();

    // Existing account (has a password) → contextual notice, no setup link.
    $existing = User::create([
        'name' => 'Kebede Alemu', 'phone' => '0911000002', 'password' => Hash::make('secret123'),
    ]);

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/students/'.relStudentIn($branch, 'Second')->id.'/guardians', [
            'first_name' => 'Kebede', 'father_name' => 'Alemu',
            'phone' => '0911000002', 'relationship' => 'father',
        ])->assertCreated();

    $this->sms->shouldHaveReceived('send')
        ->withArgs(fn ($to, $body) => $to === '0911000002' && ! str_contains($body, 'set-password'))
        ->once();
});

it('localizes the contextual SMS to the guardian preferred language', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));

    User::create([
        'name' => 'Amharic Parent', 'phone' => '0911000003',
        'password' => Hash::make('secret123'), 'preferred_language' => 'am',
    ]);

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/students/'.relStudentIn($branch, 'Third')->id.'/guardians', [
            'first_name' => 'Amharic', 'father_name' => 'Parent',
            'phone' => '0911000003', 'relationship' => 'father',
        ])->assertCreated();

    $this->sms->shouldHaveReceived('send')
        ->withArgs(fn ($to, $body) => $to === '0911000003' && str_contains($body, 'ተመዝግቧል'))
        ->once();
});

/* ------------------------------------------------------------------ */
/* /me/preferences */
/* ------------------------------------------------------------------ */

it('lets any account read and update its own preferences', function () {
    // Refresh so DB defaults (notify flags) are hydrated like a real request.
    $user = User::factory()->create(['phone' => '0911000010'])->refresh();
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/me/preferences')
        ->assertOk()
        ->assertJsonPath('data.preferred_language', 'en')
        ->assertJsonPath('data.notify_via_sms', true);

    $this->putJson('/api/v1/me/preferences', [
        'preferred_language' => 'om',
        'notify_via_email' => false,
    ])->assertOk()
        ->assertJsonPath('data.preferred_language', 'om')
        ->assertJsonPath('data.notify_via_email', false);

    expect($user->fresh()->preferred_language)->toBe('om');
});

it('rejects preferred languages outside the UI locales', function () {
    $user = User::factory()->create(['phone' => '0911000011']);
    Sanctum::actingAs($user);

    $this->putJson('/api/v1/me/preferences', ['preferred_language' => 'fr'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('preferred_language');
});

/* ------------------------------------------------------------------ */
/* School directory */
/* ------------------------------------------------------------------ */

it('lets staff search the directory and add unverified entries with provenance', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));
    SchoolDirectoryEntry::create(['name' => 'Hawassa Tabor Secondary School', 'city' => 'Hawassa', 'is_verified' => true]);

    $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/school-directory?q=tabor')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Hawassa Tabor Secondary School');

    $created = $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/school-directory', ['name' => 'Rural Village School', 'region' => 'Oromia'])
        ->assertCreated();

    $entry = SchoolDirectoryEntry::find($created->json('data.id'));
    expect($entry->is_verified)->toBeFalse();
    expect($entry->created_by_school_id)->toBe($branch->school_id);
});

it('reuses an existing directory row instead of duplicating on exact match', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));
    $existing = SchoolDirectoryEntry::create(['name' => 'St. Joseph School', 'is_verified' => true]);

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/school-directory', ['name' => 'st. joseph school'])
        ->assertOk()
        ->assertJsonPath('data.id', $existing->id);

    expect(SchoolDirectoryEntry::count())->toBe(1);
});

it('restricts directory verification and deletion to platform staff', function () {
    $branch = makeBranch();
    $entry = SchoolDirectoryEntry::create(['name' => 'Unverified School']);

    Sanctum::actingAs(directorOf($branch));
    $this->withHeaders(branchContext($branch))
        ->patchJson("/api/v1/school-directory/{$entry->id}/verify")
        ->assertForbidden();

    Sanctum::actingAs(platformAdmin());
    $this->patchJson("/api/v1/school-directory/{$entry->id}/verify")->assertOk();
    expect($entry->fresh()->is_verified)->toBeTrue();
});

it('creates a verified directory row for every Temari-hosted school', function () {
    Sanctum::actingAs(platformAdmin());

    $this->postJson('/api/v1/schools', [
        'name' => 'New Temari School',
        'principal_name' => 'Principal Person',
        'principal_phone' => '0911000020',
    ])->assertCreated();

    $entry = SchoolDirectoryEntry::firstWhere('name', 'New Temari School');
    expect($entry)->not->toBeNull();
    expect($entry->is_verified)->toBeTrue();
    expect($entry->school_id)->not->toBeNull();
});

/* ------------------------------------------------------------------ */
/* Student self accounts */
/* ------------------------------------------------------------------ */

it('provisions a student login by default, honouring an explicit opt-out', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));

    // Explicit opt-out: no user account.
    $without = $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/students', [
            'first_name' => 'NoLogin', 'father_name' => 'Kid', 'gender' => 'male',
            'primary_phone' => '0911000030',
            'create_user_account' => false,
            'guardians' => [guardianPayload()],
        ])->assertCreated();
    expect(Student::find($without->json('data.id'))->user_id)->toBeNull();

    // Flag omitted: every student gets credentials from day one — account
    // created + setup SMS.
    $with = $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/students', [
            'first_name' => 'HasLogin', 'father_name' => 'Teen', 'gender' => 'female',
            'primary_phone' => '0911000031',
            'guardians' => [guardianPayload()],
        ])->assertCreated();

    $student = Student::find($with->json('data.id'));
    expect($student->user_id)->not->toBeNull();
    $this->sms->shouldHaveReceived('send')
        ->withArgs(fn ($to, $body) => $to === '0911000031' && str_contains($body, 'set-password'))
        ->once();

    // The student can then use the relationship lane.
    Sanctum::actingAs(User::findOrFail($student->user_id));
    $this->getJson('/api/v1/me/student')
        ->assertOk()
        ->assertJsonPath('data.full_name', 'HasLogin Teen');
});

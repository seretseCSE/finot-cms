<?php

use App\Enums\Role;
use App\Models\Branch;
use App\Models\Membership;
use App\Models\ParentProfile;
use App\Models\Student;
use App\Models\StudentGuardian;
use App\Models\User;
use App\Services\Sms\SmsClient;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->sms = Mockery::spy(SmsClient::class);
    app()->instance(SmsClient::class, $this->sms);
});

function studentIn(Branch $branch, string $first = 'Test', string $father = 'Student'): Student
{
    return Student::create([
        'school_id' => $branch->school_id,
        'branch_id' => $branch->id,
        'first_name' => $first,
        'father_name' => $father,
        'gender' => 'male',
    ]);
}

it('adds a guardian, provisioning a parent account and texting a setup link', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));
    $student = studentIn($branch);

    $response = $this->postJson("/api/v1/students/{$student->id}/guardians", [
        'name' => 'Mulu Bekele',
        'phone' => '0911222333',
        'relationship' => 'mother',
        'is_primary' => true,
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Mulu Bekele')
        ->assertJsonPath('data.relationship', 'mother')
        ->assertJsonPath('data.is_primary', true);

    $user = User::firstWhere('phone', '0911222333');
    // Parent-ness is a relationship, never a role/membership (ADR-012).
    expect($user->memberships()->exists())->toBeFalse();
    expect(ParentProfile::where('user_id', $user->id)->exists())->toBeTrue();
    expect(StudentGuardian::find($response->json('data.id'))->student_id)->toBe($student->id);

    $this->sms->shouldHaveReceived('send')->once();
});

it('rejects linking the same guardian to a student twice', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));
    $student = studentIn($branch);

    $payload = ['name' => 'Abebe Kebede', 'phone' => '0911444555', 'relationship' => 'father'];

    $this->postJson("/api/v1/students/{$student->id}/guardians", $payload)->assertCreated();

    $this->postJson("/api/v1/students/{$student->id}/guardians", $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors('phone');
});

it('keeps only one primary guardian per student', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));
    $student = studentIn($branch);

    $first = $this->postJson("/api/v1/students/{$student->id}/guardians", [
        'name' => 'Mother One', 'phone' => '0911000111', 'relationship' => 'mother', 'is_primary' => true,
    ])->json('data.id');

    $this->postJson("/api/v1/students/{$student->id}/guardians", [
        'name' => 'Father Two', 'phone' => '0911000222', 'relationship' => 'father', 'is_primary' => true,
    ])->assertCreated();

    expect(StudentGuardian::find($first)->is_primary)->toBeFalse();
    expect(StudentGuardian::where('student_id', $student->id)->where('is_primary', true)->count())->toBe(1);
});

it('lists and updates guardian permissions', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));
    $student = studentIn($branch);

    $guardianId = $this->postJson("/api/v1/students/{$student->id}/guardians", [
        'name' => 'Sara Lemma', 'phone' => '0911777888', 'relationship' => 'mother',
    ])->json('data.id');

    $this->getJson("/api/v1/students/{$student->id}/guardians")
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->putJson("/api/v1/guardians/{$guardianId}", [
        'relationship' => 'legal_guardian',
        'can_pay_fees' => false,
    ])
        ->assertOk()
        ->assertJsonPath('data.relationship', 'legal_guardian')
        ->assertJsonPath('data.can_pay_fees', false);
});

it('updates the guardian profile fields (name, phone, email) on the person record', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));
    $student = studentIn($branch);

    $guardianId = $this->postJson("/api/v1/students/{$student->id}/guardians", [
        'first_name' => 'Almaz', 'father_name' => 'Tesfaye', 'phone' => '0911999000', 'relationship' => 'mother',
    ])->json('data.id');

    $this->putJson("/api/v1/guardians/{$guardianId}", [
        'relationship' => 'mother',
        'first_name' => 'Almaz',
        'father_name' => 'Tesfaye',
        'grandfather_name' => 'Bekele',
        'phone' => '0911999111',
        'email' => 'almaz@example.com',
        'occupation' => 'Nurse',
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Almaz Tesfaye Bekele')
        ->assertJsonPath('data.phone', '0911999111')
        ->assertJsonPath('data.email', 'almaz@example.com')
        ->assertJsonPath('data.occupation', 'Nurse');

    expect(User::firstWhere('phone', '0911999111'))->not->toBeNull();
});

it('rejects a guardian phone update that collides with another account', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));
    $student = studentIn($branch);

    User::factory()->create(['phone' => '0911555666']);

    $guardianId = $this->postJson("/api/v1/students/{$student->id}/guardians", [
        'name' => 'Taken Phone', 'phone' => '0911555777', 'relationship' => 'father',
    ])->json('data.id');

    $this->putJson("/api/v1/guardians/{$guardianId}", [
        'relationship' => 'father',
        'phone' => '0911555666',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('phone');
});

it('removes a guardian', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));
    $student = studentIn($branch);

    // Keep one guardian on file — a student must always have at least one.
    $this->postJson("/api/v1/students/{$student->id}/guardians", [
        'name' => 'Kept Guardian', 'phone' => '0911999111', 'relationship' => 'mother',
    ])->assertCreated();

    $guardianId = $this->postJson("/api/v1/students/{$student->id}/guardians", [
        'name' => 'Temp Guardian', 'phone' => '0911999000', 'relationship' => 'other',
    ])->json('data.id');

    $this->deleteJson("/api/v1/guardians/{$guardianId}")->assertOk();

    expect(StudentGuardian::find($guardianId))->toBeNull();
});

it('refuses to remove the last guardian of a student', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));
    $student = studentIn($branch);

    $guardianId = $this->postJson("/api/v1/students/{$student->id}/guardians", [
        'name' => 'Only Guardian', 'phone' => '0911999222', 'relationship' => 'father',
    ])->json('data.id');

    $this->deleteJson("/api/v1/guardians/{$guardianId}")->assertStatus(422);

    expect(StudentGuardian::find($guardianId))->not->toBeNull();
});

it('forbids a teacher from adding a guardian', function () {
    $branch = makeBranch();
    $teacher = User::factory()->create();
    Membership::create([
        'user_id' => $teacher->id, 'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'role' => Role::Teacher->value, 'scope' => Role::Teacher->scope()->value, 'is_active' => true,
    ]);
    Sanctum::actingAs($teacher);
    $student = studentIn($branch);

    $this->postJson("/api/v1/students/{$student->id}/guardians", [
        'name' => 'X Y', 'phone' => '0911121212', 'relationship' => 'father',
    ])->assertForbidden();
});

it('forbids managing guardians for a student in another branch', function () {
    $branchA = makeBranch('AA-0001');
    $branchB = makeBranch('AA-0002');
    Sanctum::actingAs(directorOf($branchA));
    $studentB = studentIn($branchB);

    $this->postJson("/api/v1/students/{$studentB->id}/guardians", [
        'name' => 'X Y', 'phone' => '0911343434', 'relationship' => 'father',
    ])->assertForbidden();
});

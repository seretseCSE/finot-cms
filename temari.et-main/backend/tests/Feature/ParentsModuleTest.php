<?php

use App\Enums\Role;
use App\Models\Branch;
use App\Models\ParentProfile;
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

/** Creates a parent (user + profile) linked to a fresh student at the branch. */
function parentAt(Branch $branch, string $name, string $phone): ParentProfile
{
    $student = Student::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => "Child of {$name}", 'father_name' => 'Test', 'gender' => 'male',
    ]);

    $user = User::factory()->create(['name' => $name, 'phone' => $phone]);
    $parent = ParentProfile::create(['user_id' => $user->id]);
    StudentGuardian::create([
        'student_id' => $student->id, 'parent_id' => $parent->id,
        'relationship' => 'mother', 'is_active' => true,
    ]);

    return $parent;
}

it('lists only parents of students administered in the active context', function () {
    $branchA = makeBranch('AA-0001');
    $branchB = makeBranch('BB-0001');

    parentAt($branchA, 'Parent A', '0911100001');
    parentAt($branchB, 'Parent B', '0911100002');

    Sanctum::actingAs(directorOf($branchA));

    $response = $this->withHeaders(branchContext($branchA))
        ->getJson('/api/v1/parents')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.name'))->toBe('Parent A');
    expect($response->json('data.0.children_count'))->toBe(1);
});

it('finds parents by public id case-insensitively', function () {
    $branch = makeBranch();
    $parent = parentAt($branch, 'Findable Parent', '0911100003');

    Sanctum::actingAs(directorOf($branch));

    $response = $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/parents?search='.strtolower($parent->user->public_id))
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($parent->id);
});

it('shows a parent profile with children only to staff of a linked scope', function () {
    $branchA = makeBranch('AA-0001');
    $branchB = makeBranch('BB-0001');
    $parent = parentAt($branchA, 'Scoped Parent', '0911100004');

    Sanctum::actingAs(directorOf($branchA));
    $this->withHeaders(branchContext($branchA))
        ->getJson("/api/v1/parents/{$parent->id}")
        ->assertOk()
        ->assertJsonPath('data.name', 'Scoped Parent')
        ->assertJsonCount(1, 'data.children');

    // Staff of another school never see this parent.
    Sanctum::actingAs(directorOf($branchB));
    $this->withHeaders(branchContext($branchB))
        ->getJson("/api/v1/parents/{$parent->id}")
        ->assertForbidden();
});

it('denies the parents list to roles without guardians.view', function () {
    $branch = makeBranch();
    parentAt($branch, 'Hidden Parent', '0911100005');

    // Finance officers hold students.view but not guardians.view.
    $finance = memberOf($branch, Role::FinanceOfficer);
    Sanctum::actingAs($finance);

    $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/parents')
        ->assertForbidden();
});

it('exports the scoped parents list', function () {
    $branch = makeBranch();
    parentAt($branch, 'Export Parent', '0911100006');

    Sanctum::actingAs(directorOf($branch));

    $response = $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/parents/export')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1);
});

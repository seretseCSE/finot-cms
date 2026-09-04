<?php

use App\Enums\Role;
use App\Models\Membership;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('gives a director only their own branch', function () {
    $branch = makeBranch();
    // A second branch in the same school the director must NOT see.
    $branch->school->branches()->create(['name' => 'Other', 'code' => 'AA-0002']);

    Sanctum::actingAs(directorOf($branch));

    $response = $this->getJson('/api/v1/auth/contexts')->assertOk();

    expect($response->json('data.is_platform'))->toBeFalse();
    expect($response->json('data.schools'))->toHaveCount(1);
    expect($response->json('data.schools.0.can_manage'))->toBeFalse();
    expect($response->json('data.schools.0.branches'))->toHaveCount(1);
    expect($response->json('data.schools.0.branches.0.id'))->toBe($branch->id);
});

it('gives a principal every branch of their school as a manager', function () {
    $branch = makeBranch();
    $branch->school->branches()->create(['name' => 'Second', 'code' => 'AA-0002']);

    $principal = User::factory()->create();
    Membership::create([
        'user_id' => $principal->id,
        'school_id' => $branch->school_id,
        'role' => Role::Principal->value,
        'scope' => Role::Principal->scope()->value,
        'is_active' => true,
    ]);
    Sanctum::actingAs($principal);

    $response = $this->getJson('/api/v1/auth/contexts')->assertOk();

    expect($response->json('data.schools.0.can_manage'))->toBeTrue();
    expect($response->json('data.schools.0.branches'))->toHaveCount(2);
});

it('gives platform staff every active school and branch', function () {
    $branch = makeBranch();
    $branch->school->branches()->create(['name' => 'Second', 'code' => 'AA-0002']);

    $superAdmin = User::factory()->create();
    grantPlatformRole($superAdmin, Role::SuperAdmin);
    Sanctum::actingAs($superAdmin);

    $response = $this->getJson('/api/v1/auth/contexts')->assertOk();

    expect($response->json('data.is_platform'))->toBeTrue();
    expect($response->json('data.schools.0.branches'))->toHaveCount(2);
});

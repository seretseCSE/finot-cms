<?php

use App\Enums\Role;
use App\Models\Employee;
use App\Models\Membership;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('suspends branch-scoped activity for branch members when the branch is deactivated', function () {
    $branch = makeBranch();
    $director = directorOf($branch);

    Sanctum::actingAs($director);

    // Active branch: full access.
    $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/employees')
        ->assertOk();

    $branch->update(['is_active' => false]);

    // Deactivated branch: every permission riding on the branch membership is gone.
    Sanctum::actingAs($director->fresh());

    $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/employees')
        ->assertForbidden();

    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/employees', [
            'first_name' => 'Blocked',
            'phone' => '0911000111',
            'positions' => [['job_title' => 'teacher', 'is_primary' => true, 'hired_on' => '2024-09-01']],
        ])
        ->assertForbidden();
});

it('keeps school managers in control of a deactivated branch so they can reactivate it', function () {
    $branch = makeBranch();
    $branch->update(['is_active' => false]);

    $principal = schoolPrincipal($branch);
    Sanctum::actingAs($principal);

    // The principal still sees the branch list and can flip the switch back on.
    $this->withHeaders(schoolContext($branch))
        ->getJson('/api/v1/branches')
        ->assertOk();

    $this->withHeaders(schoolContext($branch))
        ->patchJson("/api/v1/branches/{$branch->id}", ['is_active' => true])
        ->assertOk()
        ->assertJsonPath('data.is_active', true);

    expect($branch->fresh()->is_active)->toBeTrue();
});

it('restores branch member access when the branch is reactivated', function () {
    $branch = makeBranch();
    $director = directorOf($branch);

    $branch->update(['is_active' => false]);
    Sanctum::actingAs($director);
    $this->withHeaders(branchContext($branch))->getJson('/api/v1/employees')->assertForbidden();

    $branch->update(['is_active' => true]);
    Sanctum::actingAs(User::find($director->id));
    $this->withHeaders(branchContext($branch))->getJson('/api/v1/employees')->assertOk();
});

it('does not affect the same user\'s access at other branches', function () {
    $inactive = makeBranch('AA-0001');
    $active = makeBranch('AA-0002');

    $director = directorOf($inactive);
    // Same person also directs the second branch.
    Membership::create([
        'user_id' => $director->id,
        'school_id' => $active->school_id,
        'branch_id' => $active->id,
        'role' => Role::Director->value,
        'scope' => Role::Director->scope()->value,
        'is_active' => true,
    ]);

    Employee::create([
        'user_id' => User::factory()->create()->id,
        'school_id' => $active->school_id,
        'branch_id' => $active->id,
        'first_name' => 'Visible',
    ]);

    $inactive->update(['is_active' => false]);
    Sanctum::actingAs($director);

    $this->withHeaders(branchContext($inactive))->getJson('/api/v1/employees')->assertForbidden();
    $this->withHeaders(branchContext($active))->getJson('/api/v1/employees')->assertOk();
});

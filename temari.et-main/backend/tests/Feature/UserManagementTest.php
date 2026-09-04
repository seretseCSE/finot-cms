<?php

use App\Enums\AccountStatus;
use App\Enums\Role;
use App\Models\Membership;
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

// ── Visibility / scoping ────────────────────────────────────────────────────

it('lets a platform admin see every user', function () {
    $branch = makeBranch();
    $teacher = memberOf($branch);

    Sanctum::actingAs(platformAdmin());

    $ids = collect($this->getJson('/api/v1/users')->assertOk()->json('data'))->pluck('id');

    expect($ids)->toContain($teacher->id);
});

it('scopes a principal to users across their school only', function () {
    $branchA = makeBranch('AA-0001');
    $branchA2 = $branchA->school->branches()->create(['name' => 'Second', 'code' => 'AA-0002']);
    $otherSchoolBranch = makeBranch('BB-0001');

    $teacherA = memberOf($branchA);
    $teacherA2 = memberOf($branchA2);
    $teacherOther = memberOf($otherSchoolBranch);

    Sanctum::actingAs(schoolPrincipal($branchA));

    $ids = collect(
        $this->withHeaders(schoolContext($branchA))->getJson('/api/v1/users')->assertOk()->json('data')
    )->pluck('id');

    expect($ids)->toContain($teacherA->id)
        ->and($ids)->toContain($teacherA2->id)
        ->and($ids)->not->toContain($teacherOther->id);
});

it('scopes a director to their own branch only', function () {
    $branchA = makeBranch('AA-0001');
    $branchA2 = $branchA->school->branches()->create(['name' => 'Second', 'code' => 'AA-0002']);

    $teacherA = memberOf($branchA);
    $teacherA2 = memberOf($branchA2);

    Sanctum::actingAs(directorOf($branchA));

    $ids = collect(
        $this->withHeaders(branchContext($branchA))->getJson('/api/v1/users')->assertOk()->json('data')
    )->pluck('id');

    expect($ids)->toContain($teacherA->id)
        ->and($ids)->not->toContain($teacherA2->id);
});

it('scopes the users list to the active branch context', function () {
    $branchA1 = makeBranch('AA-0001');
    $branchA2 = $branchA1->school->branches()->create(['name' => 'Second', 'code' => 'AA-0002']);
    $teacher1 = memberOf($branchA1);
    $teacher2 = memberOf($branchA2);

    Sanctum::actingAs(schoolPrincipal($branchA1));

    // Deny-by-default (ADR-010): without a validated context header, school
    // roles grant nothing at all.
    $this->getJson('/api/v1/users')->assertForbidden();

    // In the school context → the principal sees both branches' teachers.
    $all = collect(
        $this->withHeaders(schoolContext($branchA1))->getJson('/api/v1/users')->assertOk()->json('data')
    )->pluck('id');
    expect($all)->toContain($teacher1->id)->and($all)->toContain($teacher2->id);

    // Switching into branch A1 must actually narrow the list to that branch.
    $scoped = collect(
        $this->withHeaders(branchContext($branchA1))->getJson('/api/v1/users')->assertOk()->json('data')
    )->pluck('id');
    expect($scoped)->toContain($teacher1->id)->and($scoped)->not->toContain($teacher2->id);
});

it('switches the users list between a managed school and a director branch', function () {
    $branchA = makeBranch('AA-0001');   // school A — actor is principal
    $branchB = makeBranch('BB-0001');   // school B — actor is director
    $teacherA = memberOf($branchA);
    $teacherB = memberOf($branchB);

    // Actor: principal of school A AND director of branch B (the reported setup).
    $actor = schoolPrincipal($branchA);
    Membership::create([
        'user_id' => $actor->id,
        'school_id' => $branchB->school_id,
        'branch_id' => $branchB->id,
        'role' => Role::Director->value,
        'scope' => Role::Director->scope()->value,
        'is_active' => true,
    ]);

    Sanctum::actingAs($actor);

    // In school A's context → school A's teacher only.
    $inA = collect(
        $this->withHeaders(['X-School-Id' => (string) $branchA->school_id])
            ->getJson('/api/v1/users')->assertOk()->json('data')
    )->pluck('id');
    expect($inA)->toContain($teacherA->id)->and($inA)->not->toContain($teacherB->id);

    // Switching into school B / branch B → school B's teacher only.
    $inB = collect(
        $this->withHeaders(branchContext($branchB))->getJson('/api/v1/users')->assertOk()->json('data')
    )->pluck('id');
    expect($inB)->toContain($teacherB->id)->and($inB)->not->toContain($teacherA->id);
});

it('does not fall back to the primary branch column when a school context is active', function () {
    $branchA = makeBranch('AA-0001');   // school A — principal, all branches
    $branchB = makeBranch('BB-0001');   // school B — director + actor's primary branch
    $teacherA = memberOf($branchA);
    $teacherB = memberOf($branchB);

    // Actor: principal of school A + director of branch B — the shape that once
    // made activeBranchId() wrongly fall back to a "primary branch" column.
    // Users no longer carry such columns; this stays as a regression guard.
    $actor = schoolPrincipal($branchA);
    Membership::create([
        'user_id' => $actor->id,
        'school_id' => $branchB->school_id,
        'branch_id' => $branchB->id,
        'role' => Role::Director->value,
        'scope' => Role::Director->scope()->value,
        'is_active' => true,
    ]);
    Sanctum::actingAs($actor);

    // Switching into school A (all branches) must scope to school A, NOT silently
    // re-scope to the actor's primary branch B.
    $ids = collect(
        $this->withHeaders(['X-School-Id' => (string) $branchA->school_id])
            ->getJson('/api/v1/users')->assertOk()->json('data')
    )->pluck('id');

    expect($ids)->toContain($teacherA->id)->and($ids)->not->toContain($teacherB->id);
});

// ── Global status (platform only) ───────────────────────────────────────────

it('revokes live tokens immediately when a platform admin bans a user', function () {
    $branch = makeBranch();
    $teacher = memberOf($branch);
    $teacher->createToken('api');
    expect($teacher->tokens()->count())->toBe(1);

    Sanctum::actingAs(platformAdmin());
    $this->patchJson("/api/v1/users/{$teacher->id}/status", ['status' => 'banned'])
        ->assertOk();

    expect($teacher->fresh()->status)->toBe(AccountStatus::Banned)
        // All of the target's tokens are revoked, ending any live session.
        ->and($teacher->tokens()->count())->toBe(0);
});

it('blocks a still-tokened inactive user on every request with a code', function () {
    $user = User::factory()->inactive()->create();
    $token = $user->createToken('api')->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/auth/me')
        ->assertStatus(403)
        ->assertJsonPath('code', 'account_inactive');
});

it('blocks login for an inactive account', function () {
    User::factory()->inactive()->create(['phone' => '0911222333']);

    $this->postJson('/api/v1/auth/login', ['identifier' => '0911222333', 'password' => 'password'])
        ->assertStatus(422);
});

it('forbids a principal from changing global account status', function () {
    $branch = makeBranch();
    $teacher = memberOf($branch);

    Sanctum::actingAs(schoolPrincipal($branch));

    $this->patchJson("/api/v1/users/{$teacher->id}/status", ['status' => 'banned'])
        ->assertStatus(403);

    expect($teacher->fresh()->status)->toBe(AccountStatus::Active);
});

it('forbids a non-platform user from editing profile information', function () {
    $branch = makeBranch();
    $teacher = memberOf($branch);

    Sanctum::actingAs(schoolPrincipal($branch));

    $this->putJson("/api/v1/users/{$teacher->id}", [
        'name' => 'Hacked Name',
        'phone' => $teacher->phone,
    ])->assertStatus(403);
});

it('bulk-updates global status for a platform admin', function () {
    $u1 = User::factory()->create();
    $u2 = User::factory()->create();

    Sanctum::actingAs(platformAdmin());

    $this->postJson('/api/v1/users/bulk/status', ['ids' => [$u1->id, $u2->id], 'status' => 'inactive'])
        ->assertOk()
        ->assertJsonPath('meta.updated', 2);

    expect($u1->fresh()->status)->toBe(AccountStatus::Inactive)
        ->and($u2->fresh()->status)->toBe(AccountStatus::Inactive);
});

// ── Scoped membership access (principal / director) ─────────────────────────

it('lets a principal deactivate a branch membership without touching global status', function () {
    $branchA = makeBranch('AA-0001');
    $branchB = $branchA->school->branches()->create(['name' => 'B', 'code' => 'AA-0002']);

    $teacher = memberOf($branchA);
    $membershipB = Membership::create([
        'user_id' => $teacher->id,
        'school_id' => $branchB->school_id,
        'branch_id' => $branchB->id,
        'role' => Role::Teacher->value,
        'scope' => Role::Teacher->scope()->value,
        'is_active' => true,
    ]);
    $membershipA = Membership::where('user_id', $teacher->id)->where('branch_id', $branchA->id)->first();

    Sanctum::actingAs(schoolPrincipal($branchA));

    $this->patchJson("/api/v1/memberships/{$membershipA->id}/status", ['is_active' => false])
        ->assertOk();

    expect($membershipA->fresh()->is_active)->toBeFalse()
        ->and($membershipB->fresh()->is_active)->toBeTrue()
        ->and($teacher->fresh()->status)->toBe(AccountStatus::Active);
});

it('keeps a user visible and reactivatable after a director deactivates their only membership', function () {
    $branch = makeBranch();
    $teacher = memberOf($branch);
    $director = directorOf($branch);
    $membership = Membership::where('user_id', $teacher->id)->where('branch_id', $branch->id)->first();

    Sanctum::actingAs($director);

    // A director may ban/unban people already in their own branch (manage_branch_access).
    // Deactivating must not make the teacher disappear from the director's own list.
    $this->withHeaders(branchContext($branch))->patchJson("/api/v1/memberships/{$membership->id}/status", ['is_active' => false])->assertOk();

    $ids = collect($this->withHeaders(branchContext($branch))->getJson('/api/v1/users')->assertOk()->json('data'))->pluck('id');
    expect($ids)->toContain($teacher->id);

    $this->withHeaders(branchContext($branch))->getJson("/api/v1/users/{$teacher->id}")->assertOk();

    // And the director must still be able to flip it back on.
    $this->withHeaders(branchContext($branch))->patchJson("/api/v1/memberships/{$membership->id}/status", ['is_active' => true])
        ->assertOk();
    expect($membership->fresh()->is_active)->toBeTrue();
});

it('lets a director remove a member from their own branch', function () {
    $branch = makeBranch();
    $teacher = memberOf($branch);
    $membership = Membership::where('user_id', $teacher->id)->where('branch_id', $branch->id)->first();

    Sanctum::actingAs(directorOf($branch));

    $this->deleteJson("/api/v1/memberships/{$membership->id}")->assertOk();

    expect(Membership::find($membership->id))->toBeNull();
});

it('lets a principal assign an independent user to a branch in their school', function () {
    $branch = makeBranch();
    $newUser = User::factory()->create();

    Sanctum::actingAs(schoolPrincipal($branch));

    $this->withHeaders(schoolContext($branch))->postJson("/api/v1/users/{$newUser->id}/memberships", [
        'branch_id' => $branch->id,
        'role' => 'teacher',
    ])->assertOk();

    expect(Membership::where('user_id', $newUser->id)->where('branch_id', $branch->id)->where('is_active', true)->exists())->toBeTrue()
        ->and(hasMembershipRole($newUser->fresh(), Role::Teacher))->toBeTrue();
});

it('forbids a director from assigning a user to their branch', function () {
    $branch = makeBranch();
    $newUser = User::factory()->create();

    Sanctum::actingAs(directorOf($branch));

    $this->postJson("/api/v1/users/{$newUser->id}/memberships", [
        'branch_id' => $branch->id,
        'role' => 'teacher',
    ])->assertStatus(403);

    expect(Membership::where('user_id', $newUser->id)->where('branch_id', $branch->id)->exists())->toBeFalse();
});

it('scopes the assign permission to the active context, not the global role union', function () {
    $branchA = makeBranch('AA-0001');   // school A — actor is ONLY a director here
    $branchB = makeBranch('BB-0001');   // school B — actor is a principal here
    $newUser = User::factory()->create();

    // Actor: director of branch A AND principal of the unrelated school B. Globally
    // they hold `users.assign_branch` (via the principal role) — but that must only
    // take effect while they are actually acting in school B.
    $actor = directorOf($branchA);
    Membership::create([
        'user_id' => $actor->id,
        'school_id' => $branchB->school_id,
        'role' => Role::Principal->value,
        'scope' => Role::Principal->scope()->value,
        'is_active' => true,
    ]);

    Sanctum::actingAs($actor);

    // In the school A (director) context: assignment is denied.
    $this->withHeaders(branchContext($branchA))
        ->postJson("/api/v1/users/{$newUser->id}/memberships", ['branch_id' => $branchA->id, 'role' => 'teacher'])
        ->assertStatus(403);
    expect(Membership::where('user_id', $newUser->id)->where('branch_id', $branchA->id)->exists())->toBeFalse();

    // In the school B (principal) context: the SAME actor can assign.
    $this->withHeaders(['X-School-Id' => (string) $branchB->school_id])
        ->postJson("/api/v1/users/{$newUser->id}/memberships", ['branch_id' => $branchB->id, 'role' => 'teacher'])
        ->assertOk();
    expect(Membership::where('user_id', $newUser->id)->where('branch_id', $branchB->id)->where('is_active', true)->exists())->toBeTrue();
});

it('scopes a target user\'s visible + manageable branches to the actor\'s active context', function () {
    $branchA = makeBranch('AA-0001');   // school A — actor is a DIRECTOR here
    $branchB = makeBranch('BB-0001');   // school B — actor is a PRINCIPAL here

    // Actor: director at branch A AND principal of the unrelated school B.
    $actor = directorOf($branchA);
    Membership::create([
        'user_id' => $actor->id,
        'school_id' => $branchB->school_id,
        'role' => Role::Principal->value,
        'scope' => Role::Principal->scope()->value,
        'is_active' => true,
    ]);

    // Target: a teacher in BOTH branch A and (school B's) branch B.
    $target = memberOf($branchA, Role::Teacher);
    Membership::create([
        'user_id' => $target->id,
        'school_id' => $branchB->school_id,
        'branch_id' => $branchB->id,
        'role' => Role::Teacher->value,
        'scope' => Role::Teacher->scope()->value,
        'is_active' => true,
    ]);
    $membershipB = Membership::where('user_id', $target->id)->where('branch_id', $branchB->id)->first();

    Sanctum::actingAs($actor);

    // In the school A (director) context the target's branch list must expose ONLY
    // branch A — the school-B membership belongs to the actor's OTHER (principal) hat.
    $row = collect($this->withHeaders(branchContext($branchA))->getJson('/api/v1/users')->assertOk()->json('data'))
        ->firstWhere('id', $target->id);
    $branchIds = collect($row['branches'])->pluck('id');
    expect($branchIds)->toContain($branchA->id)
        ->and($branchIds)->not->toContain($branchB->id);

    // And managing the school-B membership from the school-A context is forbidden,
    // even though the actor is globally a principal of school B.
    $this->withHeaders(branchContext($branchA))
        ->patchJson("/api/v1/memberships/{$membershipB->id}/status", ['is_active' => false])
        ->assertStatus(403);
    expect($membershipB->fresh()->is_active)->toBeTrue();

    // Switching into the school B (principal) context flips it — now it is manageable.
    $this->withHeaders(['X-School-Id' => (string) $branchB->school_id])
        ->patchJson("/api/v1/memberships/{$membershipB->id}/status", ['is_active' => false])
        ->assertOk();
    expect($membershipB->fresh()->is_active)->toBeFalse();
});

it('flags a peer/higher membership as read-only but a lower one as manageable (hierarchy)', function () {
    $branch = makeBranch();
    $actor = directorOf($branch);

    $coDirector = directorOf($branch);              // peer — must be read-only
    $teacher = memberOf($branch, Role::Teacher);    // lower — manageable

    Sanctum::actingAs($actor);

    $rows = collect($this->withHeaders(branchContext($branch))->getJson('/api/v1/users')->assertOk()->json('data'));

    $peerBranch = collect($rows->firstWhere('id', $coDirector->id)['branches'])->firstWhere('id', $branch->id);
    $lowerBranch = collect($rows->firstWhere('id', $teacher->id)['branches'])->firstWhere('id', $branch->id);

    expect($peerBranch['can_manage'])->toBeFalse()
        ->and($lowerBranch['can_manage'])->toBeTrue();
});

it('hides a principal entirely from a director — not listed, not viewable, no branches revealed', function () {
    $branch = makeBranch();
    $principal = schoolPrincipal($branch);          // school-level principal (a superior)
    $teacher = memberOf($branch, Role::Teacher);    // a subordinate the director CAN see

    Sanctum::actingAs(directorOf($branch));

    $data = collect($this->withHeaders(branchContext($branch))->getJson('/api/v1/users')->assertOk()->json('data'));

    // The principal is absent from the director's list; the teacher is present.
    expect($data->pluck('id'))->not->toContain($principal->id)
        ->and($data->pluck('id'))->toContain($teacher->id);

    // And opening the principal directly by id is forbidden (no URL-guessing around it).
    $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/users/{$principal->id}")
        ->assertStatus(403);
});

it('lets a principal still see and manage a director (subordinate) normally', function () {
    $branch = makeBranch();
    $director = directorOf($branch);
    $membership = Membership::where('user_id', $director->id)->where('branch_id', $branch->id)->first();

    Sanctum::actingAs(schoolPrincipal($branch));

    $data = collect($this->withHeaders(branchContext($branch))->getJson('/api/v1/users')->assertOk()->json('data'));
    $row = $data->firstWhere('id', $director->id);

    // A principal outranks a director, so the director is listed AND manageable.
    expect($row)->not->toBeNull()
        ->and(collect($row['branches'])->firstWhere('id', $branch->id)['can_manage'])->toBeTrue();

    $this->withHeaders(branchContext($branch))
        ->patchJson("/api/v1/memberships/{$membership->id}/status", ['is_active' => false])
        ->assertOk();
});

it('forbids a director from managing any membership of a principal, even an incidental branch role', function () {
    $branch = makeBranch();
    $principal = schoolPrincipal($branch);   // school-level principal — outranks a director

    // The principal also teaches a class in this branch — an incidental lower role
    // that sits inside the director's own scope.
    $teacherMembership = Membership::create([
        'user_id' => $principal->id,
        'school_id' => $branch->school_id,
        'branch_id' => $branch->id,
        'role' => Role::Teacher->value,
        'scope' => Role::Teacher->scope()->value,
        'is_active' => true,
    ]);

    Sanctum::actingAs(directorOf($branch));

    // The person outranks the director here, so NONE of their memberships — not even
    // the branch teacher one — may be deactivated or removed by the director.
    $this->withHeaders(branchContext($branch))
        ->patchJson("/api/v1/memberships/{$teacherMembership->id}/status", ['is_active' => false])
        ->assertStatus(403);
    expect($teacherMembership->fresh()->is_active)->toBeTrue();

    $this->withHeaders(branchContext($branch))
        ->deleteJson("/api/v1/memberships/{$teacherMembership->id}")
        ->assertStatus(403);
    expect(Membership::find($teacherMembership->id))->not->toBeNull();
});

it('forbids a director from touching a membership in another school', function () {
    $branchA = makeBranch('AA-0001');
    $branchB = makeBranch('BB-0001');
    $teacherB = memberOf($branchB);
    $membershipB = Membership::where('user_id', $teacherB->id)->first();

    Sanctum::actingAs(directorOf($branchA));

    $this->patchJson("/api/v1/memberships/{$membershipB->id}/status", ['is_active' => false])
        ->assertStatus(403);

    expect($membershipB->fresh()->is_active)->toBeTrue();
});

it('forbids a director from deactivating a principal (hierarchy + scope)', function () {
    $branch = makeBranch();
    $principal = schoolPrincipal($branch);
    $principalMembership = Membership::where('user_id', $principal->id)->first();

    Sanctum::actingAs(directorOf($branch));

    $this->patchJson("/api/v1/memberships/{$principalMembership->id}/status", ['is_active' => false])
        ->assertStatus(403);
});

it('lets a principal manage an in-scope director who is a principal at another school', function () {
    $branchA = makeBranch('AA-0001');   // school A — the acting principal's school
    $branchB = makeBranch('BB-0001');   // school B — an unrelated school

    // Elon: director on branch A (in scope) AND principal of the unrelated school B.
    $elon = directorOf($branchA);
    Membership::create([
        'user_id' => $elon->id,
        'school_id' => $branchB->school_id,
        'role' => Role::Principal->value,
        'scope' => Role::Principal->scope()->value,
        'is_active' => true,
    ]);

    $directorMembership = Membership::where('user_id', $elon->id)->where('branch_id', $branchA->id)->first();
    $directorMembership->update(['is_active' => false]);

    Sanctum::actingAs(schoolPrincipal($branchA));

    // The principal of school A must be able to reactivate Elon's director
    // membership in their OWN school — his principal role at school B is irrelevant
    // and must not inflate his rank to block management here.
    $this->withHeaders(schoolContext($branchA))->patchJson("/api/v1/memberships/{$directorMembership->id}/status", ['is_active' => true])
        ->assertOk();

    expect($directorMembership->fresh()->is_active)->toBeTrue();

    // And they must be able to open his profile despite the cross-school principal role.
    $this->withHeaders(schoolContext($branchA))->getJson("/api/v1/users/{$elon->id}")->assertOk();
});

it('does not let a principal-elsewhere director outrank a co-director in their own branch', function () {
    $branchA = makeBranch('AA-0001');   // school A
    $branchB = makeBranch('BB-0001');   // school B

    // Actor: director of branch A, but also principal of the unrelated school B.
    $actor = directorOf($branchA);
    Membership::create([
        'user_id' => $actor->id,
        'school_id' => $branchB->school_id,
        'role' => Role::Principal->value,
        'scope' => Role::Principal->scope()->value,
        'is_active' => true,
    ]);

    // Target: a co-director of the SAME branch A.
    $coDirector = directorOf($branchA);
    $coMembership = Membership::where('user_id', $coDirector->id)->where('branch_id', $branchA->id)->first();

    Sanctum::actingAs($actor);

    // Within branch A the actor is only a director (level 2); their principal role
    // at school B must not inflate their authority to outrank a co-director here.
    $this->patchJson("/api/v1/memberships/{$coMembership->id}/status", ['is_active' => false])
        ->assertStatus(403);

    expect($coMembership->fresh()->is_active)->toBeTrue();
});

it('rejects assigning a school/platform role at branch level', function () {
    $branch = makeBranch();
    $newUser = User::factory()->create();

    Sanctum::actingAs(schoolPrincipal($branch));

    $this->withHeaders(schoolContext($branch))->postJson("/api/v1/users/{$newUser->id}/memberships", [
        'branch_id' => $branch->id,
        'role' => 'principal',
    ])->assertStatus(422);
});

// ── Filters / export / reset password ───────────────────────────────────────

it('filters users by global status', function () {
    $active = memberOf(makeBranch('AA-0001'));
    $inactive = User::factory()->inactive()->create();

    Sanctum::actingAs(platformAdmin());

    $ids = collect($this->getJson('/api/v1/users?status=inactive')->assertOk()->json('data'))->pluck('id');

    expect($ids)->toContain($inactive->id)
        ->and($ids)->not->toContain($active->id);
});

it('filters users by type (independent vs affiliated)', function () {
    $affiliated = memberOf(makeBranch('AA-0001'));
    $independent = User::factory()->create();

    Sanctum::actingAs(platformAdmin());

    $ids = collect($this->getJson('/api/v1/users?type=independent')->assertOk()->json('data'))->pluck('id');

    expect($ids)->toContain($independent->id)
        ->and($ids)->not->toContain($affiliated->id);
});

it('exports respect the principal scope', function () {
    $branchA = makeBranch('AA-0001');
    $otherBranch = makeBranch('BB-0001');
    $teacherA = memberOf($branchA);
    $teacherOther = memberOf($otherBranch);

    Sanctum::actingAs(schoolPrincipal($branchA));

    $ids = collect(
        $this->withHeaders(schoolContext($branchA))->getJson('/api/v1/users/export')->assertOk()->json('data')
    )->pluck('id');

    expect($ids)->toContain($teacherA->id)
        ->and($ids)->not->toContain($teacherOther->id);
});

it('lets a platform admin delete a user', function () {
    $branch = makeBranch();
    $teacher = memberOf($branch);

    Sanctum::actingAs(platformAdmin());

    $this->deleteJson("/api/v1/users/{$teacher->id}")->assertOk();

    expect(User::find($teacher->id))->toBeNull()
        ->and(User::withTrashed()->find($teacher->id))->not->toBeNull();
});

it('forbids a non-platform user from deleting a user', function () {
    $branch = makeBranch();
    $teacher = memberOf($branch);

    Sanctum::actingAs(schoolPrincipal($branch));

    $this->deleteJson("/api/v1/users/{$teacher->id}")->assertStatus(403);

    expect(User::find($teacher->id))->not->toBeNull();
});

it('lets a platform admin send a password reset link', function () {
    $branch = makeBranch();
    $teacher = memberOf($branch);

    Sanctum::actingAs(platformAdmin());

    $this->postJson("/api/v1/users/{$teacher->id}/reset-password")->assertOk();

    $this->sms->shouldHaveReceived('send')->once();
});

// ── Affiliation tree (School → Branch → Role) ───────────────────────────────

it('groups a user\'s memberships into a School → Branch → Role affiliation tree', function () {
    $branch = makeBranch('AA-0001');

    $user = User::factory()->create();
    // School-wide principal + branch teacher + platform staff (all memberships —
    // parent-ness is a relationship, not a role, and never appears here).
    grantPlatformRole($user, Role::SupportAgent);
    Membership::create([
        'user_id' => $user->id,
        'school_id' => $branch->school_id,
        'role' => Role::Principal->value,
        'scope' => Role::Principal->scope()->value,
        'is_active' => true,
    ]);
    Membership::create([
        'user_id' => $user->id,
        'school_id' => $branch->school_id,
        'branch_id' => $branch->id,
        'role' => Role::Teacher->value,
        'scope' => Role::Teacher->scope()->value,
        'is_active' => true,
    ]);

    Sanctum::actingAs(platformAdmin());

    $row = collect($this->getJson('/api/v1/users')->assertOk()->json('data'))
        ->firstWhere('id', $user->id);

    expect($row['affiliations'])->toHaveCount(1);

    $aff = $row['affiliations'][0];
    expect($aff['school_name'])->toBe('Unity Academy')
        ->and($aff['roles'])->toContain('principal')
        ->and($aff['branches'])->toHaveCount(1)
        ->and($aff['branches'][0]['name'])->toBe('Main')
        ->and($aff['branches'][0]['roles'])->toContain('teacher')
        ->and($aff['branches'][0]['active'])->toBeTrue()
        ->and($row['platform_roles'])->toContain('support_agent')
        ->and($row['other_roles'])->not->toContain('teacher')
        ->and($row['other_roles'])->not->toContain('principal');
});

it('hides a user\'s affiliations at other schools from a scoped principal', function () {
    $branchA = makeBranch('AA-0001');
    $branchB = makeBranch('BB-0001');

    // A user who teaches at School A (visible to A's principal) but is also a
    // teacher + platform support agent + loose parent tied to School B.
    $user = memberOf($branchA);
    grantPlatformRole($user, Role::SupportAgent);
    Membership::create([
        'user_id' => $user->id,
        'school_id' => $branchB->school_id,
        'branch_id' => $branchB->id,
        'role' => Role::Teacher->value,
        'scope' => Role::Teacher->scope()->value,
        'is_active' => true,
    ]);

    Sanctum::actingAs(schoolPrincipal($branchA));

    $row = collect(
        $this->withHeaders(schoolContext($branchA))->getJson('/api/v1/users')->assertOk()->json('data')
    )->firstWhere('id', $user->id);

    // Only School A is visible; School B affiliation and global roles are hidden.
    expect($row['affiliations'])->toHaveCount(1)
        ->and($row['affiliations'][0]['school_id'])->toBe($branchA->school_id)
        ->and(collect($row['schools'])->pluck('id'))->not->toContain($branchB->school_id)
        ->and(collect($row['branches'])->pluck('id'))->not->toContain($branchB->id)
        ->and($row['platform_roles'])->toBeEmpty()
        ->and($row['other_roles'])->toBeEmpty();
});

// ── Bulk row actions ────────────────────────────────────────────────────────
// Every bulk endpoint authorizes PER USER and reports what it could not do
// (App\Http\Controllers\Concerns\HandlesBulkActions) — one unreachable row must
// never kill the batch, and must never be silently treated as done.

it('reports rows a bulk status change could not touch instead of failing', function () {
    $target = User::factory()->create();
    $admin = platformAdmin();

    Sanctum::actingAs($admin);

    // The actor's own row (a super admin bypasses every policy, so bulk guards
    // self explicitly) and a missing id ride along.
    $response = $this->postJson('/api/v1/users/bulk/status', [
        'ids' => [$target->id, $admin->id, 999999],
        'status' => 'inactive',
    ])->assertOk();

    expect($response->json('meta.updated'))->toBe(1)
        ->and($response->json('meta.requested'))->toBe(3)
        ->and(collect($response->json('meta.skipped'))->pluck('reason')->all())
        ->toEqualCanonicalizing(['not_found', 'self'])
        ->and($target->fresh()->status)->toBe(AccountStatus::Inactive)
        ->and($admin->fresh()->status)->toBe(AccountStatus::Active);
});

it('bulk-sends password reset links and skips accounts with no phone', function () {
    $withPhone = User::factory()->create(['phone' => '0911445566']);
    $phoneless = User::factory()->create(['phone' => null]);

    Sanctum::actingAs(platformAdmin());

    $response = $this->postJson('/api/v1/users/bulk/reset-password', [
        'ids' => [$withPhone->id, $phoneless->id],
    ])->assertOk();

    expect($response->json('meta.sent'))->toBe(1)
        ->and($response->json('meta.skipped.0.reason'))->toBe('no_phone');

    $this->sms->shouldHaveReceived('send')->once();
});

it('forbids a principal from bulk-resetting passwords', function () {
    $branch = makeBranch();
    $teacher = memberOf($branch);

    Sanctum::actingAs(schoolPrincipal($branch));

    $response = $this->withHeaders(schoolContext($branch))
        ->postJson('/api/v1/users/bulk/reset-password', ['ids' => [$teacher->id]])
        ->assertOk();

    expect($response->json('meta.sent'))->toBe(0)
        ->and($response->json('meta.skipped.0.reason'))->toBe('not_permitted');

    $this->sms->shouldNotHaveReceived('send');
});

it('bulk-deletes users but never the actor or an already-deleted row', function () {
    $one = User::factory()->create();
    $two = User::factory()->create();
    $two->delete();
    $admin = platformAdmin();

    Sanctum::actingAs($admin);

    $response = $this->postJson('/api/v1/users/bulk/delete', [
        'ids' => [$one->id, $two->id, $admin->id],
    ])->assertOk();

    expect($response->json('meta.deleted'))->toBe(1)
        ->and(collect($response->json('meta.skipped'))->pluck('reason')->all())
        ->toEqualCanonicalizing(['already_deleted', 'self'])
        ->and(User::find($one->id))->toBeNull()
        ->and(User::withTrashed()->find($admin->id)->deleted_at)->toBeNull();
});

it('forbids a principal from bulk-deleting users', function () {
    $branch = makeBranch();
    $teacher = memberOf($branch);

    Sanctum::actingAs(schoolPrincipal($branch));

    $response = $this->withHeaders(schoolContext($branch))
        ->postJson('/api/v1/users/bulk/delete', ['ids' => [$teacher->id]])
        ->assertOk();

    expect($response->json('meta.deleted'))->toBe(0)
        ->and(User::find($teacher->id))->not->toBeNull();
});

it('lets a principal bulk-assign users to a branch with several roles', function () {
    $branch = makeBranch();
    $one = User::factory()->create();
    $two = User::factory()->create();

    Sanctum::actingAs(schoolPrincipal($branch));

    $response = $this->withHeaders(schoolContext($branch))->postJson('/api/v1/users/bulk/memberships', [
        'ids' => [$one->id, $two->id],
        'branch_id' => $branch->id,
        'roles' => ['teacher', 'registrar'],
    ])->assertOk();

    expect($response->json('meta.assigned'))->toBe(2)
        ->and($response->json('meta.skipped'))->toBeEmpty()
        ->and(Membership::where('user_id', $one->id)->where('branch_id', $branch->id)->count())->toBe(2)
        ->and(hasMembershipRole($two->fresh(), Role::Registrar))->toBeTrue();
});

it('skips a user who outranks the actor in a bulk branch assignment', function () {
    $branch = makeBranch();
    $peer = schoolPrincipal($branch);   // same rank as the actor in this school
    $plain = User::factory()->create();

    Sanctum::actingAs(schoolPrincipal($branch));

    $response = $this->withHeaders(schoolContext($branch))->postJson('/api/v1/users/bulk/memberships', [
        'ids' => [$plain->id, $peer->id],
        'branch_id' => $branch->id,
        'roles' => ['teacher'],
    ])->assertOk();

    expect($response->json('meta.assigned'))->toBe(1)
        ->and($response->json('meta.skipped.0.id'))->toBe($peer->id)
        ->and($response->json('meta.skipped.0.reason'))->toBe('user_outranks_you')
        ->and(Membership::where('user_id', $peer->id)->where('branch_id', $branch->id)->exists())->toBeFalse();
});

it('forbids a director from bulk-assigning users to a branch', function () {
    $branch = makeBranch();
    $newUser = User::factory()->create();

    Sanctum::actingAs(directorOf($branch));

    $this->withHeaders(branchContext($branch))->postJson('/api/v1/users/bulk/memberships', [
        'ids' => [$newUser->id],
        'branch_id' => $branch->id,
        'roles' => ['teacher'],
    ])->assertStatus(403);

    expect(Membership::where('user_id', $newUser->id)->where('branch_id', $branch->id)->exists())->toBeFalse();
});

it('lets a director bulk-toggle branch access without touching global status', function () {
    $branch = makeBranch();
    $teacher = memberOf($branch);
    $outsider = User::factory()->create();   // no membership in this branch

    Sanctum::actingAs(directorOf($branch));

    $response = $this->withHeaders(branchContext($branch))->postJson('/api/v1/users/bulk/branch-access', [
        'ids' => [$teacher->id, $outsider->id],
        'branch_id' => $branch->id,
        'is_active' => false,
    ])->assertOk();

    expect($response->json('meta.updated'))->toBe(1)
        ->and($response->json('meta.skipped.0.reason'))->toBe('no_membership')
        ->and(Membership::where('user_id', $teacher->id)->where('branch_id', $branch->id)->first()->is_active)->toBeFalse()
        ->and($teacher->fresh()->status)->toBe(AccountStatus::Active);

    // And back on — deactivation is never a one-way trip.
    $this->withHeaders(branchContext($branch))->postJson('/api/v1/users/bulk/branch-access', [
        'ids' => [$teacher->id],
        'branch_id' => $branch->id,
        'is_active' => true,
    ])->assertOk();

    expect(Membership::where('user_id', $teacher->id)->where('branch_id', $branch->id)->first()->is_active)->toBeTrue();
});

it('never lets a bulk branch-access sweep reach another school\'s branch', function () {
    $branchA = makeBranch('AA-0001');
    $branchB = makeBranch('BB-0001');
    $teacherB = memberOf($branchB);

    Sanctum::actingAs(schoolPrincipal($branchA));

    $response = $this->withHeaders(schoolContext($branchA))->postJson('/api/v1/users/bulk/branch-access', [
        'ids' => [$teacherB->id],
        'branch_id' => $branchB->id,
        'is_active' => false,
    ])->assertOk();

    expect($response->json('meta.updated'))->toBe(0)
        ->and($response->json('meta.skipped.0.reason'))->toBe('not_permitted')
        ->and(Membership::where('user_id', $teacherB->id)->where('branch_id', $branchB->id)->first()->is_active)->toBeTrue();
});

it('lets a platform admin restore a deleted user with their access intact', function () {
    $branch = makeBranch();
    $teacher = memberOf($branch);
    $teacher->delete();

    Sanctum::actingAs(platformAdmin());

    $this->postJson("/api/v1/users/{$teacher->id}/restore")->assertOk();

    // Deleting never cascaded to memberships, so the account comes back whole.
    expect(User::find($teacher->id))->not->toBeNull()
        ->and(hasMembershipRole($teacher->fresh(), Role::Teacher))->toBeTrue();
});

it('refuses to restore an account that is not deleted', function () {
    $user = User::factory()->create();

    Sanctum::actingAs(platformAdmin());

    $this->postJson("/api/v1/users/{$user->id}/restore")->assertStatus(422);
});

it('forbids a principal from restoring a deleted user', function () {
    $branch = makeBranch();
    $teacher = memberOf($branch);
    $teacher->delete();

    Sanctum::actingAs(schoolPrincipal($branch));

    $this->withHeaders(schoolContext($branch))
        ->postJson("/api/v1/users/{$teacher->id}/restore")
        ->assertStatus(403);

    expect(User::find($teacher->id))->toBeNull();
});

it('bulk-restores deleted users and reports rows that were never deleted', function () {
    $deleted = User::factory()->create();
    $deleted->delete();
    $live = User::factory()->create();

    Sanctum::actingAs(platformAdmin());

    $response = $this->postJson('/api/v1/users/bulk/restore', [
        'ids' => [$deleted->id, $live->id, 999999],
    ])->assertOk();

    expect($response->json('meta.restored'))->toBe(1)
        ->and(collect($response->json('meta.skipped'))->pluck('reason')->all())
        ->toEqualCanonicalizing(['not_deleted', 'not_found'])
        ->and(User::find($deleted->id))->not->toBeNull();
});

it('undoes an accidental bulk delete', function () {
    $one = User::factory()->create();
    $two = User::factory()->create();

    Sanctum::actingAs(platformAdmin());

    $this->postJson('/api/v1/users/bulk/delete', ['ids' => [$one->id, $two->id]])
        ->assertOk()
        ->assertJsonPath('meta.deleted', 2);

    $this->postJson('/api/v1/users/bulk/restore', ['ids' => [$one->id, $two->id]])
        ->assertOk()
        ->assertJsonPath('meta.restored', 2);

    expect(User::whereIn('id', [$one->id, $two->id])->count())->toBe(2);
});

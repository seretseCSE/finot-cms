<?php

use App\Models\GradeLevel;
use App\Models\Section;
use App\Models\Student;
use Database\Seeders\GradeLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

/*
|--------------------------------------------------------------------------
| School-wide workspace writes
|--------------------------------------------------------------------------
| A school manager (principal / school_admin) acts on ANY branch of their
| school straight from the school-wide context by naming the target branch in
| the payload (`branch_id`) — no context switch. The id is only a target
| selector: authority still comes from hasPermissionForScope() against the
| named branch, so it can never widen access (asserted by the forgery cases).
*/

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(GradeLevelSeeder::class);
});

it('lets a principal create in any branch from the school-wide context via branch_id', function () {
    $main = makeBranch();
    $west = $main->school->branches()->create(['name' => 'West', 'code' => 'AA-0002']);

    Sanctum::actingAs(schoolPrincipal($main));

    $this->withHeaders(schoolContext($main))->postJson('/api/v1/sections', [
        'grade_level_id' => GradeLevel::where('code', 'G1')->value('id'),
        'name' => 'A',
        'branch_id' => $west->id,
    ])->assertCreated();

    expect(Section::where('branch_id', $west->id)->where('name', 'A')->exists())->toBeTrue();

    $this->withHeaders(schoolContext($main))->postJson('/api/v1/students', [
        'first_name' => 'Hana', 'father_name' => 'Bekele', 'gender' => 'female',
        'branch_id' => $main->id,
        'guardians' => [guardianPayload()],
    ])->assertCreated();

    expect(Student::where('branch_id', $main->id)->where('first_name', 'Hana')->exists())->toBeTrue();
});

it('rejects a school-wide write that names no branch', function () {
    $main = makeBranch();

    Sanctum::actingAs(schoolPrincipal($main));

    $this->withHeaders(schoolContext($main))->postJson('/api/v1/sections', [
        'grade_level_id' => GradeLevel::where('code', 'G1')->value('id'),
        'name' => 'A',
    ])->assertStatus(422);

    expect(Section::query()->exists())->toBeFalse();
});

it('denies a director naming a sibling branch they hold no role in', function () {
    $main = makeBranch();
    $west = $main->school->branches()->create(['name' => 'West', 'code' => 'AA-0002']);

    Sanctum::actingAs(directorOf($main));

    $this->withHeaders(branchContext($main))->postJson('/api/v1/sections', [
        'grade_level_id' => GradeLevel::where('code', 'G1')->value('id'),
        'name' => 'Rogue',
        'branch_id' => $west->id,
    ])->assertForbidden();

    expect(Section::where('branch_id', $west->id)->exists())->toBeFalse();
});

it('nullifies a branch_id pointing at another school', function () {
    $branchA = makeBranch('AA-0001');
    $branchB = makeBranch('BB-0001');

    Sanctum::actingAs(schoolPrincipal($branchA));

    $this->withHeaders(schoolContext($branchA))->postJson('/api/v1/sections', [
        'grade_level_id' => GradeLevel::where('code', 'G1')->value('id'),
        'name' => 'Forged',
        'branch_id' => $branchB->id,
    ])->assertStatus(422);

    expect(Section::where('branch_id', $branchB->id)->exists())->toBeFalse();
});

it('narrows school-wide lists to one branch with the branch_id filter', function () {
    $main = makeBranch();
    $west = $main->school->branches()->create(['name' => 'West', 'code' => 'AA-0002']);

    foreach ([$main, $west] as $branch) {
        Student::create([
            'school_id' => $branch->school_id, 'branch_id' => $branch->id,
            'first_name' => "Of{$branch->code}", 'father_name' => 'Test', 'gender' => 'female',
        ]);
    }

    Sanctum::actingAs(schoolPrincipal($main));

    $all = $this->withHeaders(schoolContext($main))->getJson('/api/v1/students')->assertOk();
    expect($all->json('data'))->toHaveCount(2);

    $filtered = $this->withHeaders(schoolContext($main))
        ->getJson("/api/v1/students?branch_id={$west->id}")
        ->assertOk();
    expect($filtered->json('data'))->toHaveCount(1)
        ->and($filtered->json('data.0.first_name'))->toBe("Of{$west->code}");
});

<?php

use App\Enums\Role;
use App\Models\Bank;
use App\Models\GradeLevel;
use App\Models\HealthCondition;
use App\Models\SchoolDirectoryEntry;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\GradeLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

/** A Temari.et content admin — holds `catalogs.manage` via a platform membership. */
function contentAdmin(): User
{
    $admin = User::factory()->create();
    grantPlatformRole($admin, Role::ContentAdmin);

    return $admin;
}

// ── Access control ──────────────────────────────────────────────────────────

it('denies the catalog studio to school staff', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));

    $this->withHeaders(branchContext($branch))->getJson('/api/v1/catalogs/overview')->assertForbidden();
    $this->withHeaders(branchContext($branch))->getJson('/api/v1/catalogs/banks')->assertForbidden();
    $this->withHeaders(branchContext($branch))->getJson('/api/v1/catalogs/subjects')->assertForbidden();
    $this->withHeaders(branchContext($branch))
        ->postJson('/api/v1/catalogs/health-conditions', ['name' => 'X', 'category' => 'other'])
        ->assertForbidden();
});

it('opens the catalog studio to content admins and super admins', function () {
    Sanctum::actingAs(contentAdmin());
    $this->getJson('/api/v1/catalogs/overview')->assertOk();

    Sanctum::actingAs(platformAdmin());
    $this->getJson('/api/v1/catalogs/overview')
        ->assertOk()
        ->assertJsonStructure(['data' => ['subjects', 'grade_levels', 'banks', 'health_conditions', 'school_directory']]);
});

// ── Banks ───────────────────────────────────────────────────────────────────

it('manages the bank catalog', function () {
    Sanctum::actingAs(contentAdmin());

    $created = $this->postJson('/api/v1/catalogs/banks', [
        'code' => 'testbank', 'name' => 'Test Bank', 'type' => 'bank',
    ])->assertCreated()->json('data');

    $this->putJson("/api/v1/catalogs/banks/{$created['id']}", [
        'code' => 'testbank', 'name' => 'Test Bank Renamed', 'type' => 'wallet', 'is_active' => false,
    ])->assertOk();

    expect(Bank::find($created['id']))->name->toBe('Test Bank Renamed')->is_active->toBeFalse();

    $this->deleteJson("/api/v1/catalogs/banks/{$created['id']}")->assertOk();
    expect(Bank::find($created['id']))->toBeNull();
});

it('refuses to delete a bank that holds collection accounts', function () {
    $branch = makeBranch();
    $bank = Bank::create(['code' => 'cbe-test', 'name' => 'CBE', 'type' => 'bank']);
    $bank->accounts()->create([
        'school_id' => $branch->school_id,
        'account_name' => 'Unity Academy', 'account_number' => '1000',
    ]);

    Sanctum::actingAs(contentAdmin());

    $this->deleteJson("/api/v1/catalogs/banks/{$bank->id}")->assertStatus(422);
    expect(Bank::find($bank->id))->not->toBeNull();
});

it('filters the bank list by type and search', function () {
    Bank::create(['code' => 'awash-x', 'name' => 'Awash Bank', 'type' => 'bank']);
    Bank::create(['code' => 'telebirr-x', 'name' => 'Telebirr', 'type' => 'wallet']);

    Sanctum::actingAs(contentAdmin());

    expect($this->getJson('/api/v1/catalogs/banks?type=wallet')->assertOk()->json('data'))
        ->toHaveCount(1)
        ->and($this->getJson('/api/v1/catalogs/banks?search=awash')->json('data'))
        ->toHaveCount(1);
});

// ── Grade levels ────────────────────────────────────────────────────────────

it('manages grade levels and protects rows in use', function () {
    $this->seed(GradeLevelSeeder::class);
    $branch = makeBranch();
    $g1 = GradeLevel::where('code', 'G1')->firstOrFail();
    $branch->sections()->create([
        'school_id' => $branch->school_id, 'grade_level_id' => $g1->id, 'name' => 'A',
    ]);

    Sanctum::actingAs(contentAdmin());

    // The ordered ladder, with usage counts.
    $list = $this->getJson('/api/v1/catalogs/grade-levels')->assertOk()->json('data');
    expect($list)->toHaveCount(15)->and($list[0]['code'])->toBe('KG1');

    // A referenced level cannot be deleted…
    $this->deleteJson("/api/v1/catalogs/grade-levels/{$g1->id}")->assertStatus(422);

    // …but an unreferenced one can be created, edited and removed.
    $id = $this->postJson('/api/v1/catalogs/grade-levels', [
        'code' => 'G13', 'name' => 'Grade 13', 'cycle' => 'preparatory', 'sort_order' => 17,
    ])->assertCreated()->json('data.id');

    $this->putJson("/api/v1/catalogs/grade-levels/{$id}", [
        'code' => 'G13', 'name' => 'Grade 13 (pilot)', 'cycle' => 'preparatory',
        'sort_order' => 17, 'has_national_exam' => true,
    ])->assertOk();

    $this->deleteJson("/api/v1/catalogs/grade-levels/{$id}")->assertOk();
    expect(GradeLevel::where('code', 'G13')->exists())->toBeFalse();
});

it('reorders the whole ladder atomically', function () {
    $this->seed(GradeLevelSeeder::class);
    Sanctum::actingAs(contentAdmin());

    $ordered = GradeLevel::orderBy('sort_order')->pluck('id')->all();

    // Send the ladder with its first two rungs swapped.
    [$ordered[0], $ordered[1]] = [$ordered[1], $ordered[0]];

    $result = $this->putJson('/api/v1/catalogs/grade-levels/reorder', ['ids' => $ordered])
        ->assertOk()
        ->json('data');

    // sort_order is renumbered 1..N in the new order.
    expect(collect($result)->pluck('id')->all())->toBe($ordered)
        ->and($result[0]['sort_order'])->toBe(1)
        ->and($result[1]['sort_order'])->toBe(2);
    expect(GradeLevel::find($ordered[0])->sort_order)->toBe(1);

    // A partial list (not every rung) is rejected.
    $this->putJson('/api/v1/catalogs/grade-levels/reorder', [
        'ids' => array_slice($ordered, 0, 3),
    ])->assertStatus(422);
});

it('forbids reorder for non-catalog managers', function () {
    $this->seed(GradeLevelSeeder::class);
    $ids = GradeLevel::orderBy('sort_order')->pluck('id')->all();
    Sanctum::actingAs(directorOf(makeBranch()));

    $this->putJson('/api/v1/catalogs/grade-levels/reorder', ['ids' => $ids])->assertForbidden();
});

// ── Health conditions ───────────────────────────────────────────────────────

it('manages health conditions and protects referenced ones', function () {
    $branch = makeBranch();
    $condition = HealthCondition::create(['name' => 'Asthma', 'category' => 'chronic']);
    $student = Student::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => 'Sara', 'father_name' => 'Mulu', 'gender' => 'female',
    ]);
    $student->healthConditions()->attach($condition->id);

    Sanctum::actingAs(contentAdmin());

    // Referenced → deactivate, never delete.
    $this->deleteJson("/api/v1/catalogs/health-conditions/{$condition->id}")->assertStatus(422);
    $this->putJson("/api/v1/catalogs/health-conditions/{$condition->id}", [
        'name' => 'Asthma', 'category' => 'chronic', 'is_active' => false,
    ])->assertOk();
    expect($condition->fresh()->is_active)->toBeFalse();

    // Unreferenced rows delete fine.
    $id = $this->postJson('/api/v1/catalogs/health-conditions', [
        'name' => 'Hay fever', 'category' => 'allergy',
    ])->assertCreated()->json('data.id');
    $this->deleteJson("/api/v1/catalogs/health-conditions/{$id}")->assertOk();
});

// ── Subjects ────────────────────────────────────────────────────────────────

it('manages platform subjects but never school-custom rows', function () {
    $this->seed(GradeLevelSeeder::class);
    $branch = makeBranch();
    $custom = Subject::create([
        'code' => 'SCH-101', 'name' => 'School Special', 'school_id' => $branch->school_id,
    ]);

    Sanctum::actingAs(contentAdmin());

    // The studio lists both origins…
    $rows = $this->getJson('/api/v1/catalogs/subjects')->assertOk()->json('data');
    expect(collect($rows)->pluck('code'))->toContain('SCH-101');

    // …creates national-curriculum rows (school_id forced null) with an
    // EXPLICIT — possibly non-contiguous — grade set…
    $gradeIds = GradeLevel::whereIn('sort_order', [12, 14, 15])->pluck('id')->all();
    $created = $this->postJson('/api/v1/catalogs/subjects', [
        'code' => 'CIV-9', 'name' => 'Civics', 'category' => 'social_science',
        'weight' => 3, 'grade_level_ids' => $gradeIds,
    ])->assertCreated()->json('data');
    expect($created['school_id'])->toBeNull()
        ->and($created['grade_sorts'])->toBe([12, 14, 15]);

    // …rejects an unknown grade level…
    $this->postJson('/api/v1/catalogs/subjects', [
        'code' => 'BAD-1', 'name' => 'Bad', 'weight' => 3,
        'grade_level_ids' => [999999],
    ])->assertStatus(422);

    // …and never touches school-custom subjects.
    $this->putJson("/api/v1/catalogs/subjects/{$custom->id}", [
        'code' => 'SCH-101', 'name' => 'Hijacked', 'weight' => 3,
    ])->assertStatus(422);
    $this->deleteJson("/api/v1/catalogs/subjects/{$custom->id}")->assertStatus(422);
});

// ── School directory ────────────────────────────────────────────────────────

it('lists, filters and curates the school directory', function () {
    SchoolDirectoryEntry::create(['name' => 'St. Joseph School', 'region' => 'Addis Ababa', 'is_verified' => true]);
    SchoolDirectoryEntry::create(['name' => 'Hawassa Tabor', 'region' => 'Sidama', 'is_verified' => false]);

    Sanctum::actingAs(contentAdmin());

    expect($this->getJson('/api/v1/catalogs/school-directory?is_verified=false')->assertOk()->json('data'))
        ->toHaveCount(1)
        ->and($this->getJson('/api/v1/catalogs/school-directory?region=Sidama')->json('data'))
        ->toHaveCount(1)
        ->and($this->getJson('/api/v1/catalogs/school-directory/regions')->json('data'))
        ->toBe(['Addis Ababa', 'Sidama']);

    // Platform additions are verified by default.
    $created = $this->postJson('/api/v1/catalogs/school-directory', [
        'name' => 'Bahir Dar Academy', 'region' => 'Amhara', 'city' => 'Bahir Dar',
    ])->assertCreated()->json('data');
    expect($created['is_verified'])->toBeTrue();
});

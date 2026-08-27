<?php

use App\Models\Employee;
use App\Models\Student;
use App\Models\User;
use App\Support\SearchTerm;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

/**
 * Guard rail for the ONE search rule (`App\Support\SearchTerm`): a list search
 * is matched WORD BY WORD, so a full Ethiopian name finds its record even
 * though the name lives in three separate columns.
 *
 * This is the regression these tests exist for: typing "Abdi Fikre Gemeda"
 * used to return NOTHING (no single column holds the whole string) while
 * "Abdi" alone worked — the record was invisible to the person looking at it.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('splits a query into words and neutralises the user\'s own wildcards', function () {
    expect(SearchTerm::words('  Abdi   Fikre Gemeda '))->toBe(['Abdi', 'Fikre', 'Gemeda']);
    expect(SearchTerm::words('   '))->toBe([]);
    expect(SearchTerm::contains('50%'))->toBe('%50\%%');
});

it('finds a student by their full name across the split name columns', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));

    $student = Student::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => 'Abdi', 'father_name' => 'Fikre', 'grandfather_name' => 'Gemeda',
        'gender' => 'male',
    ]);
    Student::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => 'Abdi', 'father_name' => 'Tesfaye', 'grandfather_name' => 'Bekele',
        'gender' => 'male',
    ]);

    $find = fn (string $q) => $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/students?search='.urlencode($q))
        ->assertOk()
        ->json('data');

    // The full name — the case that used to come back empty.
    expect($find('Abdi Fikre Gemeda'))->toHaveCount(1);
    expect($find('Abdi Fikre Gemeda')[0]['id'])->toBe($student->id);

    // Any subset of the words, in any order.
    expect($find('Fikre Abdi'))->toHaveCount(1);
    expect($find('gemeda abdi'))->toHaveCount(1);

    // A single word still matches everyone who shares it.
    expect($find('Abdi'))->toHaveCount(2);

    // A word that belongs to nobody narrows to nothing.
    expect($find('Abdi Kebede'))->toHaveCount(0);
});

it('finds a student by name plus phone or Temari ID in one query', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));

    $student = Student::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => 'Abdi', 'father_name' => 'Fikre', 'grandfather_name' => 'Gemeda',
        'primary_phone' => '0911223344', 'gender' => 'male',
    ]);

    $find = fn (string $q) => $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/students?search='.urlencode($q))
        ->assertOk()
        ->json('data');

    expect($find('Abdi 0911223344'))->toHaveCount(1);
    expect($find(strtolower($student->public_id).' Gemeda'))->toHaveCount(1);
});

it('finds an employee by their full name', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));

    $employee = Employee::create([
        'user_id' => User::factory()->create()->id,
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => 'Abdi', 'father_name' => 'Fikre', 'grandfather_name' => 'Gemeda',
    ]);
    Employee::create([
        'user_id' => User::factory()->create()->id,
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => 'Selam', 'father_name' => 'Tesfaye',
    ]);

    $response = $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/employees?search='.urlencode('Abdi Fikre Gemeda'))
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($employee->id);
});

it('finds a user account by their full name', function () {
    $branch = makeBranch();
    Sanctum::actingAs(platformAdmin());

    $user = User::factory()->create(['name' => 'Abdi Fikre Gemeda']);
    User::factory()->create(['name' => 'Selam Tesfaye Bekele']);

    $response = $this->getJson('/api/v1/users?search='.urlencode('Gemeda Abdi'))->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($user->id);
});

it('still matches a phrase that lives whole inside one column', function () {
    $branch = makeBranch();
    Sanctum::actingAs(platformAdmin());

    // A branch name with a space: the phrase must keep matching intact, and
    // its words must match in any order.
    $branch->update(['name' => 'Bole Main Campus']);

    $find = fn (string $q) => $this->getJson('/api/v1/branches?search='.urlencode($q))
        ->assertOk()
        ->json('data');

    expect($find('Bole Main'))->toHaveCount(1);
    expect($find('Campus Bole'))->toHaveCount(1);
});

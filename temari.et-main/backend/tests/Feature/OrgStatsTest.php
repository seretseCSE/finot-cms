<?php

use App\Actions\SaveAcademicYearAction;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\GradeLevel;
use App\Models\ParentProfile;
use App\Models\School;
use App\Models\SchoolProgram;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentGuardian;
use App\Models\User;
use App\Services\Sms\SmsClient;
use App\Support\GradeOffering;
use Database\Seeders\GradeLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

/**
 * School/branch profile vitals (GET …/stats) and the list-table counts: the
 * numbers must be correct, scoped to the tenant, and visible only to whoever
 * may open the profile.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(GradeLevelSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    app()->instance(SmsClient::class, Mockery::spy(SmsClient::class));
});

/** Seed a branch with sections, enrollments, a guardian and a teaching staff. */
function seedBranchLife(Branch $branch): void
{
    $year = (new SaveAcademicYearAction)->execute($branch, ['name' => '2017 E.C.', 'status' => 'active']);
    $program = SchoolProgram::defaultFor($branch);

    $g1 = GradeLevel::where('code', 'G1')->value('id');
    $g4 = GradeLevel::where('code', 'G4')->value('id');

    $branch->sections()->create(['school_id' => $branch->school_id, 'grade_level_id' => $g1, 'name' => 'A', 'capacity' => 30]);
    $branch->sections()->create(['school_id' => $branch->school_id, 'grade_level_id' => $g4, 'name' => 'A', 'capacity' => 40]);

    $enroll = function (string $gender, int $gradeLevelId, string $status) use ($branch, $year, $program): Student {
        $student = Student::create([
            'school_id' => $branch->school_id, 'branch_id' => $branch->id,
            'first_name' => fake()->firstName(), 'father_name' => 'Test', 'gender' => $gender,
        ]);
        StudentEnrollment::create([
            'student_id' => $student->id, 'school_id' => $branch->school_id, 'branch_id' => $branch->id,
            'academic_year_id' => $year->id, 'school_program_id' => $program->id,
            'grade_level_id' => $gradeLevelId, 'status' => $status, 'enrolled_on' => now(),
        ]);

        return $student;
    };

    $enroll('male', $g1, 'active');
    $enroll('male', $g1, 'active');
    $withParent = $enroll('female', $g4, 'active');
    $enroll('female', $g4, 'pending');

    $parent = ParentProfile::create([
        'user_id' => User::factory()->create()->id,
        'first_name' => 'Tesfaye', 'father_name' => 'Alemu',
    ]);
    StudentGuardian::create([
        'student_id' => $withParent->id, 'parent_id' => $parent->id,
        'relationship' => 'father', 'is_primary' => true, 'is_active' => true,
    ]);

    // One teacher, plus a director who ALSO teaches (counts once per title).
    $teacher = Employee::create([
        'user_id' => User::factory()->create()->id,
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => 'Meles', 'is_active' => true,
    ]);
    $teacher->positions()->create(['job_title' => 'teacher', 'is_primary' => true, 'hired_on' => '2020-09-01']);

    $director = Employee::create([
        'user_id' => User::factory()->create()->id,
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => 'Sara', 'is_active' => true,
    ]);
    $director->positions()->create(['job_title' => 'director', 'is_primary' => true, 'hired_on' => '2020-09-01']);
    $director->positions()->create(['job_title' => 'teacher', 'is_primary' => false, 'hired_on' => '2020-09-01']);
}

it('returns branch profile vitals with the tenant scoped numbers', function () {
    $branch = makeBranch();
    seedBranchLife($branch);

    // A second school's life must never bleed into the numbers.
    seedBranchLife(makeBranch('BB-0001'));

    Sanctum::actingAs(platformAdmin());

    $response = $this->getJson("/api/v1/branches/{$branch->id}/stats")->assertOk();

    expect($response->json('data.students'))->toBe(['active' => 3, 'pending' => 1, 'male' => 2, 'female' => 1]);
    expect($response->json('data.guardians'))->toBe(1);
    expect($response->json('data.employees.total'))->toBe(2);
    expect($response->json('data.employees.by_job_title'))
        ->toContain(['job_title' => 'teacher', 'total' => 2])
        ->toContain(['job_title' => 'director', 'total' => 1]);
    expect($response->json('data.academics.sections'))->toBe(2);
    expect($response->json('data.academics.capacity'))->toBe(70);

    $grades = $response->json('data.grades');
    expect($grades)->toHaveCount(2);
    expect($grades[0])->toBe(['id' => GradeLevel::where('code', 'G1')->value('id'), 'code' => 'G1', 'name' => 'Grade 1', 'students' => 2, 'sections' => 1]);
    expect($grades[1]['students'])->toBe(1);
});

it('returns school stats with a per-branch rollup', function () {
    $branch = makeBranch();
    seedBranchLife($branch);
    // The grade span reflects the configured OFFERING, not existing sections.
    GradeOffering::sync($branch, [[
        'type' => 'regular',
        'grade_level_ids' => GradeLevel::whereIn('code', ['G1', 'G2', 'G3', 'G4'])->pluck('id')->all(),
    ]]);
    $second = Branch::create(['school_id' => $branch->school_id, 'name' => 'Second', 'code' => 'AA-0002']);

    Sanctum::actingAs(platformAdmin());

    $response = $this->getJson("/api/v1/schools/{$branch->school_id}/stats")->assertOk();

    expect($response->json('data.students.active'))->toBe(3);
    expect($response->json('data.branches'))->toHaveCount(2);

    $main = collect($response->json('data.branches'))->firstWhere('id', $branch->id);
    expect($main['students'])->toBe(3);
    expect($main['teachers'])->toBe(2);
    expect($main['grade_min'])->toBe('Grade 1');
    expect($main['grade_max'])->toBe('Grade 4');

    $empty = collect($response->json('data.branches'))->firstWhere('id', $second->id);
    expect($empty['students'])->toBe(0);
});

it('exposes list vitals on the school and branch management tables', function () {
    $branch = makeBranch();
    seedBranchLife($branch);
    // The grade span reflects the configured OFFERING, not existing sections.
    GradeOffering::sync($branch, [[
        'type' => 'regular',
        'grade_level_ids' => GradeLevel::whereIn('code', ['G1', 'G2', 'G3', 'G4'])->pluck('id')->all(),
    ]]);

    Sanctum::actingAs(platformAdmin());

    $school = $this->getJson('/api/v1/schools')->assertOk()->json('data.0');
    expect($school['students_count'])->toBe(3);
    expect($school['teachers_count'])->toBe(2);
    expect($school['grade_min'])->toBe('Grade 1');
    expect($school['grade_max'])->toBe('Grade 4');

    $row = $this->getJson('/api/v1/branches')->assertOk()->json('data.0');
    expect($row['students_count'])->toBe(3);
    expect($row['teachers_count'])->toBe(2);
    expect($row['sections_count'])->toBe(2);
});

it('lets a principal read their own school stats but never another school', function () {
    $branch = makeBranch();
    $other = makeBranch('BB-0001');

    $principal = schoolPrincipal($branch);
    Sanctum::actingAs($principal);

    $this->getJson("/api/v1/schools/{$branch->school_id}/stats")->assertOk();
    $this->getJson("/api/v1/schools/{$other->school_id}/stats")->assertForbidden();
    $this->getJson("/api/v1/branches/{$other->id}/stats")->assertForbidden();
});

it('forbids a director from school stats but allows their own branch', function () {
    $branch = makeBranch();

    Sanctum::actingAs(directorOf($branch));

    $this->getJson("/api/v1/schools/{$branch->school_id}/stats")->assertForbidden();
    $this->getJson("/api/v1/branches/{$branch->id}/stats")->assertOk();
});

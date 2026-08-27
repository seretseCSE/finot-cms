<?php

use App\Actions\SaveAcademicYearAction;
use App\Enums\TimetableVersionStatus;
use App\Models\Employee;
use App\Models\GradeLevel;
use App\Models\SchoolProgram;
use App\Models\SectionHomeroom;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectAssignment;
use App\Models\TimetableVersion;
use App\Models\User;
use Database\Seeders\GradeLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SubjectSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(GradeLevelSeeder::class);
    $this->seed(SubjectSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

/** A branch with a year, two G1 sections and a teacher assigned MATH in both. */
function teachingFixture(): array
{
    $branch = makeBranch();
    $gradeId = GradeLevel::where('code', 'G1')->value('id');
    $sectionA = $branch->sections()->create(['school_id' => $branch->school_id, 'grade_level_id' => $gradeId, 'name' => 'A']);
    $sectionB = $branch->sections()->create(['school_id' => $branch->school_id, 'grade_level_id' => $gradeId, 'name' => 'B']);

    $teacher = Employee::create([
        'user_id' => User::factory()->create()->id,
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => 'Almaz', 'is_active' => true,
    ]);
    $teacher->positions()->create(['job_title' => 'teacher', 'is_primary' => true]);

    Sanctum::actingAs(directorOf($branch));
    $year = (new SaveAcademicYearAction)->execute($branch, ['name' => '2018 E.C.']);
    $term = $year->terms->first();

    $math = Subject::where('code', 'MATH')->value('id');
    $assignments = collect([$sectionA, $sectionB])->map(fn ($section) => SubjectAssignment::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'academic_year_id' => $year->id, 'term_id' => $term->id,
        'section_id' => $section->id, 'subject_id' => $math,
        'employee_id' => $teacher->id, 'periods_per_week' => 3, 'is_active' => true,
    ]));

    return [$branch, $teacher, $term, $sectionA, $assignments];
}

it('returns the teacher workload: assignments, head counts and homeroom', function () {
    [$branch, $teacher, $term, $sectionA] = teachingFixture();

    SectionHomeroom::create([
        'section_id' => $sectionA->id,
        'academic_year_id' => $term->academic_year_id,
        'employee_id' => $teacher->id,
    ]);

    // Two active students in section A, one inactive — only active ones count.
    foreach ([['Sara', 'active'], ['Liya', 'active'], ['Meron', 'withdrawn']] as [$name, $status]) {
        $student = Student::create([
            'school_id' => $branch->school_id, 'branch_id' => $branch->id,
            'first_name' => $name, 'father_name' => 'Tesfaye', 'gender' => 'female',
        ]);
        $student->enrollments()->create([
            'school_id' => $branch->school_id, 'branch_id' => $branch->id,
            'academic_year_id' => $term->academic_year_id,
            'school_program_id' => SchoolProgram::defaultFor($branch)->id,
            'section_id' => $sectionA->id, 'grade_level_id' => $sectionA->grade_level_id,
            'status' => $status,
        ]);
    }

    $response = $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/employees/{$teacher->id}/teaching")
        ->assertOk();

    expect($response->json('data.term_id'))->toBe($term->id)
        ->and($response->json('data.assignments'))->toHaveCount(2)
        ->and($response->json('data.assignments.0.subject_code'))->toBe('MATH')
        ->and($response->json('data.assignments.0.periods_per_week'))->toBe(3)
        ->and(collect($response->json('data.assignments'))->firstWhere('section_name', 'A')['students'])->toBe(2)
        ->and($response->json('data.homeroom_sections.0.section_name'))->toBe('A')
        ->and($response->json('data.week'))->toBeNull()
        ->and($response->json('meta.terms'))->not->toBeEmpty();
});

it('includes the personal week once the term timetable is published', function () {
    [$branch, $teacher, $term, , $assignments] = teachingFixture();

    $version = TimetableVersion::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'term_id' => $term->id, 'name' => 'v1',
        'status' => TimetableVersionStatus::Published->value,
        'days' => [1, 2, 3, 4, 5], 'published_at' => now(),
    ]);
    $version->slots()->create([
        'subject_assignment_id' => $assignments->first()->id,
        'day_of_week' => 1, 'period_number' => 2,
    ]);

    $response = $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/employees/{$teacher->id}/teaching")
        ->assertOk();

    expect($response->json('data.week.days'))->toBe([1, 2, 3, 4, 5])
        ->and($response->json('data.week.slots'))->toHaveCount(1)
        ->and($response->json('data.week.slots.0.subject_code'))->toBe('MATH')
        ->and($response->json('data.week.slots.0.section_name'))->toBe('A');
});

it('serves a requested semester and 404s a term of another branch', function () {
    [$branch, $teacher, $term] = teachingFixture();

    $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/employees/{$teacher->id}/teaching?term_id={$term->id}")
        ->assertOk();

    $foreign = makeBranch('AA-0002');
    Sanctum::actingAs(directorOf($foreign));
    $foreignYear = (new SaveAcademicYearAction)->execute($foreign, ['name' => '2018 E.C.']);

    Sanctum::actingAs(directorOf($branch));
    $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/employees/{$teacher->id}/teaching?term_id={$foreignYear->terms->first()->id}")
        ->assertNotFound();
});

it('denies a director of another school', function () {
    [, $teacher] = teachingFixture();

    $other = makeBranch('AA-0003');
    Sanctum::actingAs(directorOf($other));

    $this->withHeaders(branchContext($other))
        ->getJson("/api/v1/employees/{$teacher->id}/teaching")
        ->assertForbidden();
});

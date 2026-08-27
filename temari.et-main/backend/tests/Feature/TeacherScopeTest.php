<?php

use App\Actions\EnrollStudentAction;
use App\Actions\SaveAcademicYearAction;
use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\GradeLevel;
use App\Models\Membership;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentTermResult;
use App\Models\Subject;
use App\Models\SubjectAssignment;
use App\Models\User;
use Database\Seeders\GradeLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SubjectSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

/*
 * The teacher OWNERSHIP READ lane. Teachers hold no branch-wide reads: their
 * visibility is exactly their own sections (homeroom or active teaching
 * assignment), their own marklists and — for homeroom teachers — their own
 * section's report-card rows. Everything else (student/parent registers,
 * academic-year management, fees, whole-branch grading) is office territory.
 */

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(GradeLevelSeeder::class);
    $this->seed(SubjectSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function scopeYear(Branch $branch, string $name = '2017 E.C.', string $status = 'active'): AcademicYear
{
    return (new SaveAcademicYearAction)->execute($branch, ['name' => $name, 'status' => $status]);
}

function scopeSection(Branch $branch, string $name = 'A', string $gradeCode = 'G1'): Section
{
    return $branch->sections()->create([
        'school_id' => $branch->school_id,
        'grade_level_id' => GradeLevel::where('code', $gradeCode)->value('id'),
        'name' => $name,
    ]);
}

/** A teacher user with a membership + employee profile in the branch. */
function scopeTeacher(Branch $branch): array
{
    $user = memberOf($branch);
    $employee = Employee::create([
        'user_id' => $user->id,
        'school_id' => $branch->school_id,
        'branch_id' => $branch->id,
        'first_name' => 'Alemu',
        'father_name' => 'Bekele',
        'gender' => 'male',
    ]);

    return [$user, $employee];
}

function scopeAssignment(Branch $branch, AcademicYear $year, Section $section, ?Employee $teacher): SubjectAssignment
{
    return SubjectAssignment::create([
        'school_id' => $branch->school_id,
        'branch_id' => $branch->id,
        'academic_year_id' => $year->id,
        'section_id' => $section->id,
        'subject_id' => Subject::where('code', 'MATH')->value('id'),
        'term_id' => $year->terms()->first()->id,
        'employee_id' => $teacher?->id,
        'periods_per_week' => 5,
    ]);
}

function scopeStudent(Branch $branch, AcademicYear $year, Section $section, string $first = 'Abel'): Student
{
    $student = $branch->students()->create([
        'school_id' => $branch->school_id,
        'first_name' => $first,
        'father_name' => 'Tesfaye',
        'gender' => 'male',
    ]);

    app(EnrollStudentAction::class)->execute($student, [
        'academic_year_id' => $year->id,
        'section_id' => $section->id,
        'grade_level_id' => $section->grade_level_id,
    ]);

    return $student;
}

// ───────────────────── sections: the ownership lane ─────────────────────

it('lists only the teacher\'s own sections, never the whole branch', function () {
    $branch = makeBranch();
    $year = scopeYear($branch);
    $mine = scopeSection($branch, 'A');
    $other = scopeSection($branch, 'B');
    [$teacher, $employee] = scopeTeacher($branch);
    scopeAssignment($branch, $year, $mine, $employee);

    Sanctum::actingAs($teacher);

    $ids = collect($this->getJson('/api/v1/sections', branchContext($branch))
        ->assertOk()
        ->json('data'))->pluck('id');

    expect($ids->all())->toBe([$mine->id]);
    expect($ids)->not->toContain($other->id);
});

it('opens the teacher\'s own section profile but forbids other sections', function () {
    $branch = makeBranch();
    $year = scopeYear($branch);
    $mine = scopeSection($branch, 'A');
    $other = scopeSection($branch, 'B');
    [$teacher, $employee] = scopeTeacher($branch);
    $mine->setHomeroom($year->id, $employee->id);

    Sanctum::actingAs($teacher);

    $this->getJson("/api/v1/sections/{$mine->id}", branchContext($branch))->assertOk();
    $this->getJson("/api/v1/sections/{$other->id}", branchContext($branch))->assertForbidden();
});

// ───────────────────── registers stay office territory ─────────────────────

it('forbids teachers from the student, parent and academic-year registers', function () {
    $branch = makeBranch();
    scopeYear($branch);
    [$teacher] = scopeTeacher($branch);

    Sanctum::actingAs($teacher);

    $this->getJson('/api/v1/students', branchContext($branch))->assertForbidden();
    $this->getJson('/api/v1/parents', branchContext($branch))->assertForbidden();
    $this->getJson('/api/v1/academic-years', branchContext($branch))->assertForbidden();
    $this->getJson('/api/v1/fee-structures', branchContext($branch))->assertForbidden();
});

it('still serves semester metadata to teachers for their pickers', function () {
    $branch = makeBranch();
    scopeYear($branch);
    [$teacher] = scopeTeacher($branch);

    Sanctum::actingAs($teacher);

    $this->getJson('/api/v1/terms', branchContext($branch))
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

// ───────────────────── attendance: homeroom only ─────────────────────

it('serves the attendance register only for the teacher\'s homeroom sections', function () {
    $branch = makeBranch();
    $year = scopeYear($branch);
    $homeroom = scopeSection($branch, 'A');
    $taught = scopeSection($branch, 'B');
    $other = scopeSection($branch, 'C');
    [$teacher, $employee] = scopeTeacher($branch);
    $homeroom->setHomeroom($year->id, $employee->id);
    // Teaching a subject in a section does NOT open its class register.
    scopeAssignment($branch, $year, $taught, $employee);
    scopeStudent($branch, $year, $homeroom);

    Sanctum::actingAs($teacher);

    $this->getJson("/api/v1/sections/{$homeroom->id}/attendance?date=2026-06-20", branchContext($branch))
        ->assertOk();
    $this->getJson("/api/v1/sections/{$taught->id}/attendance?date=2026-06-20", branchContext($branch))
        ->assertForbidden();
    $this->getJson("/api/v1/sections/{$other->id}/attendance?date=2026-06-20", branchContext($branch))
        ->assertForbidden();
});

it('records attendance only in the teacher\'s homeroom section', function () {
    $branch = makeBranch();
    $year = scopeYear($branch);
    $homeroom = scopeSection($branch, 'A');
    $taught = scopeSection($branch, 'B');
    [$teacher, $employee] = scopeTeacher($branch);
    $homeroom->setHomeroom($year->id, $employee->id);
    scopeAssignment($branch, $year, $taught, $employee);
    $student = scopeStudent($branch, $year, $homeroom);
    $taughtStudent = scopeStudent($branch, $year, $taught, 'Binyam');

    Sanctum::actingAs($teacher);

    $payload = fn (Student $s) => [
        'date' => '2026-06-20',
        'records' => [['student_id' => $s->id, 'status' => 'present']],
    ];

    $this->postJson("/api/v1/sections/{$homeroom->id}/attendance", $payload($student), branchContext($branch))
        ->assertOk();
    $this->postJson("/api/v1/sections/{$taught->id}/attendance", $payload($taughtStudent), branchContext($branch))
        ->assertForbidden();
});

it('narrows the section picker to homerooms with homeroom_only', function () {
    $branch = makeBranch();
    $year = scopeYear($branch);
    $homeroom = scopeSection($branch, 'A');
    $taught = scopeSection($branch, 'B');
    [$teacher, $employee] = scopeTeacher($branch);
    $homeroom->setHomeroom($year->id, $employee->id);
    scopeAssignment($branch, $year, $taught, $employee);

    Sanctum::actingAs($teacher);

    $ids = collect($this->getJson('/api/v1/sections?homeroom_only=1', branchContext($branch))
        ->assertOk()
        ->json('data'))->pluck('id');

    expect($ids->all())->toBe([$homeroom->id]);
});

it('narrows the section picker to a specific year\'s homerooms with mine_homeroom', function () {
    // The report-card / roster pickers must list ONLY the sections a teacher
    // homerooms for the anchored year — never a class they merely teach, and
    // never a homeroom they held in a different year.
    $branch = makeBranch();
    $thisYear = scopeYear($branch);
    $lastYear = scopeYear($branch, '2016 E.C.', 'completed');
    $homeroom = scopeSection($branch, 'A');
    $taught = scopeSection($branch, 'B');
    $formerHomeroom = scopeSection($branch, 'C');
    [$teacher, $employee] = scopeTeacher($branch);
    $homeroom->setHomeroom($thisYear->id, $employee->id);
    $formerHomeroom->setHomeroom($lastYear->id, $employee->id);
    scopeAssignment($branch, $thisYear, $taught, $employee);

    Sanctum::actingAs($teacher);

    $ids = collect($this->getJson(
        "/api/v1/sections?mine_homeroom=1&academic_year_id={$thisYear->id}",
        branchContext($branch),
    )->assertOk()->json('data'))->pluck('id');

    expect($ids->all())->toBe([$homeroom->id]);

    // Anchor to last year → the picker follows the homeroom the teacher held then.
    $lastYearIds = collect($this->getJson(
        "/api/v1/sections?mine_homeroom=1&academic_year_id={$lastYear->id}",
        branchContext($branch),
    )->assertOk()->json('data'))->pluck('id');

    expect($lastYearIds->all())->toBe([$formerHomeroom->id]);
});

// ───────────────────── report cards: homeroom window ─────────────────────

it('scopes term results to the teacher\'s homeroom section and hides the rest', function () {
    $branch = makeBranch();
    $year = scopeYear($branch);
    $term = $year->terms()->first();
    $mine = scopeSection($branch, 'A');
    $other = scopeSection($branch, 'B');
    [$teacher, $employee] = scopeTeacher($branch);
    $mine->setHomeroom($year->id, $employee->id);

    $mineStudent = scopeStudent($branch, $year, $mine, 'Abel');
    $otherStudent = scopeStudent($branch, $year, $other, 'Bini');

    foreach ([[$mine, $mineStudent], [$other, $otherStudent]] as [$section, $student]) {
        StudentTermResult::create([
            'school_id' => $branch->school_id, 'branch_id' => $branch->id,
            'academic_year_id' => $year->id,
            'term_id' => $term->id, 'section_id' => $section->id,
            'grade_level_id' => $section->grade_level_id,
            'student_id' => $student->id,
            'student_enrollment_id' => $student->enrollments()->first()->id,
            'average' => 80,
            'computed_at' => now(),
        ]);
    }

    Sanctum::actingAs($teacher);

    $rows = $this->getJson("/api/v1/terms/{$term->id}/results", branchContext($branch))
        ->assertOk()
        ->json('data');

    expect(collect($rows)->pluck('section_id')->unique()->all())->toBe([$mine->id]);

    // A subject teacher with no homeroom gets an empty list, never a 403 wall.
    [$subjectTeacher, $subjectEmployee] = scopeTeacher($branch);
    scopeAssignment($branch, $year, $other, $subjectEmployee);
    Sanctum::actingAs($subjectTeacher);

    $this->getJson("/api/v1/terms/{$term->id}/results", branchContext($branch))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('serves every homeroom of a teacher who holds several, across grades', function () {
    // One teacher may homeroom several sections — same grade or different
    // grades. All of them must come back, so the report-card pickers can list
    // each grade/section the teacher actually assembles.
    $branch = makeBranch();
    $year = scopeYear($branch);
    $term = $year->terms()->first();
    $g1a = scopeSection($branch, 'A', 'G1');
    $g1b = scopeSection($branch, 'B', 'G1');
    $g2a = scopeSection($branch, 'A', 'G2');
    $other = scopeSection($branch, 'Z', 'G3');
    [$teacher, $employee] = scopeTeacher($branch);

    foreach ([$g1a, $g1b, $g2a] as $section) {
        $section->setHomeroom($year->id, $employee->id);
    }

    foreach ([$g1a, $g1b, $g2a, $other] as $i => $section) {
        $student = scopeStudent($branch, $year, $section, "Kid{$i}");
        StudentTermResult::create([
            'school_id' => $branch->school_id, 'branch_id' => $branch->id,
            'academic_year_id' => $year->id,
            'term_id' => $term->id, 'section_id' => $section->id,
            'grade_level_id' => $section->grade_level_id,
            'student_id' => $student->id,
            'student_enrollment_id' => $student->enrollments()->first()->id,
            'average' => 80,
            'computed_at' => now(),
        ]);
    }

    Sanctum::actingAs($teacher);

    $rows = $this->getJson("/api/v1/terms/{$term->id}/results", branchContext($branch))
        ->assertOk()
        ->json('data');

    expect(collect($rows)->pluck('section_id')->unique()->sort()->values()->all())
        ->toBe(collect([$g1a->id, $g1b->id, $g2a->id])->sort()->values()->all());

    // Two distinct grades reach the picker, and every row carries the grade id
    // the frontend groups by.
    expect(collect($rows)->pluck('grade_level_id')->unique()->count())->toBe(2)
        ->and(collect($rows)->whereNull('grade_level_id')->count())->toBe(0);
});

// ───────────────────── cross-tenant row scoping ─────────────────────

it('blocks reading another school\'s assessments and teaching grid by id', function () {
    $branchA = makeBranch('AA-0001');
    $branchB = makeBranch('AA-0002');
    $yearB = scopeYear($branchB);
    $sectionB = scopeSection($branchB);
    $assignmentB = scopeAssignment($branchB, $yearB, $sectionB, null);

    // A director of school A holds grades.view / timetable.view in THEIR
    // context — that must not open school B's rows by id.
    Sanctum::actingAs(directorOf($branchA));

    $this->getJson("/api/v1/subject-assignments/{$assignmentB->id}/assessments", branchContext($branchA))
        ->assertForbidden();
    $this->getJson("/api/v1/sections/{$sectionB->id}/subject-assignments", branchContext($branchA))
        ->assertForbidden();
    $this->getJson(
        '/api/v1/reports/subject-assignments/'.$assignmentB->id.'/continuous assessment',
        branchContext($branchA),
    )->assertForbidden();
});

it('lets a teacher read assessments only for assignments that are theirs', function () {
    $branch = makeBranch();
    $year = scopeYear($branch);
    $section = scopeSection($branch);
    [$teacher, $employee] = scopeTeacher($branch);
    $ownAssignment = scopeAssignment($branch, $year, $section, $employee);

    $otherTeacher = gradeless_employee($branch);
    $otherAssignment = SubjectAssignment::create([
        'school_id' => $branch->school_id,
        'branch_id' => $branch->id,
        'academic_year_id' => $year->id,
        'section_id' => $section->id,
        'subject_id' => Subject::where('code', 'ENG')->value('id'),
        'term_id' => $year->terms()->first()->id,
        'employee_id' => $otherTeacher->id,
        'periods_per_week' => 5,
    ]);

    Sanctum::actingAs($teacher);

    $this->getJson("/api/v1/subject-assignments/{$ownAssignment->id}/assessments", branchContext($branch))
        ->assertOk();
    $this->getJson("/api/v1/subject-assignments/{$otherAssignment->id}/assessments", branchContext($branch))
        ->assertForbidden();
});

// ───────────────────── sensitive fields on shared resources ─────────────────────

it('hides pay data from employees.view holders without payroll authority', function () {
    $branch = makeBranch();
    [, $employee] = scopeTeacher($branch);
    $employee->positions()->create([
        'job_title' => 'teacher',
        'employment_type' => 'full_time',
        'salary' => 15000,
        'hired_on' => '2026-01-01',
        'is_primary' => true,
    ]);

    // Registrars browse the staff register but hold no payroll.view.
    Sanctum::actingAs(memberOf($branch, Role::Registrar));
    $payload = $this->getJson("/api/v1/employees/{$employee->id}", branchContext($branch))
        ->assertOk()
        ->json('data');
    expect($payload['positions'][0]['salary'])->toBeNull();
    expect($payload['primary_position']['salary'])->toBeNull();

    // The director (payroll.view) keeps the full pay picture.
    Sanctum::actingAs(directorOf($branch));
    $payload = $this->getJson("/api/v1/employees/{$employee->id}", branchContext($branch))
        ->assertOk()
        ->json('data');
    expect((float) $payload['positions'][0]['salary'])->toBe(15000.0);
});

it('hides student health data and documents from read-only students.view holders', function () {
    $branch = makeBranch();
    $year = scopeYear($branch);
    $section = scopeSection($branch);
    $student = scopeStudent($branch, $year, $section);

    // Finance officers pick students for invoicing — never their health file.
    Sanctum::actingAs(memberOf($branch, Role::FinanceOfficer));
    $payload = $this->getJson("/api/v1/students/{$student->id}", branchContext($branch))
        ->assertOk()
        ->json('data');
    expect($payload)->not->toHaveKeys(['health_conditions', 'attachments']);

    // Record managers (students.update) keep the full file.
    Sanctum::actingAs(directorOf($branch));
    $payload = $this->getJson("/api/v1/students/{$student->id}", branchContext($branch))
        ->assertOk()
        ->json('data');
    expect($payload)->toHaveKeys(['health_conditions', 'attachments']);
});

it('embeds fee structures on a year only for fees.view holders', function () {
    $branch = makeBranch();
    $year = scopeYear($branch);

    Sanctum::actingAs(directorOf($branch));

    $this->getJson("/api/v1/academic-years/{$year->id}", branchContext($branch))
        ->assertOk()
        ->assertJsonPath('data.fees', []);
});

/** A colleague's employee profile — an assignment the acting teacher does not own. */
function gradeless_employee(Branch $branch): Employee
{
    return Employee::create([
        'user_id' => User::factory()->create()->id,
        'school_id' => $branch->school_id,
        'branch_id' => $branch->id,
        'first_name' => 'Chaltu',
        'father_name' => 'Dinsa',
        'gender' => 'female',
    ]);
}

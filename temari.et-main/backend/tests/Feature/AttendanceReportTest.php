<?php

use App\Actions\SaveAcademicYearAction;
use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\Device;
use App\Models\Employee;
use App\Models\GradeLevel;
use App\Models\SchoolProgram;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use Database\Seeders\GradeLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(GradeLevelSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

// ─── fixtures ────────────────────────────────────────────────────────────

/** @return array{Section, AcademicYear} */
function arSection(Branch $branch, string $grade = 'G1', string $name = 'A', ?AcademicYear $year = null): array
{
    $year ??= (new SaveAcademicYearAction())->execute($branch, ['name' => '2017 E.C.', 'status' => 'active']);
    $section = $branch->sections()->create([
        'school_id' => $branch->school_id,
        'grade_level_id' => GradeLevel::where('code', $grade)->value('id'),
        'name' => $name,
    ]);

    return [$section, $year];
}

function arStudent(Branch $branch, Section $section, AcademicYear $year, string $first, string $gender = 'male'): Student
{
    $student = Student::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => $first, 'father_name' => 'Report', 'gender' => $gender,
    ]);
    StudentEnrollment::create([
        'student_id' => $student->id, 'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'academic_year_id' => $year->id, 'school_program_id' => SchoolProgram::defaultFor($branch)->id,
        'section_id' => $section->id,
        'grade_level_id' => $section->grade_level_id, 'status' => 'active', 'enrolled_on' => now(),
    ]);

    return $student;
}

function arMark(Student $student, Section $section, string $date, string $status, array $extra = []): AttendanceRecord
{
    return AttendanceRecord::create(array_merge([
        'school_id' => $section->school_id,
        'branch_id' => $section->branch_id,
        'section_id' => $section->id,
        'student_id' => $student->id,
        'date' => $date,
        'status' => $status,
        'source' => 'manual',
    ], $extra));
}

function arDevice(Branch $branch, string $name = 'Main gate'): Device
{
    return Device::create([
        'school_id' => $branch->school_id,
        'branch_id' => $branch->id,
        'name' => $name,
        'audience' => 'students',
        'token_hash' => Device::hashToken(Device::mintToken()),
    ]);
}

// ─── branch lane (director) ──────────────────────────────────────────────

it('summarises a branch register for the director — and never another tenant', function () {
    $branch = makeBranch();
    [$section, $year] = arSection($branch);
    $abel = arStudent($branch, $section, $year, 'Abel');
    $bini = arStudent($branch, $section, $year, 'Bini', 'female');
    $gate = arDevice($branch);

    arMark($abel, $section, '2026-06-10', 'present', ['source' => 'device', 'device_id' => $gate->id, 'check_in' => '07:50']);
    arMark($bini, $section, '2026-06-10', 'absent');
    arMark($abel, $section, '2026-06-11', 'late', ['source' => 'device', 'device_id' => $gate->id, 'check_in' => '08:40']);
    arMark($bini, $section, '2026-06-11', 'present');

    // Another school entirely — must never bleed in.
    $foreign = makeBranch('AA-0002');
    [$foreignSection, $foreignYear] = arSection($foreign);
    arMark(arStudent($foreign, $foreignSection, $foreignYear, 'Chala'), $foreignSection, '2026-06-10', 'absent');

    Sanctum::actingAs(directorOf($branch));

    $response = $this->getJson(
        '/api/v1/attendance-reports/overview?from=2026-06-01&to=2026-06-30',
        branchContext($branch),
    )->assertOk();

    $response
        ->assertJsonPath('data.totals.marks', 4)
        ->assertJsonPath('data.totals.students', 2)
        ->assertJsonPath('data.totals.by_status.present', 2)
        ->assertJsonPath('data.totals.by_status.late', 1)
        ->assertJsonPath('data.totals.by_status.absent', 1)
        ->assertJsonPath('data.totals.attendance_rate', 75)
        ->assertJsonPath('data.window.school_days', 2)
        ->assertJsonPath('data.sources.manual', 2)
        ->assertJsonPath('data.sources.device', 2)
        ->assertJsonPath('data.sources.devices.0.name', 'Main gate')
        ->assertJsonPath('data.sources.devices.0.marks', 2)
        ->assertJsonPath('data.sources.devices.0.late', 1)
        ->assertJsonPath('data.absences.total', 1)
        ->assertJsonPath('data.absences.by_gender.female', 1)
        ->assertJsonPath('data.absences.by_gender.male', 0)
        ->assertJsonPath('data.coverage.recorded', 4)
        ->assertJsonPath('data.coverage.expected', 4)
        ->assertJsonPath('data.punctuality.late', 1);

    expect($response->json('meta.devices'))->toHaveCount(1);
});

it('compares against the previous window of equal length', function () {
    $branch = makeBranch();
    [$section, $year] = arSection($branch);
    $abel = arStudent($branch, $section, $year, 'Abel');

    // Current window (June 10–19): 1/2 attended. Previous (May 31–June 9): 2/2.
    arMark($abel, $section, '2026-06-10', 'present');
    arMark($abel, $section, '2026-06-11', 'absent');
    arMark($abel, $section, '2026-06-05', 'present');
    arMark($abel, $section, '2026-06-06', 'present');

    Sanctum::actingAs(directorOf($branch));

    $this->getJson(
        '/api/v1/attendance-reports/overview?from=2026-06-10&to=2026-06-19',
        branchContext($branch),
    )
        ->assertOk()
        ->assertJsonPath('data.totals.attendance_rate', 50)
        ->assertJsonPath('data.totals.previous_rate', 100);
});

it('filters by source, device, grade and section', function () {
    $branch = makeBranch();
    [$sectionA, $year] = arSection($branch, 'G1', 'A');
    [$sectionB] = arSection($branch, 'G2', 'B', $year);
    $abel = arStudent($branch, $sectionA, $year, 'Abel');
    $chala = arStudent($branch, $sectionB, $year, 'Chala');
    $gate = arDevice($branch, 'North gate');
    $yard = arDevice($branch, 'Yard door');

    arMark($abel, $sectionA, '2026-06-10', 'present', ['source' => 'device', 'device_id' => $gate->id]);
    arMark($abel, $sectionA, '2026-06-11', 'present', ['source' => 'device', 'device_id' => $yard->id]);
    arMark($chala, $sectionB, '2026-06-10', 'absent');

    Sanctum::actingAs(directorOf($branch));
    $base = '/api/v1/attendance-reports/overview?from=2026-06-01&to=2026-06-30';

    $this->getJson("{$base}&source=manual", branchContext($branch))
        ->assertOk()->assertJsonPath('data.totals.marks', 1);

    $this->getJson("{$base}&device_id={$gate->id}", branchContext($branch))
        ->assertOk()->assertJsonPath('data.totals.marks', 1);

    $gradeTwo = GradeLevel::where('code', 'G2')->value('id');
    $this->getJson("{$base}&grade_level_id={$gradeTwo}", branchContext($branch))
        ->assertOk()->assertJsonPath('data.totals.marks', 1)
        ->assertJsonPath('data.totals.by_status.absent', 1);

    $this->getJson("{$base}&section_id={$sectionA->id}", branchContext($branch))
        ->assertOk()->assertJsonPath('data.totals.marks', 2);
});

// ─── school-wide + platform lanes ────────────────────────────────────────

it('gives a principal the whole school, narrowable to one branch, compared by branch', function () {
    $branchA = makeBranch();
    $branchB = $branchA->school->branches()->create(['name' => 'West', 'code' => 'AA-0002']);
    [$sectionA, $yearA] = arSection($branchA);
    [$sectionB, $yearB] = arSection($branchB);
    arMark(arStudent($branchA, $sectionA, $yearA, 'Abel'), $sectionA, '2026-06-10', 'present');
    arMark(arStudent($branchB, $sectionB, $yearB, 'Bini'), $sectionB, '2026-06-10', 'absent');

    Sanctum::actingAs(schoolPrincipal($branchA));

    $this->getJson(
        '/api/v1/attendance-reports/overview?from=2026-06-01&to=2026-06-30',
        schoolContext($branchA),
    )->assertOk()->assertJsonPath('data.totals.marks', 2);

    $this->getJson(
        "/api/v1/attendance-reports/overview?from=2026-06-01&to=2026-06-30&branch_id={$branchB->id}",
        schoolContext($branchA),
    )->assertOk()->assertJsonPath('data.totals.marks', 1);

    $trends = $this->getJson(
        '/api/v1/attendance-reports/trends?from=2026-06-01&to=2026-06-30',
        schoolContext($branchA),
    )->assertOk();
    expect($trends->json('data.breakdown.group'))->toBe('branch');
    expect($trends->json('data.breakdown.rows'))->toHaveCount(2);
});

it('gives Temari.et staff the whole platform, compared by school', function () {
    $branchA = makeBranch();
    $branchB = makeBranch('AA-0002');
    [$sectionA, $yearA] = arSection($branchA);
    [$sectionB, $yearB] = arSection($branchB);
    arMark(arStudent($branchA, $sectionA, $yearA, 'Abel'), $sectionA, '2026-06-10', 'present');
    arMark(arStudent($branchB, $sectionB, $yearB, 'Bini'), $sectionB, '2026-06-10', 'late');

    Sanctum::actingAs(platformAdmin());

    $this->getJson('/api/v1/attendance-reports/overview?from=2026-06-01&to=2026-06-30')
        ->assertOk()->assertJsonPath('data.totals.marks', 2);

    $this->getJson("/api/v1/attendance-reports/overview?from=2026-06-01&to=2026-06-30&school_id={$branchB->school_id}")
        ->assertOk()->assertJsonPath('data.totals.marks', 1);

    $trends = $this->getJson('/api/v1/attendance-reports/trends?from=2026-06-01&to=2026-06-30')->assertOk();
    expect($trends->json('data.breakdown.group'))->toBe('school');
    expect($trends->json('data.breakdown.rows'))->toHaveCount(2);
});

it('never lets school staff reach another school through scope params', function () {
    $mine = makeBranch();
    $other = makeBranch('AA-0002');
    [$otherSection, $otherYear] = arSection($other);
    arMark(arStudent($other, $otherSection, $otherYear, 'Chala'), $otherSection, '2026-06-10', 'present');

    Sanctum::actingAs(schoolPrincipal($mine));

    // A foreign school_id / branch_id can only intersect to nothing.
    $this->getJson(
        "/api/v1/attendance-reports/overview?from=2026-06-01&to=2026-06-30&school_id={$other->school_id}&branch_id={$other->id}",
        schoolContext($mine),
    )->assertOk()->assertJsonPath('data.totals.marks', 0);
});

// ─── ownership lane (teachers) + relationship lane ───────────────────────

it('caps a homeroom teacher to their own section and blocks everyone else', function () {
    $branch = makeBranch();
    [$mineSection, $year] = arSection($branch, 'G1', 'A');
    [$otherSection] = arSection($branch, 'G1', 'B', $year);
    $abel = arStudent($branch, $mineSection, $year, 'Abel');
    arMark($abel, $mineSection, '2026-06-10', 'present');
    arMark(arStudent($branch, $otherSection, $year, 'Bini'), $otherSection, '2026-06-10', 'absent');

    $teacher = memberOf($branch);
    $employee = Employee::create([
        'user_id' => $teacher->id, 'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => 'Homeroom',
    ]);
    $mineSection->setHomeroom($year->id, $employee->id);
    Sanctum::actingAs($teacher);

    $this->getJson(
        '/api/v1/attendance-reports/overview?from=2026-06-01&to=2026-06-30',
        branchContext($branch),
    )
        ->assertOk()
        ->assertJsonPath('data.totals.marks', 1)
        ->assertJsonPath('data.totals.by_status.present', 1);

    // A teacher with no homeroom sees an empty report, not a neighbour's.
    Sanctum::actingAs(memberOf($branch));
    $this->getJson(
        '/api/v1/attendance-reports/overview?from=2026-06-01&to=2026-06-30',
        branchContext($branch),
    )->assertOk()->assertJsonPath('data.totals.marks', 0);

    // No staff hat at all → no reports.
    Sanctum::actingAs(User::factory()->create());
    $this->getJson('/api/v1/attendance-reports/overview?from=2026-06-01&to=2026-06-30')
        ->assertForbidden();
});

// ─── per-student ledger ──────────────────────────────────────────────────

it('serves the per-student ledger with rates, streaks and flags', function () {
    $branch = makeBranch();
    [$section, $year] = arSection($branch);
    $spotless = arStudent($branch, $section, $year, 'Spotless');
    $chronic = arStudent($branch, $section, $year, 'Chronic', 'female');

    foreach (range(10, 14) as $day) {
        arMark($spotless, $section, "2026-06-{$day}", 'present');
        // Chronic: present the first 3 days, absent the last 2 (current streak = 2).
        arMark($chronic, $section, "2026-06-{$day}", $day <= 12 ? 'present' : 'absent');
    }

    Sanctum::actingAs(directorOf($branch));
    $base = '/api/v1/attendance-reports/students?from=2026-06-01&to=2026-06-30';

    $response = $this->getJson("{$base}&sort=absent&dir=desc", branchContext($branch))->assertOk();
    $rows = $response->json('data');

    expect($rows)->toHaveCount(2);
    expect($rows[0]['name'])->toContain('Chronic');
    expect($rows[0]['absent'])->toBe(2);
    expect($rows[0]['attendance_rate'])->toEqual(60);
    expect($rows[0]['absent_streak'])->toBe(2);
    expect(collect($rows[0]['last_marks'])->pluck('status')->all())
        ->toBe(['present', 'present', 'present', 'absent', 'absent']);
    expect($rows[1]['absent_streak'])->toBe(0);
    expect($response->json('meta.total'))->toBe(2);

    // Flags: ≥10% of recorded days absent ⇄ not a single absent/late mark.
    $chronicRows = $this->getJson("{$base}&flag=chronic", branchContext($branch))->json('data');
    expect($chronicRows)->toHaveCount(1);
    expect($chronicRows[0]['student_id'])->toBe($chronic->id);

    $perfectRows = $this->getJson("{$base}&flag=perfect", branchContext($branch))->json('data');
    expect($perfectRows)->toHaveCount(1);
    expect($perfectRows[0]['student_id'])->toBe($spotless->id);

    // Search narrows by name; export returns the same shape un-paginated.
    expect($this->getJson("{$base}&search=Spotless", branchContext($branch))->json('data'))->toHaveCount(1);
    expect($this->getJson(
        '/api/v1/attendance-reports/students/export?from=2026-06-01&to=2026-06-30',
        branchContext($branch),
    )->assertOk()->json('data'))->toHaveCount(2);
});

it('charts the daily register, arrivals histogram and grade league for a branch', function () {
    $branch = makeBranch();
    [$section, $year] = arSection($branch);
    $abel = arStudent($branch, $section, $year, 'Abel');
    $gate = arDevice($branch);

    arMark($abel, $section, '2026-06-10', 'present', ['source' => 'device', 'device_id' => $gate->id, 'check_in' => '07:55']);
    arMark($abel, $section, '2026-06-11', 'late', ['source' => 'device', 'device_id' => $gate->id, 'check_in' => '08:35']);

    Sanctum::actingAs(directorOf($branch));

    $response = $this->getJson(
        '/api/v1/attendance-reports/trends?from=2026-06-01&to=2026-06-30',
        branchContext($branch),
    )->assertOk();

    $daily = $response->json('data.daily');
    expect($daily)->toHaveCount(2);
    expect($daily[0])->toMatchArray(['date' => '2026-06-10', 'present' => 1, 'device' => 1, 'manual' => 0]);

    $arrivals = $response->json('data.arrivals');
    expect(collect($arrivals)->pluck('time')->all())->toBe(['07:30', '08:30']);
    expect($arrivals[1]['late'])->toBe(1);

    expect($response->json('data.breakdown.group'))->toBe('grade');
    expect($response->json('data.breakdown.rows.0.rate'))->toEqual(100);
});

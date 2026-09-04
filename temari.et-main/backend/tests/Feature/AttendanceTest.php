<?php

use App\Actions\SaveAcademicYearAction;
use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\GradeLevel;
use App\Models\Membership;
use App\Models\SchoolProgram;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\Sms\SmsClient;
use Database\Seeders\GradeLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(GradeLevelSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    app()->instance(SmsClient::class, Mockery::spy(SmsClient::class));
});

function sectionWithYear(Branch $branch): array
{
    $year = (new SaveAcademicYearAction())->execute($branch, ['name' => '2017 E.C.', 'status' => 'active']);
    $section = $branch->sections()->create([
        'school_id' => $branch->school_id,
        'grade_level_id' => GradeLevel::where('code', 'G1')->value('id'),
        'name' => 'A',
    ]);

    return [$section, $year];
}

function enroll(Branch $branch, Section $section, AcademicYear $year, string $first): Student
{
    $student = Student::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => $first, 'father_name' => 'Test', 'gender' => 'male',
    ]);
    StudentEnrollment::create([
        'student_id' => $student->id, 'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'academic_year_id' => $year->id, 'school_program_id' => SchoolProgram::defaultFor($branch)->id,
        'section_id' => $section->id,
        'grade_level_id' => $section->grade_level_id, 'status' => 'active', 'enrolled_on' => now(),
    ]);

    return $student;
}

it('returns a roster of enrolled students with no marks before attendance is taken', function () {
    $branch = makeBranch();
    [$section, $year] = sectionWithYear($branch);
    enroll($branch, $section, $year, 'Abel');
    enroll($branch, $section, $year, 'Bini');
    Sanctum::actingAs(directorOf($branch));

    $response = $this->getJson("/api/v1/sections/{$section->id}/attendance?date=2026-06-20")->assertOk();

    expect($response->json('data'))->toHaveCount(2);
    expect($response->json('data.0.status'))->toBeNull();
    $response->assertJsonPath('meta.date', '2026-06-20');
});

it('saves a section\'s attendance and reflects it on the roster', function () {
    $branch = makeBranch();
    [$section, $year] = sectionWithYear($branch);
    $abel = enroll($branch, $section, $year, 'Abel');
    $bini = enroll($branch, $section, $year, 'Bini');
    Sanctum::actingAs(directorOf($branch));

    $this->postJson("/api/v1/sections/{$section->id}/attendance", [
        'date' => '2026-06-20',
        'records' => [
            ['student_id' => $abel->id, 'status' => 'present', 'check_in' => '08:05', 'check_out' => '15:30'],
            ['student_id' => $bini->id, 'status' => 'absent', 'note' => 'Sick'],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('meta.saved', 2);

    $roster = $this->getJson("/api/v1/sections/{$section->id}/attendance?date=2026-06-20")->json('data');
    $byId = collect($roster)->keyBy('student_id');
    expect($byId[$abel->id]['status'])->toBe('present');
    expect($byId[$abel->id]['check_in'])->toBe('08:05');
    expect($byId[$abel->id]['check_out'])->toBe('15:30');
    expect($byId[$bini->id]['status'])->toBe('absent');
    expect($byId[$bini->id]['note'])->toBe('Sick');
    expect($byId[$bini->id]['check_in'])->toBeNull();
});

it('is idempotent — re-saving corrects the mark without duplicating', function () {
    $branch = makeBranch();
    [$section, $year] = sectionWithYear($branch);
    $abel = enroll($branch, $section, $year, 'Abel');
    Sanctum::actingAs(directorOf($branch));

    $payload = fn (string $status) => [
        'date' => '2026-06-20',
        'records' => [['student_id' => $abel->id, 'status' => $status]],
    ];

    $this->postJson("/api/v1/sections/{$section->id}/attendance", $payload('present'))->assertOk();
    $this->postJson("/api/v1/sections/{$section->id}/attendance", $payload('absent'))->assertOk();

    expect(AttendanceRecord::where('student_id', $abel->id)->where('date', '2026-06-20')->count())->toBe(1);
    expect(AttendanceRecord::where('student_id', $abel->id)->where('date', '2026-06-20')->value('status')->value)
        ->toBe('absent');
});

it('ignores students who are not enrolled in the section', function () {
    $branch = makeBranch();
    [$section, $year] = sectionWithYear($branch);
    $abel = enroll($branch, $section, $year, 'Abel');
    $outsider = Student::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => 'Out', 'father_name' => 'Sider', 'gender' => 'female',
    ]);
    Sanctum::actingAs(directorOf($branch));

    $this->postJson("/api/v1/sections/{$section->id}/attendance", [
        'date' => '2026-06-20',
        'records' => [
            ['student_id' => $abel->id, 'status' => 'present'],
            ['student_id' => $outsider->id, 'status' => 'present'],
        ],
    ])->assertOk();

    expect(AttendanceRecord::where('student_id', $outsider->id)->exists())->toBeFalse();
    expect(AttendanceRecord::where('section_id', $section->id)->count())->toBe(1);
});

it('lets the homeroom teacher record attendance for their own section', function () {
    $branch = makeBranch();
    [$section, $year] = sectionWithYear($branch);
    $abel = enroll($branch, $section, $year, 'Abel');

    $teacher = User::factory()->create();
    Membership::create([
        'user_id' => $teacher->id, 'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'role' => Role::Teacher->value, 'scope' => Role::Teacher->scope()->value, 'is_active' => true,
    ]);
    $employee = Employee::create([
        'user_id' => $teacher->id, 'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => 'Homeroom',
    ]);
    $section->setHomeroom($year->id, $employee->id);
    Sanctum::actingAs($teacher);

    $this->postJson("/api/v1/sections/{$section->id}/attendance", [
        'date' => '2026-06-20',
        'records' => [['student_id' => $abel->id, 'status' => 'late']],
    ])->assertOk();

    expect(AttendanceRecord::where('section_id', $section->id)->value('status')->value)->toBe('late');
});

it('forbids a teacher from recording attendance for a section that is not theirs', function () {
    $branch = makeBranch();
    [$section, $year] = sectionWithYear($branch);
    $abel = enroll($branch, $section, $year, 'Abel');

    // A teacher of the branch with no homeroom and no teaching assignment here.
    $teacher = memberOf($branch);
    Sanctum::actingAs($teacher);

    $this->postJson("/api/v1/sections/{$section->id}/attendance", [
        'date' => '2026-06-20',
        'records' => [['student_id' => $abel->id, 'status' => 'late']],
    ])->assertForbidden();
});

it('forbids recording attendance for a section in another branch', function () {
    $branchA = makeBranch('AA-0001');
    $branchB = makeBranch('AA-0002');
    [$sectionB, $yearB] = sectionWithYear($branchB);
    $student = enroll($branchB, $sectionB, $yearB, 'Abel');
    Sanctum::actingAs(directorOf($branchA));

    $this->postJson("/api/v1/sections/{$sectionB->id}/attendance", [
        'date' => '2026-06-20',
        'records' => [['student_id' => $student->id, 'status' => 'present']],
    ])->assertForbidden();
});

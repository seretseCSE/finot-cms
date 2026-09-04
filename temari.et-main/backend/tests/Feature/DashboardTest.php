<?php

use App\Actions\SaveAcademicYearAction;
use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\GradeLevel;
use App\Models\Invoice;
use App\Models\ParentProfile;
use App\Models\Payment;
use App\Models\School;
use App\Models\SchoolProgram;
use App\Models\Section;
use App\Models\SectionHomeroom;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Support\Ethiopia;
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
function dashSection(Branch $branch): array
{
    $year = (new SaveAcademicYearAction())->execute($branch, ['name' => '2018 E.C.', 'status' => 'active']);
    // The dashboard's term strip reads the branch's CURRENT term.
    $year->terms()->first()?->update([
        'status' => 'active', 'is_current' => true,
        'starts_on' => now()->subDays(30)->toDateString(),
        'ends_on' => now()->addDays(60)->toDateString(),
    ]);
    $section = $branch->sections()->create([
        'school_id' => $branch->school_id,
        'grade_level_id' => GradeLevel::where('code', 'G1')->value('id'),
        'name' => 'A',
    ]);

    return [$section, $year];
}

function dashStudent(Branch $branch, Section $section, AcademicYear $year, string $first, string $status = 'active'): Student
{
    $student = Student::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => $first, 'father_name' => 'Dash', 'gender' => 'male',
    ]);
    StudentEnrollment::create([
        'student_id' => $student->id, 'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'academic_year_id' => $year->id, 'school_program_id' => SchoolProgram::defaultFor($branch)->id,
        'section_id' => $section->id,
        'grade_level_id' => $section->grade_level_id, 'status' => $status, 'enrolled_on' => now(),
    ]);

    return $student;
}

// ─── branch lane (director) ──────────────────────────────────────────────

it('assembles the director dashboard for one branch', function () {
    $branch = makeBranch();
    [$section, $year] = dashSection($branch);
    $abel = dashStudent($branch, $section, $year, 'Abel');
    dashStudent($branch, $section, $year, 'Bini');
    dashStudent($branch, $section, $year, 'Chaltu', status: 'pending');

    AttendanceRecord::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'section_id' => $section->id, 'student_id' => $abel->id,
        'date' => Ethiopia::today(), 'status' => 'present', 'source' => 'manual',
    ]);

    Sanctum::actingAs(directorOf($branch, financeAccess: false));
    $response = $this->withHeaders(branchContext($branch))->getJson('/api/v1/dashboard')->assertOk();

    $data = $response->json('data');

    expect($data['org']['students']['active'])->toBe(2)
        ->and($data['org']['students']['pending'])->toBe(1)
        ->and($data['attendance']['today']['marked'])->toBe(1)
        ->and($data['attendance']['today']['present'])->toBe(1)
        ->and($data['attendance']['today']['enrolled'])->toBe(2)
        ->and($data['context']['term'])->not->toBeNull()
        ->and($data['context']['ethiopian']['year'])->toBeGreaterThan(2015)
        // fees.reports.view is deliberately UNGATED for directors (they
        // chase unpaid students), so the receivables block rides along.
        ->and($data['finance']['receivables']['students'])->toBe(0)
        ->and($data)->not->toHaveKey('platform')
        ->and($data)->not->toHaveKey('branches');

    // The pending-enrollment pile surfaces in the action queue.
    $queue = collect($data['queue'])->keyBy('key');
    expect($queue->get('pending_enrollments')['count'])->toBe(1);
});

it('computes the money vitals — collections, receivables, overdue', function () {
    $branch = makeBranch();
    [$section, $year] = dashSection($branch);
    $student = dashStudent($branch, $section, $year, 'Abel');

    $invoice = Invoice::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'student_id' => $student->id, 'academic_year_id' => $year->id,
        'title' => 'Tuition', 'amount' => '1000.00', 'amount_paid' => '400.00',
        'status' => 'partial', 'due_date' => now()->subDay()->toDateString(),
    ]);
    Payment::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'invoice_id' => $invoice->id, 'student_id' => $student->id,
        'amount' => '400.00', 'method' => 'cash',
        'receipt_number' => 'RC-1', 'receipt_token' => str_repeat('a', 40),
        'paid_at' => Ethiopia::today(),
    ]);

    Sanctum::actingAs(directorOf($branch)); // helper flips director_finance_access ON
    $data = $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/dashboard')->assertOk()->json('data');

    expect($data['finance']['month']['collected'])->toBe('400.00')
        ->and($data['finance']['receivables']['balance'])->toBe('600.00')
        ->and($data['finance']['receivables']['overdue'])->toBe('600.00')
        ->and($data['finance']['receivables']['students'])->toBe(1)
        ->and($data['finance']['trend'])->toHaveCount(6);
});

// ─── tenant isolation ────────────────────────────────────────────────────

it('never leaks another tenant into the dashboard numbers', function () {
    $mine = makeBranch();
    [$section, $year] = dashSection($mine);
    dashStudent($mine, $section, $year, 'Abel');

    $other = makeBranch('AA-0002');
    [$otherSection, $otherYear] = dashSection($other);
    dashStudent($other, $otherSection, $otherYear, 'Zara');
    dashStudent($other, $otherSection, $otherYear, 'Yonas');
    AttendanceRecord::create([
        'school_id' => $other->school_id, 'branch_id' => $other->id,
        'section_id' => $otherSection->id,
        'student_id' => Student::where('first_name', 'Zara')->value('id'),
        'date' => Ethiopia::today(), 'status' => 'present', 'source' => 'manual',
    ]);

    Sanctum::actingAs(directorOf($mine, financeAccess: false));
    $data = $this->withHeaders(branchContext($mine))
        ->getJson('/api/v1/dashboard')->assertOk()->json('data');

    expect($data['org']['students']['active'])->toBe(1)
        ->and($data['attendance']['today']['marked'])->toBe(0)
        ->and($data['attendance']['today']['enrolled'])->toBe(1);
});

// ─── school-wide lane (principal) ────────────────────────────────────────

it('gives the principal a school pulse with per-branch comparison', function () {
    $branch = makeBranch();
    [$section, $year] = dashSection($branch);
    dashStudent($branch, $section, $year, 'Abel');
    $second = Branch::create(['school_id' => $branch->school_id, 'name' => 'West', 'code' => 'AA-0009']);

    Sanctum::actingAs(schoolPrincipal($branch));
    $data = $this->withHeaders(schoolContext($branch))
        ->getJson('/api/v1/dashboard')->assertOk()->json('data');

    expect($data)->toHaveKey('branches')
        ->and(collect($data['branches'])->pluck('id')->all())->toContain($branch->id, $second->id)
        ->and(collect($data['branches'])->firstWhere('id', $branch->id)['students'])->toBe(1)
        ->and($data['org']['students']['active'])->toBe(1)
        ->and($data)->not->toHaveKey('platform');
});

// ─── teacher lane ────────────────────────────────────────────────────────

it('builds the teacher "my day" block and hides supervisory piles', function () {
    $branch = makeBranch();
    [$section, $year] = dashSection($branch);
    dashStudent($branch, $section, $year, 'Abel');

    $teacher = memberOf($branch, Role::Teacher);
    $employee = Employee::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'user_id' => $teacher->id, 'first_name' => 'Marta', 'father_name' => 'Teach',
        'is_active' => true,
    ]);
    SectionHomeroom::create([
        'section_id' => $section->id, 'academic_year_id' => $year->id, 'employee_id' => $employee->id,
    ]);

    Sanctum::actingAs($teacher);
    $data = $this->withHeaders(branchContext($branch))
        ->getJson('/api/v1/dashboard')->assertOk()->json('data');

    expect($data['teacher'])->not->toBeNull()
        ->and($data['teacher']['homerooms'][0]['students'])->toBe(1)
        ->and($data['teacher']['homerooms'][0]['marked_today'])->toBe(0)
        ->and($data['teacher']['marklists'])->toBe(['draft' => 0, 'submitted' => 0, 'approved' => 0])
        ->and($data)->not->toHaveKey('finance')
        ->and($data)->not->toHaveKey('attendance')
        ->and($data)->not->toHaveKey('staff_today');
});

// ─── platform lane ───────────────────────────────────────────────────────

it('gives platform staff the platform pulse in the global workspace', function () {
    $branch = makeBranch();
    [$section, $year] = dashSection($branch);
    dashStudent($branch, $section, $year, 'Abel');

    Sanctum::actingAs(platformAdmin());
    $data = $this->getJson('/api/v1/dashboard')->assertOk()->json('data');

    expect($data['platform']['schools'])->toBe(1)
        ->and($data['platform']['branches'])->toBe(1)
        ->and($data['platform']['students'])->toBe(1)
        ->and($data['platform']['recent_schools'])->toHaveCount(1);
});

// ─── relationship-only users ─────────────────────────────────────────────

it('turns relationship-only users away — parents live on /me', function () {
    $parent = User::factory()->create();
    ParentProfile::create(['user_id' => $parent->id]);

    Sanctum::actingAs($parent);
    $this->getJson('/api/v1/dashboard')->assertForbidden();
});

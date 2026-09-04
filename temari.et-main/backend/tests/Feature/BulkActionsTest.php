<?php

use App\Enums\AbsenceExcuseStatus;
use App\Enums\AttendanceStatus;
use App\Enums\LeaveRequestStatus;
use App\Enums\Role;
use App\Models\AbsenceExcuse;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\FinanceCategory;
use App\Models\GradeLevel;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\ParentProfile;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentGuardian;
use App\Models\User;
use App\Services\Sms\SmsClient;
use Database\Seeders\GradeLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

/**
 * The BULK ACTION CONTRACT (CLAUDE.md §5), asserted once for every table that
 * offers one:
 *
 *   1. authorize PER ROW — a sweep is never a way around a single-row rule,
 *   2. do what you can, and
 *   3. report what you could not, with a stable machine `reason`.
 *
 * Domain behaviour lives in each feature's own suite; what is guarded here is
 * that no bulk endpoint becomes a hole in the kernel or silently over-reports.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(GradeLevelSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->sms = Mockery::spy(SmsClient::class);
    app()->instance(SmsClient::class, $this->sms);
});

/** An employee with a portal account in the branch. */
function bulkEmployee($branch, string $name = 'Kebede', ?User $user = null): Employee
{
    $employee = Employee::create([
        'user_id' => ($user ?? User::factory()->create())->id,
        'school_id' => $branch->school_id,
        'branch_id' => $branch->id,
        'first_name' => $name,
        'is_active' => true,
    ]);
    $employee->positions()->create([
        'job_title' => 'teacher', 'salary' => 8000, 'is_primary' => true, 'hired_on' => '2020-09-01',
    ]);

    return $employee;
}

function bulkLeaveRequest(Employee $employee, string $status = 'pending'): LeaveRequest
{
    return LeaveRequest::create([
        'school_id' => $employee->school_id,
        'branch_id' => $employee->branch_id,
        'employee_id' => $employee->id,
        'leave_type_id' => LeaveType::where('school_id', $employee->school_id)->where('code', 'annual')->value('id'),
        'start_date' => '2026-10-05',
        'end_date' => '2026-10-06',
        'days' => 2,
        'reason' => 'Family',
        'status' => $status,
        'requested_by' => $employee->user_id,
    ]);
}

// ── HR: leave requests ──────────────────────────────────────────────────────

it('bulk-decides leave and reports the rows it could not touch', function () {
    $branch = makeBranch();
    // Provision the statutory catalog for this school.
    Sanctum::actingAs(directorOf($branch));
    $this->withHeaders(branchContext($branch))->getJson('/api/v1/hr/leave-types')->assertOk();

    $pending = bulkLeaveRequest(bulkEmployee($branch, 'Alem'));
    $settled = bulkLeaveRequest(bulkEmployee($branch, 'Bekele'), 'approved');

    $response = $this->withHeaders(branchContext($branch))->postJson('/api/v1/hr/leave-requests/bulk/decide', [
        'ids' => [$pending->id, $settled->id, 999999],
        'decision' => 'approved',
    ])->assertOk();

    expect($response->json('meta.decided'))->toBe(1)
        ->and(collect($response->json('meta.skipped'))->pluck('reason')->all())
        ->toEqualCanonicalizing(['already_decided', 'not_found'])
        ->and($pending->fresh()->status)->toBe(LeaveRequestStatus::Approved);
});

it('never lets a bulk leave decision reach another school', function () {
    $branchA = makeBranch('AA-0001');
    $branchB = makeBranch('BB-0001');

    Sanctum::actingAs(directorOf($branchB));
    $this->withHeaders(branchContext($branchB))->getJson('/api/v1/hr/leave-types')->assertOk();
    $foreign = bulkLeaveRequest(bulkEmployee($branchB, 'Chaltu'));

    Sanctum::actingAs(directorOf($branchA));

    $response = $this->withHeaders(branchContext($branchA))->postJson('/api/v1/hr/leave-requests/bulk/decide', [
        'ids' => [$foreign->id],
        'decision' => 'approved',
    ])->assertOk();

    expect($response->json('meta.decided'))->toBe(0)
        ->and($response->json('meta.skipped.0.reason'))->toBe('not_permitted')
        ->and($foreign->fresh()->status)->toBe(LeaveRequestStatus::Pending);
});

it('requires a reason when a bulk leave decision is a rejection', function () {
    $branch = makeBranch();
    Sanctum::actingAs(directorOf($branch));

    $this->withHeaders(branchContext($branch))->postJson('/api/v1/hr/leave-requests/bulk/decide', [
        'ids' => [1],
        'decision' => 'rejected',
    ])->assertStatus(422)->assertJsonValidationErrors('decision_note');
});

// ── Attendance: parent-filed absence excuses ────────────────────────────────

it('bulk-approves absence excuses and retro-marks the absent days', function () {
    $branch = makeBranch();
    $registrar = memberOf($branch, Role::Registrar);
    $year = activeYear($branch);
    $section = Section::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'academic_year_id' => $year->id,
        'grade_level_id' => GradeLevel::query()->value('id'),
        'name' => 'A', 'capacity' => 40, 'is_active' => true,
    ]);
    $student = Student::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => 'Sara', 'father_name' => 'Tadesse', 'gender' => 'female',
        'date_of_birth' => '2014-05-05',
    ]);

    AttendanceRecord::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'section_id' => $section->id,
        'student_id' => $student->id, 'date' => '2026-05-04',
        'status' => AttendanceStatus::Absent->value,
    ]);

    $excuse = AbsenceExcuse::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'student_id' => $student->id, 'requested_by' => $registrar->id,
        'starts_on' => '2026-05-04', 'ends_on' => '2026-05-04',
        'reason' => 'Fever', 'status' => AbsenceExcuseStatus::Pending->value,
    ]);

    Sanctum::actingAs($registrar);

    $response = $this->withHeaders(branchContext($branch))->postJson('/api/v1/absence-excuses/bulk/decide', [
        'ids' => [$excuse->id],
        'decision' => 'approved',
    ])->assertOk();

    expect($response->json('meta.decided'))->toBe(1)
        ->and($response->json('meta.excused_days'))->toBe(1)
        ->and(AttendanceRecord::first()->status)->toBe(AttendanceStatus::Excused);
});

// ── Finance: the four-eyes rule survives a sweep ────────────────────────────

it('skips the approver\'s own expenses in a bulk decision (four-eyes)', function () {
    $branch = makeBranch();
    activeYear($branch);
    $officer = memberOf($branch, Role::FinanceOfficer);
    $principal = schoolPrincipal($branch);

    $categoryId = FinanceCategory::create([
        'school_id' => $branch->school_id, 'kind' => 'expense', 'name' => 'Rent', 'is_active' => true,
    ])->id;

    $make = fn (User $recorder) => Expense::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'finance_category_id' => $categoryId, 'title' => 'Water', 'amount' => 500,
        'expense_date' => '2026-05-01', 'method' => 'cash', 'status' => 'pending',
        'recorded_by' => $recorder->id,
    ]);

    $byOther = $make($officer);
    $byPrincipal = $make($principal);

    Sanctum::actingAs($principal);

    $response = $this->withHeaders(schoolContext($branch))->postJson('/api/v1/finance/expenses/bulk/decide', [
        'ids' => [$byOther->id, $byPrincipal->id],
        'decision' => 'approved',
    ])->assertOk();

    expect($response->json('meta.decided'))->toBe(1)
        ->and($response->json('meta.skipped.0.reason'))->toBe('self_approval')
        ->and($byOther->fresh()->status)->toBe('approved')
        ->and($byPrincipal->fresh()->status)->toBe('pending');
});

// ── People registers ────────────────────────────────────────────────────────

it('bulk-removes employees, withdraws their branch access, and restores them', function () {
    $branch = makeBranch();
    $employee = bulkEmployee($branch, 'Dawit');

    Sanctum::actingAs(schoolPrincipal($branch));

    $this->withHeaders(schoolContext($branch))
        ->postJson('/api/v1/employees/bulk/delete', ['ids' => [$employee->id]])
        ->assertOk()
        ->assertJsonPath('meta.deleted', 1);

    expect(Employee::find($employee->id))->toBeNull();

    $this->withHeaders(schoolContext($branch))
        ->postJson('/api/v1/employees/bulk/restore', ['ids' => [$employee->id]])
        ->assertOk()
        ->assertJsonPath('meta.restored', 1);

    expect(Employee::find($employee->id))->not->toBeNull();
});

it('refuses to let a bulk employee removal strip the actor\'s own access', function () {
    $branch = makeBranch();
    $principal = schoolPrincipal($branch);
    $ownFile = bulkEmployee($branch, 'Selam', $principal);

    Sanctum::actingAs($principal);

    $response = $this->withHeaders(schoolContext($branch))
        ->postJson('/api/v1/employees/bulk/delete', ['ids' => [$ownFile->id]])
        ->assertOk();

    expect($response->json('meta.deleted'))->toBe(0)
        ->and($response->json('meta.skipped.0.reason'))->toBe('self')
        ->and(Employee::find($ownFile->id))->not->toBeNull();
});

it('bulk-deletes and restores students', function () {
    $branch = makeBranch();
    $student = Student::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => 'Hana', 'father_name' => 'Girma', 'gender' => 'female',
        'date_of_birth' => '2015-01-01',
    ]);

    Sanctum::actingAs(schoolPrincipal($branch));

    $this->withHeaders(schoolContext($branch))
        ->postJson('/api/v1/students/bulk/delete', ['ids' => [$student->id]])
        ->assertOk()
        ->assertJsonPath('meta.deleted', 1);
    expect(Student::find($student->id))->toBeNull();

    $this->withHeaders(schoolContext($branch))
        ->postJson('/api/v1/students/bulk/restore', ['ids' => [$student->id]])
        ->assertOk()
        ->assertJsonPath('meta.restored', 1);
    expect(Student::find($student->id))->not->toBeNull();
});

it('never deletes another school\'s students in a sweep', function () {
    $branchA = makeBranch('AA-0001');
    $branchB = makeBranch('BB-0001');
    $foreign = Student::create([
        'school_id' => $branchB->school_id, 'branch_id' => $branchB->id,
        'first_name' => 'Foreign', 'father_name' => 'Child', 'gender' => 'male',
        'date_of_birth' => '2015-01-01',
    ]);

    Sanctum::actingAs(schoolPrincipal($branchA));

    $response = $this->withHeaders(schoolContext($branchA))
        ->postJson('/api/v1/students/bulk/delete', ['ids' => [$foreign->id]])
        ->assertOk();

    expect($response->json('meta.deleted'))->toBe(0)
        ->and($response->json('meta.skipped.0.reason'))->toBe('not_permitted')
        ->and(Student::find($foreign->id))->not->toBeNull();
});

it('reports guardians who cannot be sent a portal invite', function () {
    $branch = makeBranch();
    $student = Student::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => 'Meron', 'father_name' => 'Abera', 'gender' => 'female',
        'date_of_birth' => '2014-02-02',
    ]);
    $parentUser = User::factory()->create(['last_login_at' => now()]);
    $parent = ParentProfile::create([
        'user_id' => $parentUser->id, 'first_name' => 'Almaz',
    ]);
    StudentGuardian::create([
        'student_id' => $student->id, 'parent_id' => $parent->id,
        'relationship' => 'mother', 'is_primary' => true, 'is_active' => true,
    ]);

    Sanctum::actingAs(schoolPrincipal($branch));

    $response = $this->withHeaders(schoolContext($branch))
        ->postJson('/api/v1/parents/bulk/invite', ['ids' => [$parent->id]])
        ->assertOk();

    // The guardian already signed in — they reset their own PIN instead.
    expect($response->json('meta.sent'))->toBe(0)
        ->and($response->json('meta.skipped.0.reason'))->toBe('account_in_use');

    $this->sms->shouldNotHaveReceived('send');
});

// ── The shared envelope ─────────────────────────────────────────────────────

it('caps every bulk endpoint at 500 rows', function () {
    Sanctum::actingAs(platformAdmin());

    $this->postJson('/api/v1/users/bulk/delete', ['ids' => range(1, 501)])
        ->assertStatus(422)
        ->assertJsonValidationErrors('ids');
});

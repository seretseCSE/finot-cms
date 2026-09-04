<?php

use App\Actions\EnrollStudentAction;
use App\Actions\SaveAcademicYearAction;
use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AssessmentResult;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\GradeLevel;
use App\Models\Membership;
use App\Models\Notification;
use App\Models\Section;
use App\Models\Subject;
use App\Models\SubjectAssignment;
use App\Models\User;
use Database\Seeders\GradeLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SubjectSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

/**
 * The marklist trust rule: while a draft belongs to a teacher with an
 * account, ONLY that teacher types marks. A supervisor gets in exclusively
 * through the loud on-behalf lane (reason + teacher notification + badges),
 * and whoever put marks on a sheet can never countersign it (four-eyes).
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(GradeLevelSeeder::class);
    $this->seed(SubjectSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function trustWorld(): array
{
    $branch = makeBranch();
    $year = (new SaveAcademicYearAction())->execute($branch, ['name' => '2017 E.C.', 'status' => 'active']);
    $section = $branch->sections()->create([
        'school_id' => $branch->school_id,
        'grade_level_id' => GradeLevel::where('code', 'G1')->value('id'),
        'name' => 'A',
    ]);

    $student = $branch->students()->create([
        'school_id' => $branch->school_id,
        'first_name' => 'Abel',
        'father_name' => 'Tesfaye',
        'gender' => 'male',
    ]);
    app(EnrollStudentAction::class)->execute($student, [
        'academic_year_id' => $year->id,
        'section_id' => $section->id,
        'grade_level_id' => $section->grade_level_id,
    ]);

    return [$branch, $year, $section, $student];
}

function trustTeacher(Branch $branch, string $name = 'Alemu'): Employee
{
    $employee = Employee::create([
        'user_id' => User::factory()->create()->id,
        'school_id' => $branch->school_id,
        'branch_id' => $branch->id,
        'first_name' => $name,
        'father_name' => 'Bekele',
        'gender' => 'male',
    ]);

    Membership::create([
        'user_id' => $employee->user_id,
        'school_id' => $branch->school_id,
        'branch_id' => $branch->id,
        'role' => Role::Teacher->value,
        'scope' => Role::Teacher->scope()->value,
        'is_active' => true,
    ]);

    return $employee;
}

function trustAssignment(Branch $branch, AcademicYear $year, Section $section, ?Employee $teacher, string $subjectCode = 'MATH'): SubjectAssignment
{
    return SubjectAssignment::create([
        'school_id' => $branch->school_id,
        'branch_id' => $branch->id,
        'academic_year_id' => $year->id,
        'section_id' => $section->id,
        'subject_id' => Subject::where('code', $subjectCode)->value('id'),
        'term_id' => $year->terms()->first()->id,
        'employee_id' => $teacher?->id,
        'periods_per_week' => 5,
    ]);
}

function trustAssessment(SubjectAssignment $assignment, string $name = 'Final'): Assessment
{
    return Assessment::create([
        'subject_assignment_id' => $assignment->id,
        'type' => 'final_exam',
        'name' => $name,
        'max_score' => 100,
        'weight' => 100,
    ]);
}

it('blocks silent supervisor edits on a teacher-owned draft until on-behalf entry is declared', function () {
    [$branch, $year, $section, $student] = trustWorld();
    $teacher = trustTeacher($branch);
    $assignment = trustAssignment($branch, $year, $section, $teacher);
    $assessment = trustAssessment($assignment);

    $director = directorOf($branch);
    Sanctum::actingAs($director);
    $headers = branchContext($branch);

    // Silent write into the teacher's sheet: denied.
    $this->withHeaders($headers)
        ->postJson("/api/v1/assessments/{$assessment->id}/results", [
            'results' => [['student_id' => $student->id, 'score' => 50]],
        ])->assertForbidden();

    // The grid says so up front: read-only, assist available.
    $grid = $this->withHeaders($headers)
        ->getJson("/api/v1/marklists/{$assignment->id}")
        ->assertOk()
        ->json('data');
    expect($grid['can_edit_marks'])->toBeFalse()
        ->and($grid['can_request_assist'])->toBeTrue();

    // A reason is mandatory — the declaration is the audit record.
    $this->withHeaders($headers)
        ->postJson("/api/v1/marklists/{$assignment->id}/assist")
        ->assertStatus(422);

    $this->withHeaders($headers)
        ->postJson("/api/v1/marklists/{$assignment->id}/assist", [
            'reason' => 'Teacher on sick leave this week',
        ])->assertOk()
        ->assertJsonPath('data.assisted_by_name', $director->name)
        ->assertJsonPath('data.assist_reason', 'Teacher on sick leave this week');

    // The teacher is told immediately, with the reason.
    $note = Notification::where('user_id', $teacher->user_id)->first();
    expect($note)->not->toBeNull()
        ->and($note->event)->toBe('academics.marklist_assist')
        ->and($note->data['reason'])->toBe('Teacher on sick leave this week');

    // Now — and only now — the supervisor may type.
    $this->withHeaders($headers)
        ->postJson("/api/v1/assessments/{$assessment->id}/results", [
            'results' => [['student_id' => $student->id, 'score' => 50]],
        ])->assertOk();

    $after = $this->withHeaders($headers)
        ->getJson("/api/v1/marklists/{$assignment->id}")
        ->assertOk()
        ->json('data');
    expect($after['can_edit_marks'])->toBeTrue()
        ->and($after['can_request_assist'])->toBeFalse()
        ->and($after['marklist']['assisted_by_name'])->toBe($director->name);
});

it('refuses the on-behalf declaration where it makes no sense', function () {
    [$branch, $year, $section] = trustWorld();

    // Vacancy: no teacher account — supervisors enter directly, no declaration.
    $vacant = trustAssignment($branch, $year, $section, null);
    Sanctum::actingAs(directorOf($branch));
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/marklists/{$vacant->id}/assist", ['reason' => 'No teacher assigned yet'])
        ->assertStatus(422);

    // A teacher cannot declare on a colleague's class (no grades.manage).
    $teacher = trustTeacher($branch, 'Alemu');
    $other = trustTeacher($branch, 'Chaltu');
    $owned = trustAssignment($branch, $year, $section, $teacher, 'ENG');
    Sanctum::actingAs($other->user);
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/marklists/{$owned->id}/assist", ['reason' => 'Helping my colleague out'])
        ->assertForbidden();
});

it('lets supervisors enter marks directly when the class has no teacher account', function () {
    [$branch, $year, $section, $student] = trustWorld();
    $assignment = trustAssignment($branch, $year, $section, null);
    $assessment = trustAssessment($assignment);

    Sanctum::actingAs(directorOf($branch));

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/assessments/{$assessment->id}/results", [
            'results' => [['student_id' => $student->id, 'score' => 71]],
        ])->assertOk();
});

it('enforces four-eyes: whoever entered or submitted the marks cannot approve them', function () {
    [$branch, $year, $section, $student] = trustWorld();
    $teacher = trustTeacher($branch);
    $assignment = trustAssignment($branch, $year, $section, $teacher);
    $assessment = trustAssessment($assignment);

    $director = directorOf($branch);
    $headers = branchContext($branch);

    // Director declares assistance and types a mark.
    Sanctum::actingAs($director);
    $this->withHeaders($headers)
        ->postJson("/api/v1/marklists/{$assignment->id}/assist", ['reason' => 'Teacher travelling for training'])
        ->assertOk();
    $this->withHeaders($headers)
        ->postJson("/api/v1/assessments/{$assessment->id}/results", [
            'results' => [['student_id' => $student->id, 'score' => 64]],
        ])->assertOk();

    // Teacher signs off.
    Sanctum::actingAs($teacher->user);
    $this->withHeaders($headers)
        ->postJson("/api/v1/marklists/{$assignment->id}/submit")
        ->assertOk();

    // The assisting director may NOT countersign their own entries…
    Sanctum::actingAs($director);
    $grid = $this->withHeaders($headers)
        ->getJson("/api/v1/marklists/{$assignment->id}")
        ->assertOk()
        ->json('data');
    expect($grid['four_eyes_blocked'])->toBeTrue();

    $this->withHeaders($headers)
        ->postJson("/api/v1/marklists/{$assignment->id}/approve")
        ->assertStatus(422);

    // …but an uninvolved supervisor may.
    Sanctum::actingAs(schoolPrincipal($branch));
    $this->withHeaders($headers)
        ->postJson("/api/v1/marklists/{$assignment->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');
});

it('blocks the submitter from approving their own submission', function () {
    [$branch, $year, $section, $student] = trustWorld();
    $assignment = trustAssignment($branch, $year, $section, null); // vacancy
    $assessment = trustAssessment($assignment);

    $director = directorOf($branch);
    Sanctum::actingAs($director);
    $headers = branchContext($branch);

    $this->withHeaders($headers)
        ->postJson("/api/v1/assessments/{$assessment->id}/results", [
            'results' => [['student_id' => $student->id, 'score' => 80]],
        ])->assertOk();

    $this->withHeaders($headers)
        ->postJson("/api/v1/marklists/{$assignment->id}/submit")
        ->assertOk();

    $this->withHeaders($headers)
        ->postJson("/api/v1/marklists/{$assignment->id}/approve")
        ->assertStatus(422);

    Sanctum::actingAs(schoolPrincipal($branch));
    $this->withHeaders($headers)
        ->postJson("/api/v1/marklists/{$assignment->id}/approve")
        ->assertOk();
});

it('keeps per-cell authorship: re-saving unchanged rows never re-attributes them', function () {
    [$branch, $year, $section, $student] = trustWorld();
    $teacher = trustTeacher($branch);
    $second = $branch->students()->create([
        'school_id' => $branch->school_id,
        'first_name' => 'Sara',
        'father_name' => 'Tesfaye',
        'gender' => 'female',
    ]);
    app(EnrollStudentAction::class)->execute($second, [
        'academic_year_id' => $year->id,
        'section_id' => $section->id,
        'grade_level_id' => $section->grade_level_id,
    ]);

    $assignment = trustAssignment($branch, $year, $section, $teacher);
    $assessment = trustAssessment($assignment);
    $headers = branchContext($branch);

    // Teacher enters both marks.
    Sanctum::actingAs($teacher->user);
    $this->withHeaders($headers)
        ->postJson("/api/v1/assessments/{$assessment->id}/results", [
            'results' => [
                ['student_id' => $student->id, 'score' => 60],
                ['student_id' => $second->id, 'score' => 70],
            ],
        ])->assertOk()
        ->assertJsonPath('meta.count', 2);

    // A director WITH an employee file assists and re-saves the full grid,
    // changing only Sara's mark.
    $director = directorOf($branch);
    $directorEmployee = Employee::create([
        'user_id' => $director->id,
        'school_id' => $branch->school_id,
        'branch_id' => $branch->id,
        'first_name' => 'Worku',
        'father_name' => 'Assefa',
        'gender' => 'male',
    ]);

    Sanctum::actingAs($director);
    $this->withHeaders($headers)
        ->postJson("/api/v1/marklists/{$assignment->id}/assist", ['reason' => 'Correcting a transcription error'])
        ->assertOk();

    $this->withHeaders($headers)
        ->postJson("/api/v1/assessments/{$assessment->id}/results", [
            'results' => [
                ['student_id' => $student->id, 'score' => 60],  // untouched
                ['student_id' => $second->id, 'score' => 75],   // changed
            ],
        ])->assertOk()
        ->assertJsonPath('meta.count', 1);

    // Abel's cell still belongs to the teacher; Sara's now names the director.
    expect(AssessmentResult::where('student_id', $student->id)->value('recorded_by'))->toBe($teacher->id)
        ->and(AssessmentResult::where('student_id', $second->id)->value('recorded_by'))->toBe($directorEmployee->id);

    // The grid surfaces the non-owner recorder for the badges.
    $grid = $this->withHeaders($headers)
        ->getJson("/api/v1/marklists/{$assignment->id}")
        ->assertOk()
        ->json('data');
    expect($grid['recorders'])->toHaveCount(1)
        ->and($grid['recorders'][0]['employee_id'])->toBe($directorEmployee->id)
        ->and($grid['recorders'][0]['cells'])->toBe(1);
});

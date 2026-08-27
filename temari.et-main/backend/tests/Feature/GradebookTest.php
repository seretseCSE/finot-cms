<?php

use App\Actions\SaveAcademicYearAction;
use App\Models\Assessment;
use App\Models\AssessmentResult;
use App\Models\Branch;
use App\Models\GradeLevel;
use App\Models\SchoolProgram;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Models\SubjectAssignment;
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

function setupContinuousAssessment(Branch $branch): array
{
    $grade = GradeLevel::first();
    $section = $branch->sections()->create(['school_id' => $branch->school_id, 'grade_level_id' => $grade->id, 'name' => 'A']);
    $year = (new SaveAcademicYearAction)->execute($branch, ['name' => '2017 E.C.', 'is_current' => true]);
    $term = $year->terms()->first();
    $subject = Subject::where('code', 'MATH')->first();

    $student = Student::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => 'Abel', 'father_name' => 'Kebede', 'gender' => 'male',
    ]);
    StudentEnrollment::create([
        'student_id' => $student->id, 'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'academic_year_id' => $year->id, 'school_program_id' => SchoolProgram::defaultFor($branch)->id,
        'grade_level_id' => $grade->id, 'section_id' => $section->id,
        'status' => 'active', 'enrolled_on' => now(),
    ]);

    $assignment = SubjectAssignment::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id, 'academic_year_id' => $year->id,
        'section_id' => $section->id, 'subject_id' => $subject->id, 'term_id' => $term->id,
    ]);

    return compact('section', 'term', 'subject', 'student', 'assignment');
}

it('creates an assessment under a subject assignment', function () {
    $branch = makeBranch();
    ['assignment' => $assignment] = setupContinuousAssessment($branch);
    Sanctum::actingAs(directorOf($branch));

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/subject-assignments/{$assignment->id}/assessments", [
            'type' => 'mid_exam',
            'name' => 'Mid-term Exam',
            'max_score' => 50,
            'weight' => 40,
            'conducted_on' => '2024-11-15',
        ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'mid_exam');
});

it('bulk-upserts marks for an assessment', function () {
    $branch = makeBranch();
    ['assignment' => $assignment, 'student' => $student] = setupContinuousAssessment($branch);

    $assessment = Assessment::create([
        'subject_assignment_id' => $assignment->id,
        'type' => 'test', 'name' => 'Test 1',
        'max_score' => 20, 'weight' => 20,
    ]);

    Sanctum::actingAs(directorOf($branch));

    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/assessments/{$assessment->id}/results", [
            'results' => [
                ['student_id' => $student->id, 'score' => 17.5, 'is_absent' => false],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('meta.count', 1);

    $this->assertDatabaseHas('assessment_results', [
        'assessment_id' => $assessment->id,
        'student_id' => $student->id,
        'score' => 17.5,
    ]);
});

it('returns a result card for a student', function () {
    $branch = makeBranch();
    ['assignment' => $assignment, 'student' => $student, 'term' => $term] = setupContinuousAssessment($branch);

    $assessment = Assessment::create([
        'subject_assignment_id' => $assignment->id,
        'type' => 'final_exam', 'name' => 'Final',
        'max_score' => 100, 'weight' => 100,
    ]);

    AssessmentResult::create([
        'assessment_id' => $assessment->id,
        'student_id' => $student->id,
        'score' => 82,
        'is_absent' => false,
        'recorded_by' => null,
    ]);

    Sanctum::actingAs(directorOf($branch));

    $this->withHeaders(branchContext($branch))
        ->getJson("/api/v1/reports/students/{$student->id}/result-card?term_id={$term->id}")
        ->assertOk()
        ->assertJsonPath('data.student_id', $student->id)
        ->assertJsonCount(1, 'data.subjects')
        ->assertJsonPath('data.subjects.0.weighted_total', 82);
});

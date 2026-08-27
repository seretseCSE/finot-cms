<?php

use App\Actions\EnrollStudentAction;
use App\Enums\Role;
use App\Models\Branch;
use App\Models\Course;
use App\Models\Employee;
use App\Models\GradeLevel;
use App\Models\QuestionBank;
use App\Models\Quiz;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectAssignment;
use App\Models\User;
use Database\Seeders\GradeLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SubjectSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

/*
 * THE COURSES ENGINE (Phase 3). One engine, three audiences: platform
 * courses (exam-prep catalog, open to ANY authenticated user), school
 * grade-window courses, class courses. Modules → lessons → per-user
 * progress; sequential courses lock forward; quiz lessons complete only
 * through a real submitted attempt.
 */

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(GradeLevelSeeder::class);
    $this->seed(SubjectSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

// ───────────────────────────── fixtures ─────────────────────────────

function courseSection(Branch $branch, string $gradeCode = 'G7'): Section
{
    return $branch->sections()->create([
        'school_id' => $branch->school_id,
        'grade_level_id' => GradeLevel::where('code', $gradeCode)->value('id'),
        'name' => 'A',
    ]);
}

function courseStudent(Branch $branch, Section $section): Student
{
    $user = User::factory()->create();

    $student = Student::create([
        'user_id' => $user->id,
        'first_name' => 'Liya',
        'father_name' => 'Mekonnen',
        'gender' => 'female',
    ]);

    app(EnrollStudentAction::class)->execute($student, [
        'academic_year_id' => activeYear($branch)->id,
        'section_id' => $section->id,
        'grade_level_id' => $section->grade_level_id,
    ]);

    return $student;
}

/** A published platform course: 1 module, a reading and a video lesson. */
function platformCourse(array $attributes = []): Course
{
    $course = Course::create([
        'title' => 'EUEE Mathematics Crash Course',
        'language' => 'en',
        'status' => 'published',
        'published_at' => now(),
        ...$attributes,
    ]);

    $module = $course->modules()->create(['title' => 'Unit 1: Algebra', 'sort_order' => 0]);
    $module->lessons()->create([
        'course_id' => $course->id, 'type' => 'reading', 'title' => 'Linear equations',
        'content' => ['body' => '# Solve for x'], 'sort_order' => 0,
    ]);
    $module->lessons()->create([
        'course_id' => $course->id, 'type' => 'video', 'title' => 'Worked examples',
        'content' => ['url' => 'https://www.youtube.com/watch?v=abc'], 'sort_order' => 1,
    ]);

    return $course;
}

// ───────────────────────── the studio (staff) ─────────────────────────

it('lets platform staff build and publish a platform course', function () {
    $contentAdmin = User::factory()->create();
    grantPlatformRole($contentAdmin, Role::ContentAdmin);

    Sanctum::actingAs($contentAdmin);

    $course = $this->postJson('/api/v1/courses', [
        'platform' => true,
        'title' => 'EUEE Physics 2018',
        'stream' => 'natural',
        'is_sequential' => true,
    ])->assertCreated()->json('data');

    // Publishing an empty course is refused.
    $this->postJson("/api/v1/courses/{$course['id']}/publish")->assertUnprocessable();

    $module = $this->postJson("/api/v1/courses/{$course['id']}/modules", ['title' => 'Mechanics'])
        ->assertCreated()->json('data');

    $this->postJson("/api/v1/course-modules/{$module['id']}/lessons", [
        'type' => 'reading', 'title' => 'Newton laws', 'body' => 'F = ma',
    ])->assertCreated();

    $this->postJson("/api/v1/courses/{$course['id']}/publish")
        ->assertOk()
        ->assertJsonPath('data.status', 'published');

    // A school director has no reach into the platform studio.
    Sanctum::actingAs(directorOf(makeBranch()));
    $this->getJson('/api/v1/courses?platform=1', branchContext(makeBranch('AA-0002')))->assertForbidden();
});

it('lets a teacher build a class course but not touch other classes', function () {
    $branch = makeBranch();
    $year = activeYear($branch);
    $section = courseSection($branch);

    $teacher = memberOf($branch);
    $employee = Employee::create([
        'user_id' => $teacher->id, 'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => 'Alemu', 'father_name' => 'Bekele', 'gender' => 'male',
    ]);
    $class = SubjectAssignment::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'academic_year_id' => $year->id, 'section_id' => $section->id,
        'subject_id' => Subject::where('code', 'MATH')->value('id'),
        'term_id' => $year->terms()->first()->id, 'employee_id' => $employee->id,
        'periods_per_week' => 5,
    ]);

    // Another teacher's class refuses this teacher's course.
    $stranger = memberOf($branch);
    Sanctum::actingAs($stranger);
    $this->postJson('/api/v1/courses', [
        'subject_assignment_id' => $class->id,
        'title' => 'Hijack course',
    ], branchContext($branch))->assertForbidden();

    Sanctum::actingAs($teacher);
    $this->postJson('/api/v1/courses', [
        'subject_assignment_id' => $class->id,
        'title' => 'Maths G7 — self study',
    ], branchContext($branch))->assertCreated();
});

it('fans a multi-section course out to every targeted class and gates publishing on content', function () {
    $branch = makeBranch();
    $year = activeYear($branch);
    $sectionA = courseSection($branch);
    $sectionB = $branch->sections()->create([
        'school_id' => $branch->school_id,
        'grade_level_id' => GradeLevel::where('code', 'G7')->value('id'),
        'name' => 'B',
    ]);

    $teacher = memberOf($branch);
    $employee = Employee::create([
        'user_id' => $teacher->id, 'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => 'Alemu', 'father_name' => 'Bekele', 'gender' => 'male',
    ]);
    $mathId = Subject::where('code', 'MATH')->value('id');
    $termId = $year->terms()->first()->id;
    [$classA, $classB] = collect([$sectionA, $sectionB])->map(fn (Section $s) => SubjectAssignment::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'academic_year_id' => $year->id, 'section_id' => $s->id,
        'subject_id' => $mathId, 'term_id' => $termId,
        'employee_id' => $employee->id, 'periods_per_week' => 5,
    ]))->all();

    Sanctum::actingAs($teacher);

    // Draft autosave shape: title + audience only; anchor = first target.
    $course = $this->postJson('/api/v1/courses', [
        'title' => 'Maths G7 — both sections',
        'subject_id' => $mathId,
        'subject_assignment_ids' => [$classA->id, $classB->id],
    ], branchContext($branch))->assertCreated()->json('data');

    expect($course['subject_assignment_id'])->toBe($classA->id)
        ->and(collect($course['targets'])->pluck('subject_assignment_id')->all())
        ->toBe([$classA->id, $classB->id]);

    // Publishing through the autosave payload is refused while empty…
    $this->putJson("/api/v1/courses/{$course['id']}", ['status' => 'published'], branchContext($branch))
        ->assertUnprocessable();

    // …and goes through once a lesson exists.
    $module = $this->postJson("/api/v1/courses/{$course['id']}/modules", ['title' => 'Unit 1'], branchContext($branch))
        ->assertCreated()->json('data');
    $this->postJson("/api/v1/course-modules/{$module['id']}/lessons", [
        'type' => 'reading', 'title' => 'Notes', 'body' => '<p>Fractions</p>',
    ], branchContext($branch))->assertCreated();
    $this->putJson("/api/v1/courses/{$course['id']}", ['status' => 'published'], branchContext($branch))
        ->assertOk()
        ->assertJsonPath('data.status', 'published');

    // A student in the NON-anchor section reaches the course through /me.
    $studentUser = User::factory()->create();
    $student = Student::create([
        'user_id' => $studentUser->id,
        'first_name' => 'Liya', 'father_name' => 'Mekonnen', 'gender' => 'female',
    ]);
    app(EnrollStudentAction::class)->execute($student, [
        'academic_year_id' => $year->id,
        'section_id' => $sectionB->id,
        'grade_level_id' => $sectionB->grade_level_id,
    ]);
    Sanctum::actingAs($student->user);
    $shelf = $this->getJson('/api/v1/me/courses')->assertOk()->json('data');
    expect(collect($shelf)->pluck('id'))->toContain($course['id']);
});

// ───────────────────────── the learner lane ─────────────────────────

it('opens platform courses to any authenticated user with tracked progress', function () {
    $course = platformCourse();
    $lessons = $course->lessons()->orderBy('sort_order')->get();

    // A no-school B2C user takes the course.
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/me/courses')
        ->assertOk()
        ->assertJsonPath('data.0.id', $course->id)
        ->assertJsonPath('data.0.progress_percent', 0);

    // The tree opens cleanly on a FIRST visit — zero progress rows exist yet.
    $this->getJson("/api/v1/me/courses/{$course->id}")
        ->assertOk()
        ->assertJsonPath('data.progress_percent', 0)
        ->assertJsonPath('data.continue_lesson_id', $lessons[0]->id)
        ->assertJsonPath('data.modules.0.lessons.0.status', null);

    $this->getJson("/api/v1/me/lessons/{$lessons[0]->id}")
        ->assertOk()
        ->assertJsonPath('data.content.body', '# Solve for x');

    $this->postJson("/api/v1/me/lessons/{$lessons[0]->id}/progress", ['status' => 'completed'])
        ->assertOk()
        ->assertJsonPath('data.progress_percent', 50);

    $this->postJson("/api/v1/me/lessons/{$lessons[1]->id}/progress", ['status' => 'completed'])
        ->assertOk()
        ->assertJsonPath('data.progress_percent', 100);
});

it('locks later lessons in a sequential course until earlier ones finish', function () {
    $course = platformCourse(['is_sequential' => true]);
    $lessons = $course->lessons()->orderBy('sort_order')->get();

    Sanctum::actingAs(User::factory()->create());

    // The second lesson is locked before the first completes.
    $this->getJson("/api/v1/me/lessons/{$lessons[1]->id}")->assertStatus(423);
    $this->postJson("/api/v1/me/lessons/{$lessons[1]->id}/progress", ['status' => 'completed'])->assertStatus(423);

    $this->postJson("/api/v1/me/lessons/{$lessons[0]->id}/progress", ['status' => 'completed'])->assertOk();
    $this->getJson("/api/v1/me/lessons/{$lessons[1]->id}")->assertOk();

    // The tree exposes the lock so the UI can draw it.
    $tree = $this->getJson("/api/v1/me/courses/{$course->id}")->assertOk()->json('data');
    expect($tree['modules'][0]['lessons'][1]['is_locked'])->toBeFalse();
});

it('keeps draft and foreign-school courses invisible to learners', function () {
    $draft = platformCourse(['status' => 'draft']);

    $branchA = makeBranch('AA-0001');
    $schoolCourse = Course::create([
        'school_id' => $branchA->school_id, 'branch_id' => $branchA->id,
        'title' => 'School A study skills', 'status' => 'published', 'published_at' => now(),
    ]);
    $module = $schoolCourse->modules()->create(['title' => 'Week 1', 'sort_order' => 0]);
    $module->lessons()->create([
        'course_id' => $schoolCourse->id, 'type' => 'reading', 'title' => 'Notes',
        'content' => ['body' => 'private'], 'sort_order' => 0,
    ]);

    // A no-school user sees neither the draft nor School A's course.
    Sanctum::actingAs(User::factory()->create());
    $shelf = $this->getJson('/api/v1/me/courses')->assertOk()->json('data');
    expect(collect($shelf)->pluck('id'))->not->toContain($draft->id)
        ->and(collect($shelf)->pluck('id'))->not->toContain($schoolCourse->id);

    $this->getJson("/api/v1/me/courses/{$schoolCourse->id}")->assertForbidden();

    // School A's own student DOES see the school course.
    $student = courseStudent($branchA, courseSection($branchA));
    Sanctum::actingAs($student->user);
    $shelf = $this->getJson('/api/v1/me/courses')->assertOk()->json('data');
    expect(collect($shelf)->pluck('id'))->toContain($schoolCourse->id);
});

it('completes a quiz lesson only through a real submitted attempt', function () {
    $contentAdmin = User::factory()->create();
    grantPlatformRole($contentAdmin, Role::ContentAdmin);

    // A platform quiz with one MCQ, wired into a course lesson.
    $bank = QuestionBank::create(['name' => 'Prep pool', 'created_by' => $contentAdmin->id]);
    $question = $bank->questions()->create([
        'type' => 'mcq_single',
        'body' => ['stem' => '2+2?', 'options' => [['id' => 'a', 'text' => '4'], ['id' => 'b', 'text' => '5']]],
        'answer_key' => ['correct' => 'a'],
        'points' => 1,
    ]);
    $quiz = Quiz::create([
        'is_platform' => true, 'kind' => 'mock', 'title' => 'Checkpoint',
        'settings' => ['attempts_allowed' => 0, 'results_policy' => 'immediately'],
        'status' => 'published', 'published_at' => now(),
    ]);
    $quiz->quizQuestions()->create(['question_id' => $question->id, 'sort_order' => 0]);

    $course = platformCourse();
    $module = $course->modules()->first();
    $quizLesson = $module->lessons()->create([
        'course_id' => $course->id, 'type' => 'quiz', 'title' => 'Checkpoint quiz',
        'quiz_id' => $quiz->id, 'sort_order' => 2,
    ]);

    $learner = User::factory()->create();
    Sanctum::actingAs($learner);

    // Claiming completion without sitting the quiz is refused.
    $this->postJson("/api/v1/me/lessons/{$quizLesson->id}/progress", ['status' => 'completed'])
        ->assertUnprocessable();

    // Sit and submit the quiz, then the lesson completes.
    $attempt = $this->postJson("/api/v1/me/exams/{$quiz->id}/start")->assertCreated()->json('data');
    $this->postJson("/api/v1/me/exam-attempts/{$attempt['attempt_id']}/submit")->assertOk();

    $this->postJson("/api/v1/me/lessons/{$quizLesson->id}/progress", ['status' => 'completed'])
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');
});

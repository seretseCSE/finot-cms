<?php

use App\Actions\EnrollStudentAction;
use App\Actions\SaveAcademicYearAction;
use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Branch;
use App\Models\ContinuousAssessment;
use App\Models\Employee;
use App\Models\GradeLevel;
use App\Models\ParentProfile;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentGuardian;
use App\Models\Subject;
use App\Models\SubjectAssignment;
use App\Models\User;
use App\Services\Chat\ConversationAccess;
use App\Support\Ethiopia;
use Database\Seeders\GradeLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SubjectSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

/*
 * THE LMS GUARD RAIL (ADR-016). Asserts the two-lane design end to end:
 * teacher-owned class content, supervisory oversight, the server-authoritative
 * exam engine (deadlines, shuffling, auto-grading, access codes, attempt
 * limits, key stripping), gradebook sync, the /me relationship lane with
 * guardian gates, the open exam-prep lane for no-school users — and hard
 * cross-tenant isolation over all of it.
 */

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(GradeLevelSeeder::class);
    $this->seed(SubjectSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

// ───────────────────────────── fixtures ─────────────────────────────

function lmsYear(Branch $branch): AcademicYear
{
    return (new SaveAcademicYearAction)->execute($branch, ['name' => '2017 E.C.', 'status' => 'active']);
}

function lmsSection(Branch $branch, string $name = 'A', string $gradeCode = 'G7'): Section
{
    return $branch->sections()->create([
        'school_id' => $branch->school_id,
        'grade_level_id' => GradeLevel::where('code', $gradeCode)->value('id'),
        'name' => $name,
    ]);
}

/** @return array{0: User, 1: Employee} */
function lmsTeacher(Branch $branch): array
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

function lmsClass(Branch $branch, AcademicYear $year, Section $section, ?Employee $teacher): SubjectAssignment
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

/** A student WITH a portal account, actively enrolled in the section. */
function lmsStudent(Branch $branch, AcademicYear $year, Section $section, string $name = 'Sara'): Student
{
    $user = User::factory()->create();

    $student = Student::create([
        'user_id' => $user->id,
        'first_name' => $name,
        'father_name' => 'Tesfaye',
        'gender' => 'female',
    ]);

    app(EnrollStudentAction::class)->execute($student, [
        'academic_year_id' => $year->id,
        'section_id' => $section->id,
        'grade_level_id' => $section->grade_level_id,
    ]);

    return $student;
}

function lmsBank(Branch $branch, ?User $creator = null): QuestionBank
{
    return QuestionBank::create([
        'school_id' => $branch->school_id,
        'branch_id' => $branch->id,
        'name' => 'Math Unit 1',
        'created_by' => $creator?->id,
    ]);
}

/** Four auto-gradable questions worth 1+2+2+1 = 6 points. */
function lmsQuestions(QuestionBank $bank): array
{
    $mcq = $bank->questions()->create([
        'type' => 'mcq_single',
        'body' => ['stem' => '2 + 2 = ?', 'options' => [
            ['id' => 'a', 'text' => '3'], ['id' => 'b', 'text' => '4'], ['id' => 'c', 'text' => '5'],
        ]],
        'answer_key' => ['correct' => 'b'],
        'points' => 1,
    ]);

    $multi = $bank->questions()->create([
        'type' => 'mcq_multi',
        'body' => ['stem' => 'Pick the even numbers', 'options' => [
            ['id' => 'a', 'text' => '2'], ['id' => 'b', 'text' => '3'], ['id' => 'c', 'text' => '4'],
        ]],
        'answer_key' => ['correct' => ['a', 'c']],
        'points' => 2,
    ]);

    $numeric = $bank->questions()->create([
        'type' => 'numeric',
        'body' => ['stem' => 'What is 10 / 4?'],
        'answer_key' => ['value' => 2.5, 'tolerance' => 0.01],
        'points' => 2,
    ]);

    $tf = $bank->questions()->create([
        'type' => 'true_false',
        'body' => ['stem' => 'A triangle has 3 sides.'],
        'answer_key' => ['correct' => true],
        'points' => 1,
    ]);

    return [$mcq, $multi, $numeric, $tf];
}

/** A published fixed-paper quiz on the teacher's class. */
function lmsQuiz(SubjectAssignment $class, array $questions, array $settings = [], array $extra = []): Quiz
{
    $quiz = Quiz::create([
        'school_id' => $class->school_id,
        'branch_id' => $class->branch_id,
        'subject_assignment_id' => $class->id,
        'kind' => 'quiz',
        'title' => 'Unit 1 Quiz',
        'settings' => ['attempts_allowed' => 1, 'results_policy' => 'immediately', ...$settings],
        'status' => 'published',
        'published_at' => now(),
        ...$extra,
    ]);

    $quiz->targets()->create(['subject_assignment_id' => $class->id]);

    foreach (array_values($questions) as $i => $question) {
        $quiz->quizQuestions()->create(['question_id' => $question->id, 'sort_order' => $i]);
    }

    return $quiz;
}

/** Answers that earn full marks on the lmsQuestions() paper. */
function lmsPerfectAnswers(array $questions): array
{
    [$mcq, $multi, $numeric, $tf] = $questions;

    return [
        $mcq->id => 'b',
        $multi->id => ['a', 'c'],
        $numeric->id => 2.5,
        $tf->id => true,
    ];
}

function lmsGuardian(Student $student, bool $canViewGrades = true): User
{
    $user = User::factory()->create();
    $parent = ParentProfile::create([
        'user_id' => $user->id,
        'first_name' => 'Worknesh',
        'father_name' => 'Abebe',
        'gender' => 'female',
    ]);

    StudentGuardian::create([
        'student_id' => $student->id,
        'parent_id' => $parent->id,
        'relationship' => 'mother',
        'can_view_grades' => $canViewGrades,
        'can_view_attendance' => true,
        'can_pay_fees' => true,
        'is_primary' => true,
        'is_active' => true,
    ]);

    return $user;
}

// ───────────────────── question banks & questions ─────────────────────

it('lets a teacher create their own bank and author typed questions', function () {
    $branch = makeBranch();
    [$teacher, $employee] = lmsTeacher($branch);
    $year = lmsYear($branch);
    $class = lmsClass($branch, $year, lmsSection($branch), $employee);

    Sanctum::actingAs($teacher);

    $bank = $this->postJson('/api/v1/question-banks', [
        'name' => 'My Bank',
        'subject_id' => $class->subject_id,
        'grade_level_id' => GradeLevel::where('code', 'G7')->value('id'),
        'topics' => ['Chapter 1', 'Chapter 2'],
    ], branchContext($branch))
        ->assertCreated()
        ->json('data');

    $this->postJson("/api/v1/question-banks/{$bank['id']}/questions", [
        'type' => 'mcq_single',
        'body' => ['stem' => 'Capital of Ethiopia?', 'options' => [
            ['id' => 'a', 'text' => 'Addis Ababa'], ['id' => 'b', 'text' => 'Bahir Dar'],
        ]],
        'answer_key' => ['correct' => 'a'],
    ], branchContext($branch))->assertCreated();

    $this->getJson("/api/v1/question-banks/{$bank['id']}/questions", branchContext($branch))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('files question topics into the bank, sanitizes rich stems and keeps drafts', function () {
    $branch = makeBranch();
    [$teacher] = lmsTeacher($branch);
    $bank = lmsBank($branch, $teacher);
    $bank->update(['topics' => ['Algebra']]);

    Sanctum::actingAs($teacher);

    $question = $this->postJson("/api/v1/question-banks/{$bank->id}/questions", [
        'type' => 'true_false',
        'body' => [
            'stem' => '<h2>Prime?</h2><p>Is <b>2</b> prime? '
                .'See <a href="https://example.org" onclick="evil()">this</a>.</p>'
                .'<script>alert(1)</script><img src="https://x.example/pic.png" onerror="alert(1)">'
                .'<div data-video="youtube:dQw4w9WgXcQ" class="evil" onclick="x()"></div>'
                .'<div data-video="javascript:alert(1)"></div>',
            'attachments' => [['kind' => 'link', 'url' => 'https://example.org/ref', 'name' => 'Reference']],
        ],
        'answer_key' => ['correct' => true],
        'topic' => 'Geometry',
        'status' => 'draft',
    ], branchContext($branch))
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.topic', 'Geometry')
        ->json('data');

    expect($question['body']['stem'])
        ->not->toContain('<script>')
        ->not->toContain('onerror')
        ->not->toContain('onclick')
        ->not->toContain('class="evil"')
        ->toContain('<b>2</b>')
        ->toContain('<h2>Prime?</h2>')
        ->toContain('href="https://example.org"')
        // A valid video marker survives, a script-bearing one is neutralised.
        ->toContain('<div data-video="youtube:dQw4w9WgXcQ">')
        ->not->toContain('javascript:alert(1)');

    // The new topic joins the bank's chapter list, once.
    expect($bank->fresh()->topics)->toBe(['Algebra', 'Geometry']);

    // Malformed attachments never enter the pool.
    $this->postJson("/api/v1/question-banks/{$bank->id}/questions", [
        'type' => 'true_false',
        'body' => ['stem' => 'Bad link?', 'attachments' => [['kind' => 'link', 'url' => 'javascript:alert(1)']]],
        'answer_key' => ['correct' => false],
    ], branchContext($branch))->assertUnprocessable();
});

it('rejects malformed questions (correct answer not among options)', function () {
    $branch = makeBranch();
    [$teacher] = lmsTeacher($branch);
    $bank = lmsBank($branch, $teacher);

    Sanctum::actingAs($teacher);

    $this->postJson("/api/v1/question-banks/{$bank->id}/questions", [
        'type' => 'mcq_single',
        'body' => ['stem' => 'Broken?', 'options' => [
            ['id' => 'a', 'text' => 'One'], ['id' => 'b', 'text' => 'Two'],
        ]],
        'answer_key' => ['correct' => 'z'],
    ], branchContext($branch))->assertUnprocessable();
});

it('toggles a question between draft and published from the bank, behind the update policy', function () {
    $branch = makeBranch();
    [$teacher] = lmsTeacher($branch);
    $bank = lmsBank($branch, $teacher);
    $question = $bank->questions()->create([
        'type' => 'true_false',
        'body' => ['stem' => 'A triangle has 3 sides.'],
        'answer_key' => ['correct' => true],
        'points' => 1,
        'status' => 'draft',
        'created_by' => $teacher->id,
    ]);

    Sanctum::actingAs($teacher);

    $this->patchJson("/api/v1/questions/{$question->id}/status", ['status' => 'published'], branchContext($branch))
        ->assertOk()
        ->assertJsonPath('data.status', 'published');

    $this->patchJson("/api/v1/questions/{$question->id}/status", ['status' => 'draft'], branchContext($branch))
        ->assertOk()
        ->assertJsonPath('data.status', 'draft');

    // Retiring is not a toggle target — only draft/published.
    $this->patchJson("/api/v1/questions/{$question->id}/status", ['status' => 'retired'], branchContext($branch))
        ->assertUnprocessable();

    // A colleague who did not author it cannot flip its status.
    [$other] = lmsTeacher($branch);
    Sanctum::actingAs($other);
    $this->patchJson("/api/v1/questions/{$question->id}/status", ['status' => 'published'], branchContext($branch))
        ->assertForbidden();

    expect($question->fresh()->status)->toBe('draft');
});

it('names still-draft questions when a full-looking exam refuses to publish', function () {
    $branch = makeBranch();
    $year = lmsYear($branch);
    $section = lmsSection($branch);
    [$teacher, $employee] = lmsTeacher($branch);
    $class = lmsClass($branch, $year, $section, $employee);
    $bank = lmsBank($branch, $teacher);

    $q1 = $bank->questions()->create(['type' => 'true_false', 'body' => ['stem' => 'A'], 'answer_key' => ['correct' => true], 'points' => 1, 'status' => 'draft', 'created_by' => $teacher->id]);
    $q2 = $bank->questions()->create(['type' => 'true_false', 'body' => ['stem' => 'B'], 'answer_key' => ['correct' => false], 'points' => 1, 'status' => 'draft', 'created_by' => $teacher->id]);
    $quiz = lmsQuiz($class, [$q1, $q2], extra: ['status' => 'draft', 'published_at' => null, 'created_by' => $teacher->id]);

    Sanctum::actingAs($teacher);

    // The paper is full, but every question is a draft — the message says so.
    $this->postJson("/api/v1/quizzes/{$quiz->id}/publish", [], branchContext($branch))
        ->assertUnprocessable()
        ->assertJsonPath('errors.questions.0', '2 questions on this paper are still drafts — publish them in their question bank, then publish the exam.');

    // Publishing the questions clears the block.
    $q1->update(['status' => 'published']);
    $q2->update(['status' => 'published']);
    $this->postJson("/api/v1/quizzes/{$quiz->id}/publish", [], branchContext($branch))
        ->assertOk()
        ->assertJsonPath('data.status', 'published');
});

it('blocks a teacher from managing a bank they did not create, while a director may', function () {
    $branch = makeBranch();
    [$owner] = lmsTeacher($branch);
    [$other] = lmsTeacher($branch);
    $bank = lmsBank($branch, $owner);

    Sanctum::actingAs($other);
    $this->putJson("/api/v1/question-banks/{$bank->id}", ['name' => 'Hijack'], branchContext($branch))
        ->assertForbidden();

    Sanctum::actingAs(directorOf($branch));
    $this->putJson("/api/v1/question-banks/{$bank->id}", ['name' => 'Renamed'], branchContext($branch))
        ->assertOk();
});

it('never leaks question banks across schools', function () {
    $branchA = makeBranch('AA-0001');
    $branchB = makeBranch('AA-0002');
    [$teacherA] = lmsTeacher($branchA);
    $bankA = lmsBank($branchA, $teacherA);

    Sanctum::actingAs(directorOf($branchB));

    $this->getJson("/api/v1/question-banks/{$bankA->id}/questions", branchContext($branchB))
        ->assertForbidden();

    $this->getJson('/api/v1/question-banks', branchContext($branchB))
        ->assertOk()
        ->assertJsonMissing(['id' => $bankA->id]);
});

it('browses questions across several banks in one request, silently dropping banks the caller cannot view', function () {
    $branch = makeBranch('AA-0001');
    $otherBranch = makeBranch('AA-0002');
    [$teacher] = lmsTeacher($branch);
    [$otherTeacher] = lmsTeacher($otherBranch);

    $bankOne = lmsBank($branch, $teacher);
    $bankTwo = lmsBank($branch, $teacher);
    lmsQuestions($bankOne);
    $bankTwo->questions()->create([
        'type' => 'true_false',
        'body' => ['stem' => 'Bank two question?'],
        'answer_key' => ['correct' => true],
        'points' => 1,
    ]);
    $foreignBank = lmsBank($otherBranch, $otherTeacher);
    $foreignBank->questions()->create([
        'type' => 'true_false',
        'body' => ['stem' => 'Should never be visible.'],
        'answer_key' => ['correct' => true],
        'points' => 1,
    ]);

    Sanctum::actingAs($teacher);

    $this->getJson(
        "/api/v1/questions?question_bank_id[]={$bankOne->id}&question_bank_id[]={$bankTwo->id}&question_bank_id[]={$foreignBank->id}",
        branchContext($branch),
    )
        ->assertOk()
        ->assertJsonCount(5, 'data')
        ->assertJsonMissing(['body' => ['stem' => 'Should never be visible.']]);
});

it('reserves platform banks for exam_prep.manage holders', function () {
    $branch = makeBranch();

    Sanctum::actingAs(directorOf($branch));
    $this->getJson('/api/v1/question-banks?platform=1', branchContext($branch))
        ->assertForbidden();

    $contentAdmin = User::factory()->create();
    grantPlatformRole($contentAdmin, Role::ContentAdmin);

    Sanctum::actingAs($contentAdmin);
    $this->postJson('/api/v1/question-banks', [
        'name' => 'EUEE Maths 2016',
        'platform' => true,
        'subject_id' => Subject::where('code', 'MATH')->value('id'),
        'grade_level_id' => GradeLevel::where('code', 'G12')->value('id'),
    ])
        ->assertCreated()
        ->assertJsonPath('data.is_platform', true);
});

// ───────────────────────── the exam engine ─────────────────────────

it('runs a full exam sitting: start → autosave → submit → auto-graded score', function () {
    $branch = makeBranch();
    $year = lmsYear($branch);
    $section = lmsSection($branch);
    [$teacher, $employee] = lmsTeacher($branch);
    $class = lmsClass($branch, $year, $section, $employee);
    $questions = lmsQuestions(lmsBank($branch, $teacher));
    $quiz = lmsQuiz($class, $questions);
    $student = lmsStudent($branch, $year, $section);

    Sanctum::actingAs($student->user);

    $attempt = $this->postJson("/api/v1/me/exams/{$quiz->id}/start")
        ->assertCreated()
        ->json('data');

    expect($attempt['question_count'])->toBe(4)
        ->and((float) $attempt['max_score'])->toBe(6.0);

    // The paper never contains answer keys.
    $paper = $this->getJson("/api/v1/me/exam-attempts/{$attempt['attempt_id']}")
        ->assertOk()
        ->json('data');
    expect(json_encode($paper))->not->toContain('answer_key');

    // Answer: 3 perfect, 1 partially right (one of two even numbers).
    [$mcq, $multi, $numeric, $tf] = $questions;
    foreach ([[$mcq->id, 'b'], [$multi->id, ['a']], [$numeric->id, 2.5], [$tf->id, true]] as [$qid, $answer]) {
        $this->postJson("/api/v1/me/exam-attempts/{$attempt['attempt_id']}/answer", [
            'question_id' => $qid,
            'answer' => $answer,
        ])->assertOk();
    }

    $result = $this->postJson("/api/v1/me/exam-attempts/{$attempt['attempt_id']}/submit")
        ->assertOk()
        ->json('data');

    // 1 (mcq) + 1 (half the multi) + 2 (numeric) + 1 (tf) = 5 of 6.
    expect($result['status'])->toBe('graded');

    $detail = $this->getJson("/api/v1/me/exam-attempts/{$attempt['attempt_id']}/result")
        ->assertOk()
        ->json('data');

    expect((float) $detail['score'])->toBe(5.0)
        ->and((float) $detail['max_score'])->toBe(6.0);
});

it('reports a completed quiz-kind assignment as turned in with its score', function () {
    $branch = makeBranch();
    $year = lmsYear($branch);
    $section = lmsSection($branch);
    [$teacher, $employee] = lmsTeacher($branch);
    $class = lmsClass($branch, $year, $section, $employee);
    $questions = lmsQuestions(lmsBank($branch, $teacher));
    $quiz = lmsQuiz($class, $questions); // attempts_allowed 1, results immediately
    $student = lmsStudent($branch, $year, $section);

    // A quiz-kind assignment: the quiz IS the work — no AssignmentSubmission.
    $assignment = Assignment::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'subject_assignment_id' => $class->id,
        'kind' => 'quiz', 'quiz_id' => $quiz->id,
        'title' => 'Quiz homework', 'submission_types' => [],
        'status' => 'published', 'published_at' => now(),
    ]);

    Sanctum::actingAs($student->user);

    // Before any attempt: no submission and no quiz progress → still to-do.
    $before = $this->getJson("/api/v1/me/lms/assignments/{$assignment->id}")->assertOk()->json('data');
    expect($before['submission'])->toBeNull()
        ->and($before['quiz_progress'])->toBeNull();

    // Completing the quiz is the ONLY way to turn in a quiz-kind assignment.
    $start = $this->postJson("/api/v1/me/exams/{$quiz->id}/start")->assertCreated()->json('data');
    foreach (lmsPerfectAnswers($questions) as $qid => $answer) {
        $this->postJson("/api/v1/me/exam-attempts/{$start['attempt_id']}/answer", [
            'question_id' => $qid, 'answer' => $answer,
        ])->assertOk();
    }
    $this->postJson("/api/v1/me/exam-attempts/{$start['attempt_id']}/submit")->assertOk();

    // The detail lane now reads as turned in: graded, scored, one attempt used.
    $progress = $this->getJson("/api/v1/me/lms/assignments/{$assignment->id}")
        ->assertOk()->json('data.quiz_progress');
    expect($progress)->not->toBeNull()
        ->and($progress['status'])->toBe('graded')
        ->and((float) $progress['score'])->toBe(6.0)
        ->and($progress['attempts_used'])->toBe(1);

    // The list + Today feed carry the same signal (so the feed can drop it).
    $listed = collect($this->getJson('/api/v1/me/lms/assignments')->assertOk()->json('data'))
        ->firstWhere('id', $assignment->id);
    expect($listed['quiz_progress']['status'])->toBe('graded');

    $overview = collect($this->getJson('/api/v1/me/lms/overview')->assertOk()->json('data.assignments'))
        ->firstWhere('id', $assignment->id);
    expect($overview['quiz_progress']['status'])->toBe('graded');

    // Teacher side: the assignment bridges to quiz completion (one of one
    // enrolled student done) instead of showing an empty submissions queue.
    Sanctum::actingAs($teacher);
    $this->getJson("/api/v1/assignments/{$assignment->id}", branchContext($branch))
        ->assertOk()
        ->assertJsonPath('data.kind', 'quiz')
        ->assertJsonPath('data.quiz_stats.takers_count', 1)
        ->assertJsonPath('data.quiz_stats.expected_takers', 1);
});

it('hides a quiz-kind score until the results policy releases it', function () {
    $branch = makeBranch();
    $year = lmsYear($branch);
    $section = lmsSection($branch);
    [$teacher, $employee] = lmsTeacher($branch);
    $class = lmsClass($branch, $year, $section, $employee);
    $questions = lmsQuestions(lmsBank($branch, $teacher));
    $quiz = lmsQuiz($class, $questions, ['results_policy' => 'manual']);
    $student = lmsStudent($branch, $year, $section);

    $assignment = Assignment::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'subject_assignment_id' => $class->id,
        'kind' => 'quiz', 'quiz_id' => $quiz->id,
        'title' => 'Quiz homework', 'submission_types' => [],
        'status' => 'published', 'published_at' => now(),
    ]);

    Sanctum::actingAs($student->user);
    $start = $this->postJson("/api/v1/me/exams/{$quiz->id}/start")->assertCreated()->json('data');
    foreach (lmsPerfectAnswers($questions) as $qid => $answer) {
        $this->postJson("/api/v1/me/exam-attempts/{$start['attempt_id']}/answer", [
            'question_id' => $qid, 'answer' => $answer,
        ])->assertOk();
    }
    $this->postJson("/api/v1/me/exam-attempts/{$start['attempt_id']}/submit")->assertOk();

    // Turned in (graded), but the score stays hidden until results are released.
    $progress = $this->getJson("/api/v1/me/lms/assignments/{$assignment->id}")
        ->assertOk()->json('data.quiz_progress');
    expect($progress['status'])->toBe('graded')
        ->and($progress['score'])->toBeNull();
});

it('exposes the finished attempt on the exam list so the card can link its result', function () {
    $branch = makeBranch();
    $year = lmsYear($branch);
    $section = lmsSection($branch);
    [$teacher, $employee] = lmsTeacher($branch);
    $class = lmsClass($branch, $year, $section, $employee);
    $questions = lmsQuestions(lmsBank($branch, $teacher));
    $quiz = lmsQuiz($class, $questions); // attempts_allowed: 1, results immediately
    $student = lmsStudent($branch, $year, $section);

    Sanctum::actingAs($student->user);

    // Before sitting: no finished attempt to review, but it can be started.
    $before = collect($this->getJson('/api/v1/me/lms/exams')->assertOk()->json('data'))
        ->firstWhere('id', $quiz->id);
    expect($before['result_attempt_id'])->toBeNull()
        ->and($before['can_start'])->toBeTrue();

    $start = $this->postJson("/api/v1/me/exams/{$quiz->id}/start")->assertCreated()->json('data');
    foreach (lmsPerfectAnswers($questions) as $qid => $answer) {
        $this->postJson("/api/v1/me/exam-attempts/{$start['attempt_id']}/answer", [
            'question_id' => $qid, 'answer' => $answer,
        ])->assertOk();
    }
    $this->postJson("/api/v1/me/exam-attempts/{$start['attempt_id']}/submit")->assertOk();

    // After sitting: the card can now point at the finished attempt and shows
    // the visible score; the quota is spent so it no longer offers a start.
    $after = collect($this->getJson('/api/v1/me/lms/exams')->assertOk()->json('data'))
        ->firstWhere('id', $quiz->id);
    expect($after['result_attempt_id'])->toBe($start['attempt_id'])
        ->and($after['can_start'])->toBeFalse()
        ->and($after['best_score'])->not->toBeNull();
});

it('withholds the exam-list score until the results policy releases it', function () {
    $branch = makeBranch();
    $year = lmsYear($branch);
    $section = lmsSection($branch);
    [$teacher, $employee] = lmsTeacher($branch);
    $class = lmsClass($branch, $year, $section, $employee);
    $questions = lmsQuestions(lmsBank($branch, $teacher));
    $quiz = lmsQuiz($class, $questions, ['results_policy' => 'manual']);
    $student = lmsStudent($branch, $year, $section);

    Sanctum::actingAs($student->user);
    $start = $this->postJson("/api/v1/me/exams/{$quiz->id}/start")->assertCreated()->json('data');
    $this->postJson("/api/v1/me/exam-attempts/{$start['attempt_id']}/submit")->assertOk();

    // The attempt is linkable (so they can open the "results pending" screen),
    // but the score stays hidden until the teacher releases it.
    $row = collect($this->getJson('/api/v1/me/lms/exams')->assertOk()->json('data'))
        ->firstWhere('id', $quiz->id);
    expect($row['result_attempt_id'])->toBe($start['attempt_id'])
        ->and($row['best_score'])->toBeNull();
});

it('lets a student re-sit after their attempt is invalidated', function () {
    $branch = makeBranch();
    $year = lmsYear($branch);
    $section = lmsSection($branch);
    [$teacher, $employee] = lmsTeacher($branch);
    $class = lmsClass($branch, $year, $section, $employee);
    $quiz = lmsQuiz($class, lmsQuestions(lmsBank($branch, $teacher))); // attempts_allowed: 1
    $student = lmsStudent($branch, $year, $section);

    Sanctum::actingAs($student->user);
    $first = $this->postJson("/api/v1/me/exams/{$quiz->id}/start")->assertCreated()->json('data');
    $this->postJson("/api/v1/me/exam-attempts/{$first['attempt_id']}/submit")->assertOk();

    // Quota used — a fresh start is refused.
    $this->postJson("/api/v1/me/exams/{$quiz->id}/start")->assertUnprocessable();

    // Staff invalidate the sitting: the seat comes back, the row stays.
    Sanctum::actingAs($teacher);
    $this->postJson("/api/v1/quizzes/{$quiz->id}/attempts/{$first['attempt_id']}/invalidate", [], branchContext($branch))
        ->assertOk();

    // The re-sit continues the numbering past the invalidated row instead
    // of colliding with its unique (quiz, user, attempt_number) slot.
    Sanctum::actingAs($student->user);
    $second = $this->postJson("/api/v1/me/exams/{$quiz->id}/start")->assertCreated()->json('data');

    expect(QuizAttempt::findOrFail($second['attempt_id'])->attempt_number)->toBe(2);
});

it('refuses takers who are not enrolled in the class', function () {
    $branch = makeBranch();
    $year = lmsYear($branch);
    $section = lmsSection($branch, 'A');
    $otherSection = lmsSection($branch, 'B');
    [$teacher, $employee] = lmsTeacher($branch);
    $class = lmsClass($branch, $year, $section, $employee);
    $quiz = lmsQuiz($class, lmsQuestions(lmsBank($branch, $teacher)));

    $outsider = lmsStudent($branch, $year, $otherSection);

    Sanctum::actingAs($outsider->user);
    $this->postJson("/api/v1/me/exams/{$quiz->id}/start")->assertUnprocessable();
});

it('enforces the attempt limit but resumes a live sitting', function () {
    $branch = makeBranch();
    $year = lmsYear($branch);
    $section = lmsSection($branch);
    [$teacher, $employee] = lmsTeacher($branch);
    $class = lmsClass($branch, $year, $section, $employee);
    $questions = lmsQuestions(lmsBank($branch, $teacher));
    $quiz = lmsQuiz($class, $questions);
    $student = lmsStudent($branch, $year, $section);

    Sanctum::actingAs($student->user);

    $first = $this->postJson("/api/v1/me/exams/{$quiz->id}/start")->assertCreated()->json('data.attempt_id');

    // Starting again mid-sitting RESUMES the same attempt.
    $resumed = $this->postJson("/api/v1/me/exams/{$quiz->id}/start")->assertCreated()->json('data.attempt_id');
    expect($resumed)->toBe($first);

    $this->postJson("/api/v1/me/exam-attempts/{$first}/submit")->assertOk();

    // attempts_allowed = 1 → a fresh start is refused.
    $this->postJson("/api/v1/me/exams/{$quiz->id}/start")->assertUnprocessable();
});

it('guards supervised exams with an access code', function () {
    $branch = makeBranch();
    $year = lmsYear($branch);
    $section = lmsSection($branch);
    [$teacher, $employee] = lmsTeacher($branch);
    $class = lmsClass($branch, $year, $section, $employee);
    $quiz = lmsQuiz($class, lmsQuestions(lmsBank($branch, $teacher)), extra: [
        'kind' => 'exam',
    ]);
    $quiz->forceFill(['access_code_hash' => Hash::make('ROOM7')])->save();
    $student = lmsStudent($branch, $year, $section);

    Sanctum::actingAs($student->user);

    $this->postJson("/api/v1/me/exams/{$quiz->id}/start", ['access_code' => 'WRONG'])->assertUnprocessable();
    $this->postJson("/api/v1/me/exams/{$quiz->id}/start", ['access_code' => 'ROOM7'])->assertCreated();
});

it('auto-submits when the clock runs out and refuses late answers', function () {
    $branch = makeBranch();
    $year = lmsYear($branch);
    $section = lmsSection($branch);
    [$teacher, $employee] = lmsTeacher($branch);
    $class = lmsClass($branch, $year, $section, $employee);
    $questions = lmsQuestions(lmsBank($branch, $teacher));
    $quiz = lmsQuiz($class, $questions, settings: ['duration_minutes' => 10]);
    $student = lmsStudent($branch, $year, $section);

    Sanctum::actingAs($student->user);
    $attempt = $this->postJson("/api/v1/me/exams/{$quiz->id}/start")->assertCreated()->json('data');

    [$mcq] = $questions;
    $this->postJson("/api/v1/me/exam-attempts/{$attempt['attempt_id']}/answer", [
        'question_id' => $mcq->id, 'answer' => 'b',
    ])->assertOk();

    $this->travel(11)->minutes();

    $this->postJson("/api/v1/me/exam-attempts/{$attempt['attempt_id']}/answer", [
        'question_id' => $mcq->id, 'answer' => 'a',
    ])->assertUnprocessable();

    // The sweep finalized the attempt with the answers it had.
    $detail = $this->getJson("/api/v1/me/exam-attempts/{$attempt['attempt_id']}/result")
        ->assertOk()
        ->json('data');

    expect($detail['status'])->toBe('graded')
        ->and((float) $detail['score'])->toBe(1.0);
});

it('parks essays for manual grading, then graduates on teacher scores', function () {
    $branch = makeBranch();
    $year = lmsYear($branch);
    $section = lmsSection($branch);
    [$teacher, $employee] = lmsTeacher($branch);
    $class = lmsClass($branch, $year, $section, $employee);
    $bank = lmsBank($branch, $teacher);
    $essay = $bank->questions()->create([
        'type' => 'essay',
        'body' => ['stem' => 'Explain photosynthesis.'],
        'points' => 5,
    ]);
    $quiz = lmsQuiz($class, [$essay]);
    $student = lmsStudent($branch, $year, $section);

    Sanctum::actingAs($student->user);
    $attempt = $this->postJson("/api/v1/me/exams/{$quiz->id}/start")->assertCreated()->json('data');
    $this->postJson("/api/v1/me/exam-attempts/{$attempt['attempt_id']}/answer", [
        'question_id' => $essay->id, 'answer' => 'Plants convert light into energy…',
    ])->assertOk();
    $submitted = $this->postJson("/api/v1/me/exam-attempts/{$attempt['attempt_id']}/submit")->assertOk()->json('data');

    expect($submitted['status'])->toBe('submitted');

    // Teacher grades the essay.
    Sanctum::actingAs($teacher);
    $this->postJson("/api/v1/quizzes/{$quiz->id}/attempts/{$attempt['attempt_id']}/grade", [
        'answers' => [['question_id' => $essay->id, 'manual_score' => 4, 'feedback' => 'Good detail.']],
    ], branchContext($branch))->assertOk()->assertJsonPath('data.status', 'graded');

    Sanctum::actingAs($student->user);
    $detail = $this->getJson("/api/v1/me/exam-attempts/{$attempt['attempt_id']}/result")->assertOk()->json('data');
    expect((float) $detail['score'])->toBe(4.0);
});

it('withholds results until close under the after_close policy', function () {
    $branch = makeBranch();
    $year = lmsYear($branch);
    $section = lmsSection($branch);
    [$teacher, $employee] = lmsTeacher($branch);
    $class = lmsClass($branch, $year, $section, $employee);
    $questions = lmsQuestions(lmsBank($branch, $teacher));
    $quiz = lmsQuiz($class, $questions, settings: ['results_policy' => 'after_close']);
    $student = lmsStudent($branch, $year, $section);

    Sanctum::actingAs($student->user);
    $attempt = $this->postJson("/api/v1/me/exams/{$quiz->id}/start")->assertCreated()->json('data');
    $this->postJson("/api/v1/me/exam-attempts/{$attempt['attempt_id']}/submit")->assertOk();

    $this->getJson("/api/v1/me/exam-attempts/{$attempt['attempt_id']}/result")
        ->assertOk()
        ->assertJsonPath('data.visible', false);

    Sanctum::actingAs($teacher);
    $this->postJson("/api/v1/quizzes/{$quiz->id}/close", [], branchContext($branch))->assertOk();

    Sanctum::actingAs($student->user);
    $this->getJson("/api/v1/me/exam-attempts/{$attempt['attempt_id']}/result")
        ->assertOk()
        ->assertJsonPath('data.visible', true);
});

it('freezes quiz structure once someone has taken it', function () {
    $branch = makeBranch();
    $year = lmsYear($branch);
    $section = lmsSection($branch);
    [$teacher, $employee] = lmsTeacher($branch);
    $class = lmsClass($branch, $year, $section, $employee);
    $questions = lmsQuestions(lmsBank($branch, $teacher));
    $quiz = lmsQuiz($class, $questions, extra: ['created_by' => $teacher->id]);
    $student = lmsStudent($branch, $year, $section);

    Sanctum::actingAs($student->user);
    $this->postJson("/api/v1/me/exams/{$quiz->id}/start")->assertCreated();

    Sanctum::actingAs($teacher);
    $this->putJson("/api/v1/quizzes/{$quiz->id}", [
        'questions' => [['question_id' => $questions[0]->id]],
    ], branchContext($branch))->assertUnprocessable();
});

it('logs integrity events as flags for review, never auto-failing', function () {
    $branch = makeBranch();
    $year = lmsYear($branch);
    $section = lmsSection($branch);
    [$teacher, $employee] = lmsTeacher($branch);
    $class = lmsClass($branch, $year, $section, $employee);
    $quiz = lmsQuiz($class, lmsQuestions(lmsBank($branch, $teacher)));
    $student = lmsStudent($branch, $year, $section);

    Sanctum::actingAs($student->user);
    $attempt = $this->postJson("/api/v1/me/exams/{$quiz->id}/start")->assertCreated()->json('data');

    $this->postJson("/api/v1/me/exam-attempts/{$attempt['attempt_id']}/events", ['type' => 'blur'])->assertOk();
    $this->postJson("/api/v1/me/exam-attempts/{$attempt['attempt_id']}/events", ['type' => 'paste'])->assertOk();

    Sanctum::actingAs($teacher);
    $monitor = $this->getJson("/api/v1/quizzes/{$quiz->id}/attempts", branchContext($branch))
        ->assertOk()
        ->json('data.0');

    expect($monitor['flag_count'])->toBe(2)
        ->and($monitor['status'])->toBe('in_progress');
});

// ───────────────────── gradebook integration ─────────────────────

it('pushes graded quiz scores into the linked assessment slot, rescaled', function () {
    $branch = makeBranch();
    $year = lmsYear($branch);
    $section = lmsSection($branch);
    [$teacher, $employee] = lmsTeacher($branch);
    $class = lmsClass($branch, $year, $section, $employee);
    $questions = lmsQuestions(lmsBank($branch, $teacher));

    // The gradebook slot: Quiz 1, out of 10.
    $slot = Assessment::create([
        'subject_assignment_id' => $class->id,
        'type' => 'quiz',
        'name' => 'Quiz 1',
        'max_score' => 10,
        'weight' => 10,
    ]);

    $quiz = lmsQuiz($class, $questions, extra: ['assessment_id' => $slot->id]);
    $student = lmsStudent($branch, $year, $section);

    Sanctum::actingAs($student->user);
    $attempt = $this->postJson("/api/v1/me/exams/{$quiz->id}/start")->assertCreated()->json('data');
    [$mcq, $multi, $numeric, $tf] = $questions;
    foreach (lmsPerfectAnswers($questions) as $qid => $answer) {
        $this->postJson("/api/v1/me/exam-attempts/{$attempt['attempt_id']}/answer", [
            'question_id' => $qid, 'answer' => $answer,
        ])->assertOk();
    }
    $this->postJson("/api/v1/me/exam-attempts/{$attempt['attempt_id']}/submit")->assertOk();

    // 6/6 → 10/10 in the slot.
    $this->assertDatabaseHas('assessment_results', [
        'assessment_id' => $slot->id,
        'student_id' => $student->id,
        'score' => '10.00',
    ]);
});

// ───────────────────────── assignments ─────────────────────────

it('runs the homework loop: publish → submit → grade → parent sees per link flags', function () {
    $branch = makeBranch();
    $year = lmsYear($branch);
    $section = lmsSection($branch);
    [$teacher, $employee] = lmsTeacher($branch);
    $class = lmsClass($branch, $year, $section, $employee);
    $student = lmsStudent($branch, $year, $section);

    Sanctum::actingAs($teacher);
    $assignment = $this->postJson('/api/v1/assignments', [
        'subject_assignment_id' => $class->id,
        'title' => 'Exercise 3.1',
        'submission_types' => ['text'],
        'max_score' => 10,
        'due_at' => now()->addDay()->toIso8601String(),
        'publish' => true,
    ], branchContext($branch))->assertCreated()->json('data');

    // Student sees + submits.
    Sanctum::actingAs($student->user);
    $this->getJson('/api/v1/me/lms/assignments')
        ->assertOk()
        ->assertJsonPath('data.0.title', 'Exercise 3.1');

    $this->postJson("/api/v1/me/lms/assignments/{$assignment['id']}/submit", [
        'body' => 'x = 4 because 2x = 8.',
    ])->assertCreated()->assertJsonPath('data.is_late', false);

    // Teacher grades from the queue.
    Sanctum::actingAs($teacher);
    $submissionId = $this->getJson("/api/v1/assignments/{$assignment['id']}/submissions", branchContext($branch))
        ->assertOk()
        ->json('data.0.id');

    $this->postJson("/api/v1/assignments/{$assignment['id']}/submissions/{$submissionId}/grade", [
        'score' => 8, 'feedback' => 'Neat work.',
    ], branchContext($branch))->assertOk();

    // The graded submission is frozen for the student.
    Sanctum::actingAs($student->user);
    $this->postJson("/api/v1/me/lms/assignments/{$assignment['id']}/submit", ['body' => 'Changed my mind'])
        ->assertUnprocessable();

    // Guardian with grades access sees the score; without, sees status only.
    Sanctum::actingAs(lmsGuardian($student, canViewGrades: true));
    $this->getJson("/api/v1/me/children/{$student->id}/lms")
        ->assertOk()
        ->assertJsonPath('data.assignments.0.score', 8);

    Sanctum::actingAs(lmsGuardian($student, canViewGrades: false));
    $this->getJson("/api/v1/me/children/{$student->id}/lms")
        ->assertOk()
        ->assertJsonPath('data.assignments.0.score', null)
        ->assertJsonPath('data.assignments.0.submission_status', 'returned');
});

it('stamps late work and rejects it under a reject policy', function () {
    $branch = makeBranch();
    $year = lmsYear($branch);
    $section = lmsSection($branch);
    [$teacher, $employee] = lmsTeacher($branch);
    $class = lmsClass($branch, $year, $section, $employee);
    $student = lmsStudent($branch, $year, $section);

    $accepting = Assignment::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'subject_assignment_id' => $class->id,
        'title' => 'Late OK', 'submission_types' => ['text'],
        'due_at' => now()->subHour(), 'late_policy' => 'accept',
        'status' => 'published', 'published_at' => now(),
    ]);

    $rejecting = Assignment::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'subject_assignment_id' => $class->id,
        'title' => 'No late', 'submission_types' => ['text'],
        'due_at' => now()->subHour(), 'late_policy' => 'reject',
        'status' => 'published', 'published_at' => now(),
    ]);

    Sanctum::actingAs($student->user);

    $this->postJson("/api/v1/me/lms/assignments/{$accepting->id}/submit", ['body' => 'sorry!'])
        ->assertCreated()
        ->assertJsonPath('data.is_late', true);

    $this->postJson("/api/v1/me/lms/assignments/{$rejecting->id}/submit", ['body' => 'sorry!'])
        ->assertUnprocessable();
});

// ───────────────────── assignments v2 ─────────────────────

it('grades by rubric — server sums clamped lines and docks the late penalty', function () {
    $branch = makeBranch();
    $year = lmsYear($branch);
    $section = lmsSection($branch);
    [$teacher, $employee] = lmsTeacher($branch);
    $class = lmsClass($branch, $year, $section, $employee);
    $student = lmsStudent($branch, $year, $section);

    Sanctum::actingAs($teacher);
    $assignment = $this->postJson('/api/v1/assignments', [
        'subject_assignment_id' => $class->id,
        'title' => 'Essay',
        'submission_types' => ['text'],
        'rubric' => [
            ['criterion' => 'Content', 'max_points' => 6],
            ['criterion' => 'Grammar', 'max_points' => 4],
        ],
        'due_at' => now()->subMinute()->toIso8601String(),
        'late_policy' => 'accept',
        'late_penalty_percent' => 10,
        'publish' => true,
    ], branchContext($branch))->assertCreated()->json('data');

    // The rubric defines the total.
    expect((float) $assignment['max_score'])->toBe(10.0);

    Sanctum::actingAs($student->user);
    $this->postJson("/api/v1/me/lms/assignments/{$assignment['id']}/submit", ['body' => 'My essay'])
        ->assertCreated()
        ->assertJsonPath('data.is_late', true);

    Sanctum::actingAs($teacher);
    $submissionId = $this->getJson("/api/v1/assignments/{$assignment['id']}/submissions", branchContext($branch))
        ->json('data.0.id');

    // 6/6 + 9 (clamped to 4) → 10, minus 10% late penalty → 9.
    $this->postJson("/api/v1/assignments/{$assignment['id']}/submissions/{$submissionId}/grade", [
        'rubric_scores' => [6, 9],
    ], branchContext($branch))
        ->assertOk()
        ->assertJsonPath('data.score', 9)
        ->assertJsonPath('data.rubric_scores.1', 4);
});

it('enforces the resubmission policy: never, once, until_graded', function () {
    $branch = makeBranch();
    $year = lmsYear($branch);
    $section = lmsSection($branch);
    [, $employee] = lmsTeacher($branch);
    $class = lmsClass($branch, $year, $section, $employee);
    $student = lmsStudent($branch, $year, $section);

    $make = fn (string $policy) => Assignment::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'subject_assignment_id' => $class->id,
        'title' => "Policy {$policy}", 'submission_types' => ['text'],
        'resubmission_policy' => $policy,
        'status' => 'published', 'published_at' => now(),
    ]);

    $never = $make('never');
    $once = $make('once');

    Sanctum::actingAs($student->user);

    $this->postJson("/api/v1/me/lms/assignments/{$never->id}/submit", ['body' => 'v1'])->assertCreated();
    $this->postJson("/api/v1/me/lms/assignments/{$never->id}/submit", ['body' => 'v2'])->assertUnprocessable();

    $this->postJson("/api/v1/me/lms/assignments/{$once->id}/submit", ['body' => 'v1'])->assertCreated();
    $this->postJson("/api/v1/me/lms/assignments/{$once->id}/submit", ['body' => 'v2'])->assertCreated();
    $this->postJson("/api/v1/me/lms/assignments/{$once->id}/submit", ['body' => 'v3'])->assertUnprocessable();
});

it('drives assignment lifecycle from the status field and sanitizes rich instructions', function () {
    $branch = makeBranch();
    $year = lmsYear($branch);
    $section = lmsSection($branch);
    [$teacher, $employee] = lmsTeacher($branch);
    $class = lmsClass($branch, $year, $section, $employee);
    $student = lmsStudent($branch, $year, $section);

    Sanctum::actingAs($teacher);

    // The retired in-class kind is gone.
    $this->postJson('/api/v1/assignments', [
        'subject_assignment_id' => $class->id,
        'title' => 'Old style',
        'kind' => 'offline',
    ], branchContext($branch))->assertUnprocessable();

    // Draft via the editor's status dropdown; script tags never survive.
    $assignment = $this->postJson('/api/v1/assignments', [
        'subject_assignment_id' => $class->id,
        'title' => 'Essay',
        'instructions' => '<p>Write <strong>neatly</strong>.</p><script>alert(1)</script>',
        'submission_types' => ['text'],
        'status' => 'draft',
    ], branchContext($branch))->assertCreated()->json('data');

    expect($assignment['status'])->toBe('draft')
        ->and($assignment['instructions'])->toContain('<strong>')
        ->and($assignment['instructions'])->not->toContain('<script');

    // Drafts are invisible to the class.
    Sanctum::actingAs($student->user);
    $this->getJson('/api/v1/me/lms/assignments')->assertOk()->assertJsonCount(0, 'data');

    // Publishing through update stamps published_at once.
    Sanctum::actingAs($teacher);
    $published = $this->putJson("/api/v1/assignments/{$assignment['id']}", [
        'status' => 'published',
    ], branchContext($branch))->assertOk()->json('data');

    expect($published['status'])->toBe('published')
        ->and($published['published_at'])->not->toBeNull();

    Sanctum::actingAs($student->user);
    $this->getJson('/api/v1/me/lms/assignments')->assertOk()->assertJsonCount(1, 'data');
});

it('lets a student embed images in a rich answer via the /me upload lane', function () {
    Storage::fake();

    $branch = makeBranch();
    $year = lmsYear($branch);
    $section = lmsSection($branch);
    [$teacher, $employee] = lmsTeacher($branch);
    $class = lmsClass($branch, $year, $section, $employee);
    $student = lmsStudent($branch, $year, $section);

    $assignment = Assignment::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'subject_assignment_id' => $class->id,
        'title' => 'Diagram homework', 'submission_types' => ['text'],
        'status' => 'published', 'published_at' => now(),
    ]);

    // Staff have their own lane — the student one is relationship-gated.
    Sanctum::actingAs($teacher);
    $this->postJson('/api/v1/me/lms/uploads', [
        'file' => UploadedFile::fake()->image('sketch.png'),
    ])->assertNotFound();

    Sanctum::actingAs($student->user);
    $upload = $this->postJson('/api/v1/me/lms/uploads', [
        'file' => UploadedFile::fake()->image('sketch.png'),
    ])->assertCreated()->json('data');

    expect($upload['path'])->toContain("lms/answer-media/{$student->id}");

    // The rich answer persists the image as a data-path marker…
    $this->postJson("/api/v1/me/lms/assignments/{$assignment->id}/submit", [
        'body' => '<p>My diagram:</p><img src="'.$upload['url'].'" data-path="'.$upload['path'].'">',
    ])->assertCreated();

    $stored = AssignmentSubmission::query()->where('student_id', $student->id)->first();
    expect($stored->body)->toContain('<img data-path="'.$upload['path'].'"')
        ->and($stored->body)->not->toContain('src=');

    // …and every read re-signs it: the student's own view and the grading queue.
    $body = $this->getJson("/api/v1/me/lms/assignments/{$assignment->id}")
        ->assertOk()->json('data.submission.body');
    expect($body)->toContain('src=');

    Sanctum::actingAs($teacher);
    $queueBody = $this->getJson("/api/v1/assignments/{$assignment->id}/submissions", branchContext($branch))
        ->assertOk()->json('data.0.body');
    expect($queueBody)->toContain('src=')->and($queueBody)->toContain('data-path=');
});

it('targets an assignment at specific students only', function () {
    $branch = makeBranch();
    $year = lmsYear($branch);
    $section = lmsSection($branch);
    [$teacher, $employee] = lmsTeacher($branch);
    $class = lmsClass($branch, $year, $section, $employee);
    $target = lmsStudent($branch, $year, $section, 'Sara');
    $other = lmsStudent($branch, $year, $section, 'Hanna');

    Sanctum::actingAs($teacher);

    // A stranger to the class is rejected outright.
    $outsider = lmsStudent($branch, $year, lmsSection($branch, 'B'), 'Ruth');
    $this->postJson('/api/v1/assignments', [
        'subject_assignment_id' => $class->id,
        'title' => 'Remedial work',
        'submission_types' => ['text'],
        'target_student_ids' => [$outsider->id],
        'publish' => true,
    ], branchContext($branch))->assertUnprocessable();

    // Ids arrive as strings from the editor's multipart form — they must
    // persist as integers (JSONB matching + checkbox state depend on it).
    $assignment = $this->postJson('/api/v1/assignments', [
        'subject_assignment_id' => $class->id,
        'title' => 'Remedial work',
        'submission_types' => ['text'],
        'target_student_ids' => [(string) $target->id],
        'publish' => true,
    ], branchContext($branch))->assertCreated()->json('data');

    expect($assignment['target_student_ids'])->toBe([$target->id]);

    Sanctum::actingAs($target->user);
    $this->getJson('/api/v1/me/lms/assignments')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson("/api/v1/me/lms/assignments/{$assignment['id']}")->assertOk();

    Sanctum::actingAs($other->user);
    $this->getJson('/api/v1/me/lms/assignments')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson("/api/v1/me/lms/assignments/{$assignment['id']}")->assertForbidden();
    $this->postJson("/api/v1/me/lms/assignments/{$assignment['id']}/submit", ['body' => 'hi'])->assertForbidden();

    // Re-checking "Whole class" posts an empty string — the list clears.
    Sanctum::actingAs($teacher);
    $this->putJson("/api/v1/assignments/{$assignment['id']}", [
        'target_student_ids' => '',
    ], branchContext($branch))->assertOk()->assertJsonPath('data.target_student_ids', null);

    Sanctum::actingAs($other->user);
    $this->getJson('/api/v1/me/lms/assignments')->assertOk()->assertJsonCount(1, 'data');
});

it('wraps a quiz as an assignment: no direct turn-ins, the quiz is the work', function () {
    $branch = makeBranch();
    $year = lmsYear($branch);
    $section = lmsSection($branch);
    [$teacher, $employee] = lmsTeacher($branch);
    $class = lmsClass($branch, $year, $section, $employee);
    $student = lmsStudent($branch, $year, $section);
    $quiz = lmsQuiz($class, lmsQuestions(lmsBank($branch, $teacher)));

    Sanctum::actingAs($teacher);

    // A quiz from another class is refused.
    $otherClass = lmsClass($branch, $year, lmsSection($branch, 'B'), $employee);
    $foreignQuiz = lmsQuiz($otherClass, lmsQuestions(lmsBank($branch, $teacher)));
    $this->postJson('/api/v1/assignments', [
        'subject_assignment_id' => $class->id,
        'kind' => 'quiz',
        'quiz_id' => $foreignQuiz->id,
        'title' => 'Unit quiz homework',
        'publish' => true,
    ], branchContext($branch))->assertUnprocessable();

    $assignment = $this->postJson('/api/v1/assignments', [
        'subject_assignment_id' => $class->id,
        'kind' => 'quiz',
        'quiz_id' => $quiz->id,
        'title' => 'Unit quiz homework',
        'due_at' => now()->addDay()->toIso8601String(),
        'publish' => true,
    ], branchContext($branch))->assertCreated()->json('data');

    Sanctum::actingAs($student->user);

    // The detail carries the live quiz state; direct submission is refused.
    $this->getJson("/api/v1/me/lms/assignments/{$assignment['id']}")
        ->assertOk()
        ->assertJsonPath('data.kind', 'quiz')
        ->assertJsonPath('data.quiz.id', $quiz->id)
        ->assertJsonPath('data.quiz.can_start', true);

    $this->postJson("/api/v1/me/lms/assignments/{$assignment['id']}/submit", ['body' => 'answers'])
        ->assertUnprocessable();
});

it('carries the private teacher↔student thread on an assignment', function () {
    $branch = makeBranch();
    $year = lmsYear($branch);
    $section = lmsSection($branch);
    [$teacher, $employee] = lmsTeacher($branch);
    $class = lmsClass($branch, $year, $section, $employee);
    $student = lmsStudent($branch, $year, $section, 'Sara');
    $other = lmsStudent($branch, $year, $section, 'Hanna');

    $assignment = Assignment::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'subject_assignment_id' => $class->id,
        'title' => 'Exercise', 'submission_types' => ['text'],
        'status' => 'published', 'published_at' => now(),
    ]);

    // Student asks a question before submitting anything — the thread is a
    // chat CONTEXT conversation (ADR-019) resolved on first open.
    Sanctum::actingAs($student->user);
    $conversationId = $this->getJson("/api/v1/me/lms/assignments/{$assignment->id}/thread")
        ->assertOk()->json('data.conversation_id');
    $this->postJson("/api/v1/me/chat/conversations/{$conversationId}/messages", ['body' => 'Which page, teacher?'])
        ->assertCreated();

    // The inbox surfaces the thread even though nothing was submitted.
    app(ConversationAccess::class)->flush();
    Sanctum::actingAs($teacher);
    $this->getJson("/api/v1/assignments/{$assignment->id}/threads", branchContext($branch))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.student_id', $student->id)
        ->assertJsonPath('data.0.conversation_id', $conversationId)
        ->assertJsonPath('data.0.awaiting_reply', true);

    // Teacher replies through the staff chat lane.
    $this->getJson("/api/v1/assignments/{$assignment->id}/thread?student_id={$student->id}", branchContext($branch))
        ->assertOk()
        ->assertJsonPath('data.conversation_id', $conversationId);
    $this->postJson("/api/v1/chat/conversations/{$conversationId}/messages", [
        'body' => 'Page 42.',
    ], branchContext($branch))->assertCreated()->assertJsonPath('data.status', 'sent');

    // Answered: the inbox row flips.
    $this->getJson("/api/v1/assignments/{$assignment->id}/threads", branchContext($branch))
        ->assertOk()
        ->assertJsonPath('data.0.messages_count', 2)
        ->assertJsonPath('data.0.awaiting_reply', false);

    // The thread is private: the classmate cannot open it, and their own
    // thread lookup resolves a DIFFERENT conversation.
    app(ConversationAccess::class)->flush();
    Sanctum::actingAs($other->user);
    $this->getJson("/api/v1/me/chat/conversations/{$conversationId}/messages")->assertForbidden();
    $otherThread = $this->getJson("/api/v1/me/lms/assignments/{$assignment->id}/thread")
        ->assertOk()->json('data.conversation_id');
    expect($otherThread)->not->toBe($conversationId);

    // The student sees both messages.
    app(ConversationAccess::class)->flush();
    Sanctum::actingAs($student->user);
    $this->getJson("/api/v1/me/chat/conversations/{$conversationId}/messages")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.1.body', 'Page 42.');

    // Context threads are NAMED, never a bare "Conversation": the student
    // side is titled after the work (teacher underneath), the staff side
    // after the student (work underneath).
    $display = $this->getJson("/api/v1/me/chat/conversations/{$conversationId}")
        ->assertOk()->json('data.display');
    expect($display['title'])->toBe('Exercise')
        ->and($display['subtitle'])->toBe($teacher->name);

    app(ConversationAccess::class)->flush();
    Sanctum::actingAs($teacher);
    $display = $this->getJson("/api/v1/chat/conversations/{$conversationId}", branchContext($branch))
        ->assertOk()->json('data.display');
    expect($display['title'])->toContain('Sara')
        ->and($display['subtitle'])->toBe('Exercise');
});

// ───────────────────────── materials ─────────────────────────

it('routes teacher materials to targeted classes and window posts to the grade', function () {
    $branch = makeBranch();
    $year = lmsYear($branch);
    $sectionA = lmsSection($branch, 'A');
    $sectionB = lmsSection($branch, 'B');
    [$teacher, $employee] = lmsTeacher($branch);
    $classA = lmsClass($branch, $year, $sectionA, $employee);
    $studentA = lmsStudent($branch, $year, $sectionA);
    $studentB = lmsStudent($branch, $year, $sectionB, 'Hanna');

    // Teacher posts a link to class A only.
    Sanctum::actingAs($teacher);
    $this->postJson('/api/v1/course-materials', [
        'title' => 'Unit 1 notes',
        'type' => 'link',
        'url' => 'https://example.org/notes.pdf',
        'subject_assignment_ids' => [$classA->id],
    ], branchContext($branch))->assertCreated();

    // Director posts a grade-window video for every G7 class.
    Sanctum::actingAs(directorOf($branch));
    $g7 = GradeLevel::where('code', 'G7')->value('sort_order');
    $this->postJson('/api/v1/course-materials', [
        'title' => 'Study skills',
        'type' => 'youtube',
        'url' => 'https://youtu.be/dQw4w9WgXcQ',
        'min_grade_sort' => $g7,
        'max_grade_sort' => $g7,
    ], branchContext($branch))->assertCreated();

    Sanctum::actingAs($studentA->user);
    $titlesA = collect($this->getJson('/api/v1/me/lms/materials')->assertOk()->json('data'))->pluck('title');
    expect($titlesA)->toContain('Unit 1 notes')->toContain('Study skills');

    Sanctum::actingAs($studentB->user);
    $titlesB = collect($this->getJson('/api/v1/me/lms/materials')->assertOk()->json('data'))->pluck('title');
    expect($titlesB)->not->toContain('Unit 1 notes'); // targeted at class A only
});

it('lets a platform admin browse one school\'s materials, narrowed by branch/grade/section', function () {
    $branchA = makeBranch('AA-0001');
    $branchB = makeBranch('AA-0002');
    $yearA = lmsYear($branchA);
    $sectionA = lmsSection($branchA, 'A');
    $sectionA2 = lmsSection($branchA, 'B', 'G8');
    [, $employeeA] = lmsTeacher($branchA);
    $classA = lmsClass($branchA, $yearA, $sectionA, $employeeA);
    $classA2 = lmsClass($branchA, $yearA, $sectionA2, $employeeA);

    Sanctum::actingAs(directorOf($branchA));
    $this->postJson('/api/v1/course-materials', [
        'title' => 'G7 handout',
        'type' => 'link',
        'url' => 'https://example.org/g7.pdf',
        'subject_assignment_ids' => [$classA->id],
    ], branchContext($branchA))->assertCreated();
    $this->postJson('/api/v1/course-materials', [
        'title' => 'G8 handout',
        'type' => 'link',
        'url' => 'https://example.org/g8.pdf',
        'subject_assignment_ids' => [$classA2->id],
    ], branchContext($branchA))->assertCreated();

    $admin = platformAdmin();
    Sanctum::actingAs($admin);

    // No context at all still 422s — platform staff must name a school.
    $this->getJson('/api/v1/course-materials')->assertStatus(422);

    // school_id alone sees every branch's rows.
    $all = collect($this->getJson("/api/v1/course-materials?school_id={$branchA->school_id}")->assertOk()->json('data'))
        ->pluck('title');
    expect($all)->toContain('G7 handout')->toContain('G8 handout');

    // Narrowing by branch_id + grade_level_id isolates the G7 class only.
    $g7Id = GradeLevel::where('code', 'G7')->value('id');
    $narrowed = collect($this->getJson(
        "/api/v1/course-materials?school_id={$branchA->school_id}&branch_id={$branchA->id}&grade_level_id={$g7Id}",
    )->assertOk()->json('data'))->pluck('title');
    expect($narrowed)->toContain('G7 handout')->not->toContain('G8 handout');

    // A school the admin never scoped into is still isolated from another school.
    $other = collect($this->getJson("/api/v1/course-materials?school_id={$branchB->school_id}")->assertOk()->json('data'));
    expect($other)->toBeEmpty();
});

// ───────────────────── the open exam-prep lane ─────────────────────

it('opens platform mock exams to any registered user — school or none', function () {
    $contentAdmin = User::factory()->create();
    grantPlatformRole($contentAdmin, Role::ContentAdmin);

    // Temari.et publishes a national mock.
    $bank = QuestionBank::create(['name' => 'EUEE Maths', 'created_by' => $contentAdmin->id]);
    $questions = lmsQuestions($bank);

    $mock = Quiz::create([
        'is_platform' => true,
        'kind' => 'mock',
        'title' => 'EUEE Mathematics Mock 1',
        'grade_level_id' => GradeLevel::where('code', 'G12')->value('id'),
        'subject_id' => Subject::where('code', 'MATH')->value('id'),
        'settings' => ['attempts_allowed' => 0, 'results_policy' => 'immediately', 'reveal_answers' => true],
        'status' => 'published',
        'published_at' => now(),
    ]);
    foreach (array_values($questions) as $i => $q) {
        $mock->quizQuestions()->create(['question_id' => $q->id, 'sort_order' => $i]);
    }

    // A user with NO school, NO memberships, NO student record.
    $learner = User::factory()->create();
    Sanctum::actingAs($learner);

    $this->getJson('/api/v1/me/exam-prep')
        ->assertOk()
        ->assertJsonPath('data.0.title', 'EUEE Mathematics Mock 1')
        ->assertJsonPath('data.0.can_start', true);

    $attempt = $this->postJson("/api/v1/me/exams/{$mock->id}/start")->assertCreated()->json('data');
    foreach (lmsPerfectAnswers($questions) as $qid => $answer) {
        $this->postJson("/api/v1/me/exam-attempts/{$attempt['attempt_id']}/answer", [
            'question_id' => $qid, 'answer' => $answer,
        ])->assertOk();
    }
    $this->postJson("/api/v1/me/exam-attempts/{$attempt['attempt_id']}/submit")->assertOk();

    $result = $this->getJson("/api/v1/me/exam-attempts/{$attempt['attempt_id']}/result")->assertOk()->json('data');

    // Full marks, with explanations revealed for study.
    expect((float) $result['score'])->toBe(6.0)
        ->and($result['reveal_answers'])->toBeTrue()
        ->and($result['questions'][0])->toHaveKey('answer_key');

    // Unlimited attempts: can start again.
    $this->postJson("/api/v1/me/exams/{$mock->id}/start")->assertCreated();
});

it('carries national-exam identity on platform papers and filters the prep library by it', function () {
    $contentAdmin = User::factory()->create();
    grantPlatformRole($contentAdmin, Role::ContentAdmin);

    Sanctum::actingAs($contentAdmin);

    $paper = $this->postJson('/api/v1/quizzes', [
        'platform' => true,
        'kind' => 'mock',
        'title' => 'EUEE Mathematics 2016 — Natural',
        'exam_kind' => 'national_past',
        'exam_year_ec' => 2016,
        'stream' => 'natural',
        'settings' => ['duration_minutes' => 120, 'attempts_allowed' => 0],
    ])->assertCreated()
        ->assertJsonPath('data.exam_kind', 'national_past')
        ->assertJsonPath('data.exam_year_ec', 2016)
        ->assertJsonPath('data.stream', 'natural')
        ->json('data');

    $bank = QuestionBank::create(['name' => 'EUEE pool', 'created_by' => $contentAdmin->id]);
    $question = $bank->questions()->create([
        'type' => 'true_false',
        'body' => ['stem' => 'π is rational.'],
        'answer_key' => ['correct' => false],
        'points' => 1,
    ]);
    Quiz::findOrFail($paper['id'])->quizQuestions()->create(['question_id' => $question->id, 'sort_order' => 0]);
    $this->postJson("/api/v1/quizzes/{$paper['id']}/publish")->assertOk();

    // The open prep lane filters by year/stream/kind and labels the mode.
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/me/exam-prep?exam_kind=national_past&exam_year_ec=2016&stream=natural')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.exam_year_ec', 2016)
        ->assertJsonPath('data.0.mode', 'mock');

    $this->getJson('/api/v1/me/exam-prep?exam_year_ec=2015')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $facets = $this->getJson('/api/v1/me/exam-prep/facets')->assertOk()->json('data');
    expect($facets['years_ec'])->toContain(2016)
        ->and($facets['exam_kinds'])->toContain('national_past');

    // A class quiz can never carry prep identity.
    expect(Quiz::query()->whereNotNull('exam_kind')->where('is_platform', false)->exists())->toBeFalse();
});

it('hides draft platform exams from the prep browser', function () {
    Quiz::create([
        'is_platform' => true, 'kind' => 'mock', 'title' => 'Unreleased',
        'settings' => [], 'status' => 'draft',
    ]);

    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/me/exam-prep')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

// ───────────────────── cross-tenant isolation ─────────────────────

it('gives a teacher at school B zero reach into school A quizzes', function () {
    $branchA = makeBranch('AA-0001');
    $branchB = makeBranch('AA-0002');
    $yearA = lmsYear($branchA);
    $sectionA = lmsSection($branchA);
    [$teacherA, $employeeA] = lmsTeacher($branchA);
    $classA = lmsClass($branchA, $yearA, $sectionA, $employeeA);
    $quizA = lmsQuiz($classA, lmsQuestions(lmsBank($branchA, $teacherA)));

    [$teacherB] = lmsTeacher($branchB);
    Sanctum::actingAs($teacherB);

    $this->getJson("/api/v1/quizzes/{$quizA->id}", branchContext($branchB))->assertForbidden();
    $this->putJson("/api/v1/quizzes/{$quizA->id}", ['title' => 'Hacked'], branchContext($branchB))->assertForbidden();
    $this->getJson("/api/v1/quizzes/{$quizA->id}/attempts", branchContext($branchB))->assertForbidden();

    Sanctum::actingAs(directorOf($branchB));
    $this->getJson("/api/v1/quizzes/{$quizA->id}", branchContext($branchB))->assertForbidden();
    $this->getJson('/api/v1/quizzes', branchContext($branchB))->assertOk()->assertJsonCount(0, 'data');
});

it('keeps students out of staff LMS endpoints entirely', function () {
    $branch = makeBranch();
    $year = lmsYear($branch);
    $section = lmsSection($branch);
    [$teacher, $employee] = lmsTeacher($branch);
    $class = lmsClass($branch, $year, $section, $employee);
    $quiz = lmsQuiz($class, lmsQuestions(lmsBank($branch, $teacher)));
    $student = lmsStudent($branch, $year, $section);

    Sanctum::actingAs($student->user);

    // List endpoints refuse for lack of any staff context (422); row reads
    // hit the policy wall (403). Either way: zero staff data.
    $this->getJson('/api/v1/quizzes', branchContext($branch))->assertStatus(422);
    $this->getJson("/api/v1/quizzes/{$quiz->id}", branchContext($branch))->assertForbidden();
    $this->getJson('/api/v1/question-banks', branchContext($branch))->assertStatus(422);
});

// ───────────────────── fix-pack regressions (July 2026) ─────────────────────

it('runs one exam across several sections and counts takers per target', function () {
    $branch = makeBranch();
    $year = lmsYear($branch);
    $sectionA = lmsSection($branch, 'A');
    $sectionB = lmsSection($branch, 'B');
    [$teacher, $employee] = lmsTeacher($branch);
    $classA = lmsClass($branch, $year, $sectionA, $employee);
    $classB = lmsClass($branch, $year, $sectionB, $employee);
    $questions = lmsQuestions(lmsBank($branch, $teacher));

    $studentA = lmsStudent($branch, $year, $sectionA, 'Sara');
    $studentB = lmsStudent($branch, $year, $sectionB, 'Hanna');

    Sanctum::actingAs($teacher);

    // One paper, both sections (grade → subject → sections in the editor).
    $quiz = $this->postJson('/api/v1/quizzes', [
        'subject_assignment_ids' => [$classA->id, $classB->id],
        'kind' => 'exam',
        'title' => 'Semester exam',
        'instructions' => '<p>Read <b>carefully</b>.</p><script>x()</script>',
        'settings' => ['attempts_allowed' => 1, 'results_policy' => 'immediately'],
        'questions' => collect($questions)->map(fn ($q) => ['question_id' => $q->id])->all(),
    ], branchContext($branch))
        ->assertCreated()
        ->json('data');

    expect($quiz['subject_assignment_ids'])->toBe([$classA->id, $classB->id])
        ->and($quiz['instructions'])->not->toContain('<script>');

    $this->postJson("/api/v1/quizzes/{$quiz['id']}/publish", [], branchContext($branch))->assertOk();

    // Both sections' students sit the same paper; an outsider cannot.
    foreach ([$studentA, $studentB] as $taker) {
        Sanctum::actingAs($taker->user);
        $this->postJson("/api/v1/me/exams/{$quiz['id']}/start")->assertCreated();
    }

    $outsider = lmsStudent($branch, $year, lmsSection($branch, 'C'), 'Ruth');
    Sanctum::actingAs($outsider->user);
    $this->postJson("/api/v1/me/exams/{$quiz['id']}/start")->assertUnprocessable();

    // Expected = active students across BOTH sections; taken = the two sitters.
    Sanctum::actingAs($teacher);
    $row = $this->getJson("/api/v1/quizzes/{$quiz['id']}", branchContext($branch))->assertOk()->json('data');
    expect($row['expected_takers'])->toBe(2)
        ->and($row['takers_count'])->toBe(2)
        ->and(collect($row['sections'])->pluck('name')->all())->toBe(['A', 'B']);

    // A section whose students already sat can never be dropped.
    $this->putJson("/api/v1/quizzes/{$quiz['id']}", [
        'subject_assignment_ids' => [$classA->id],
    ], branchContext($branch))->assertUnprocessable();
});

it('fans a multi-section exam out into each section\'s own gradebook slot', function () {
    $branch = makeBranch();
    $year = lmsYear($branch);
    $sectionA = lmsSection($branch, 'A');
    $sectionB = lmsSection($branch, 'B');
    [$teacher, $employee] = lmsTeacher($branch);
    $classA = lmsClass($branch, $year, $sectionA, $employee);
    $classB = lmsClass($branch, $year, $sectionB, $employee);
    $questions = lmsQuestions(lmsBank($branch, $teacher));

    // The same ad-hoc slot exists in both classes (same name + max).
    $slotA = $classA->assessments()->create(['type' => 'quiz', 'name' => 'Quiz 1', 'max_score' => 10]);
    $slotB = $classB->assessments()->create(['type' => 'quiz', 'name' => 'Quiz 1', 'max_score' => 10]);

    $quiz = lmsQuiz($classA, $questions, [], ['assessment_id' => $slotA->id]);
    $quiz->targets()->create(['subject_assignment_id' => $classB->id]);

    $studentA = lmsStudent($branch, $year, $sectionA, 'Sara');
    $studentB = lmsStudent($branch, $year, $sectionB, 'Hanna');

    foreach ([$studentA, $studentB] as $taker) {
        Sanctum::actingAs($taker->user);
        $attempt = $this->postJson("/api/v1/me/exams/{$quiz->id}/start")->assertCreated()->json('data');
        foreach (lmsPerfectAnswers($questions) as $qid => $answer) {
            $this->postJson("/api/v1/me/exam-attempts/{$attempt['attempt_id']}/answer", [
                'question_id' => $qid, 'answer' => $answer,
            ])->assertOk();
        }
        $this->postJson("/api/v1/me/exam-attempts/{$attempt['attempt_id']}/submit")->assertOk();
    }

    Sanctum::actingAs($teacher);
    $this->postJson("/api/v1/quizzes/{$quiz->id}/sync", [], branchContext($branch))
        ->assertOk()
        ->assertJsonPath('meta.count', 2);

    // Each student's mark landed in THEIR section's slot, rescaled to /10.
    expect(DB::table('assessment_results')->where('assessment_id', $slotA->id)->where('student_id', $studentA->id)->value('score'))->toBe('10.00')
        ->and(DB::table('assessment_results')->where('assessment_id', $slotB->id)->where('student_id', $studentB->id)->value('score'))->toBe('10.00')
        ->and(DB::table('assessment_results')->where('assessment_id', $slotA->id)->count())->toBe(1);
});

it('creates a quiz as a serialisable draft — the create response never 500s', function () {
    $branch = makeBranch();
    $year = lmsYear($branch);
    [$teacher, $employee] = lmsTeacher($branch);
    $class = lmsClass($branch, $year, lmsSection($branch), $employee);

    Sanctum::actingAs($teacher);

    $this->postJson('/api/v1/quizzes', [
        'subject_assignment_id' => $class->id,
        'kind' => 'quiz',
        'title' => 'Fresh quiz',
        'settings' => ['attempts_allowed' => 1],
    ], branchContext($branch))
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft');

    // Missing essentials come back as field errors, never an exception.
    $this->postJson('/api/v1/quizzes', [], branchContext($branch))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['subject_assignment_ids', 'kind', 'title', 'settings']);
});

it('locks teacher question banks to subjects they currently teach', function () {
    $branch = makeBranch();
    $year = lmsYear($branch);
    [$teacher, $employee] = lmsTeacher($branch);
    lmsClass($branch, $year, lmsSection($branch), $employee); // teaches MATH

    Sanctum::actingAs($teacher);

    // No subject at all → rejected.
    $this->postJson('/api/v1/question-banks', ['name' => 'No subject'], branchContext($branch))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['subject_id']);

    $g7 = GradeLevel::where('code', 'G7')->value('id');

    // A subject they do not teach → rejected.
    $this->postJson('/api/v1/question-banks', [
        'name' => 'English bank',
        'subject_id' => Subject::where('code', 'ENG')->value('id'),
        'grade_level_id' => $g7,
    ], branchContext($branch))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['subject_id']);

    // Their own subject → fine.
    $this->postJson('/api/v1/question-banks', [
        'name' => 'Maths bank',
        'subject_id' => Subject::where('code', 'MATH')->value('id'),
        'grade_level_id' => $g7,
    ], branchContext($branch))->assertCreated();

    // Supervisors stay free to organise banks for any subject.
    Sanctum::actingAs(directorOf($branch));
    $this->postJson('/api/v1/question-banks', [
        'name' => 'Any subject',
        'subject_id' => Subject::where('code', 'ENG')->value('id'),
        'grade_level_id' => $g7,
    ], branchContext($branch))
        ->assertCreated();
});

it('shows the grade level on class exams', function () {
    $branch = makeBranch();
    $year = lmsYear($branch);
    [$teacher, $employee] = lmsTeacher($branch);
    $class = lmsClass($branch, $year, lmsSection($branch), $employee);
    $quiz = lmsQuiz($class, lmsQuestions(lmsBank($branch, $teacher)));

    Sanctum::actingAs($teacher);

    $this->getJson("/api/v1/quizzes/{$quiz->id}", branchContext($branch))
        ->assertOk()
        ->assertJsonPath('data.grade_level_name', 'Grade 7');

    $row = collect($this->getJson('/api/v1/quizzes', branchContext($branch))->assertOk()->json('data'))
        ->firstWhere('id', $quiz->id);
    expect($row['grade_level_name'])->toBe('Grade 7');
});

it('surfaces planned gradebook slots before the marklist is ever opened', function () {
    $branch = makeBranch();
    $year = lmsYear($branch);
    $section = lmsSection($branch);
    [$teacher, $employee] = lmsTeacher($branch);
    $class = lmsClass($branch, $year, $section, $employee);

    $plan = ContinuousAssessment::create([
        'school_id' => $branch->school_id,
        'branch_id' => $branch->id,
        'term_id' => $class->term_id,
        'name' => 'Standard CA',
        'is_active' => true,
    ]);
    $plan->targets()->create(['grade_level_id' => null, 'section_ids' => null, 'subject_ids' => null]);
    foreach ([['test', 'Test 1', 20], ['assignment', 'Assignment 1', 10], ['midterm', 'Midterm', 30], ['final', 'Final', 40]] as $i => [$type, $name, $weight]) {
        $plan->items()->create([
            'type' => $type, 'name' => $name, 'weight' => $weight, 'max_score' => $weight, 'sort_order' => $i,
        ]);
    }

    Sanctum::actingAs($teacher);

    // The register reports the PLANNED count even though nothing is materialised yet.
    $rows = $this->getJson("/api/v1/marklists?term_id={$class->term_id}", branchContext($branch))
        ->assertOk()
        ->json('data');
    expect(collect($rows)->firstWhere('subject_assignment_id', $class->id)['assessments_count'])->toBe(4);

    // The exam builder's slot list materialises the plan on sight.
    $this->getJson("/api/v1/subject-assignments/{$class->id}/assessments", branchContext($branch))
        ->assertOk()
        ->assertJsonCount(4, 'data');
});

// ───────────────────────── paper parts & printing ─────────────────────────

it('organises a fixed paper into parts and shuffles only within them', function () {
    $branch = makeBranch();
    $year = lmsYear($branch);
    $section = lmsSection($branch);
    [$teacher, $employee] = lmsTeacher($branch);
    $class = lmsClass($branch, $year, $section, $employee);
    [$mcq, $multi, $numeric, $tf] = lmsQuestions(lmsBank($branch, $teacher));

    Sanctum::actingAs($teacher);

    $quiz = $this->postJson('/api/v1/quizzes', [
        'subject_assignment_id' => $class->id,
        'kind' => 'exam',
        'title' => 'Semester exam',
        'settings' => ['attempts_allowed' => 1, 'shuffle_questions' => true, 'results_policy' => 'immediately'],
        'parts' => [
            ['title' => 'Multiple Choice', 'instructions' => '<p>Choose the best answer.</p>'],
            ['title' => 'True or False'],
        ],
        'questions' => [
            ['question_id' => $mcq->id, 'part' => 0],
            ['question_id' => $multi->id, 'part' => 0],
            ['question_id' => $tf->id, 'part' => 1],
            ['question_id' => $numeric->id, 'part' => null],
        ],
    ], branchContext($branch))->assertCreated()->json('data');

    // Parts and per-question filing round-trip on detail; out-of-range
    // part references would have been clamped to unfiled, never 500.
    $detail = $this->getJson("/api/v1/quizzes/{$quiz['id']}", branchContext($branch))
        ->assertOk()
        ->json('data');
    expect($detail['parts'])->toHaveCount(2)
        ->and($detail['parts'][0]['title'])->toBe('Multiple Choice')
        ->and($detail['parts'][0]['instructions'])->toContain('Choose the best answer')
        ->and(collect($detail['questions'])->firstWhere('id', $tf->id)['part_index'])->toBe(1)
        ->and(collect($detail['questions'])->firstWhere('id', $numeric->id)['part_index'])->toBeNull();

    $this->postJson("/api/v1/quizzes/{$quiz['id']}/publish", [], branchContext($branch))->assertOk();

    // Every sitting keeps the printed part order — Part I's questions (in
    // either shuffled order) first, then Part II, then the unfiled tail.
    $student = lmsStudent($branch, $year, $section);
    Sanctum::actingAs($student->user);

    $attempt = $this->postJson("/api/v1/me/exams/{$quiz['id']}/start")->assertCreated()->json('data');
    $paper = collect(
        $this->getJson("/api/v1/me/exam-attempts/{$attempt['attempt_id']}")->assertOk()->json('data.questions'),
    );

    expect($paper->pluck('part')->all())->toBe([0, 0, 1, null])
        ->and($paper->take(2)->pluck('question_id')->sort()->values()->all())
        ->toBe(collect([$mcq->id, $multi->id])->sort()->values()->all())
        ->and($paper[2]['question_id'])->toBe($tf->id)
        ->and($paper[3]['question_id'])->toBe($numeric->id);

    // The taker's screen also carries the part titles.
    $state = $this->getJson("/api/v1/me/exam-attempts/{$attempt['attempt_id']}")->assertOk()->json('data');
    expect($state['parts'][0]['title'])->toBe('Multiple Choice');
});

it('freezes the part structure once someone has sat the exam', function () {
    $branch = makeBranch();
    $year = lmsYear($branch);
    $section = lmsSection($branch);
    [$teacher, $employee] = lmsTeacher($branch);
    $class = lmsClass($branch, $year, $section, $employee);
    $quiz = lmsQuiz($class, lmsQuestions(lmsBank($branch, $teacher)), extra: [
        'parts' => [['title' => 'Everything', 'instructions' => null]],
        'created_by' => $teacher->id,
    ]);

    $student = lmsStudent($branch, $year, $section);
    Sanctum::actingAs($student->user);
    $this->postJson("/api/v1/me/exams/{$quiz->id}/start")->assertCreated();

    Sanctum::actingAs($teacher);

    // Retitling the part is fine…
    $this->putJson("/api/v1/quizzes/{$quiz->id}", [
        'parts' => [['title' => 'Renamed part', 'instructions' => null]],
    ], branchContext($branch))->assertOk();

    // …but adding/removing parts would orphan the frozen papers.
    $this->putJson("/api/v1/quizzes/{$quiz->id}", [
        'parts' => [['title' => 'One'], ['title' => 'Two']],
    ], branchContext($branch))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['parts']);
});

it('prints the exam as an A4 question paper — and the key only to its manager', function () {
    Storage::fake();
    config()->set('services.cloudflare.account_id', 'acc-test');
    config()->set('services.cloudflare.api_token', 'token-test');
    Http::fake(['api.cloudflare.com/*' => Http::response('%PDF-1.4 fake-pdf', 200)]);

    $branch = makeBranch();
    $year = lmsYear($branch);
    $section = lmsSection($branch);
    [$teacher, $employee] = lmsTeacher($branch);
    $class = lmsClass($branch, $year, $section, $employee);
    $quiz = lmsQuiz($class, lmsQuestions(lmsBank($branch, $teacher)), extra: [
        'parts' => [['title' => 'Multiple Choice', 'instructions' => '<p>Pick one.</p>']],
        'created_by' => $teacher->id,
    ]);
    $quiz->quizQuestions()->update(['part_index' => 0]);

    Sanctum::actingAs($teacher);

    // Question paper renders through the document pipeline (sync queue).
    $paper = $this->postJson('/api/v1/documents', [
        'type' => 'exam_paper', 'subject_id' => $quiz->id, 'params' => ['variant' => 'questions'],
    ], branchContext($branch))->assertOk()->json('data');
    expect($paper['status'])->toBe('ready')
        ->and($paper['url'])->not->toBeNull();

    // The rendered HTML is the paper, not the key.
    $html = collect(Http::recorded())->map(fn ($pair) => $pair[0]->data()['html'] ?? '')->implode('');
    expect($html)->toContain('Unit 1 Quiz')
        ->and($html)->toContain('Part I')
        ->and($html)->toContain('2 + 2 = ?')
        ->and($html)->not->toContain('Marking key');

    // The marking key is a separate document, allowed for the manager.
    $key = $this->postJson('/api/v1/documents', [
        'type' => 'exam_paper', 'subject_id' => $quiz->id, 'params' => ['variant' => 'answer_key'],
    ], branchContext($branch))->assertOk()->json('data');
    expect($key['status'])->toBe('ready')
        ->and($key['id'])->not->toBe($paper['id']);

    // Students never print exam papers — either variant.
    $student = lmsStudent($branch, $year, $section);
    Sanctum::actingAs($student->user);
    $this->postJson('/api/v1/documents', [
        'type' => 'exam_paper', 'subject_id' => $quiz->id, 'params' => ['variant' => 'questions'],
    ])->assertForbidden();

    // Neither does staff of another school.
    Sanctum::actingAs(directorOf(makeBranch('BB-0001')));
    $this->postJson('/api/v1/documents', [
        'type' => 'exam_paper', 'subject_id' => $quiz->id, 'params' => ['variant' => 'questions'],
    ])->assertForbidden();

    // Random-draw exams have no single paper to print.
    Sanctum::actingAs($teacher);
    $draw = lmsQuiz($class, [], extra: ['created_by' => $teacher->id]);
    $draw->quizQuestions()->delete();
    $draw->update(['draw' => [['question_bank_id' => QuestionBank::first()->id, 'count' => 2]]]);
    $this->postJson('/api/v1/documents', [
        'type' => 'exam_paper', 'subject_id' => $draw->id, 'params' => ['variant' => 'questions'],
    ], branchContext($branch))->assertUnprocessable();
});

// ───────────────────── scheduling guards ─────────────────────

it('refuses exam windows and assignment deadlines on days already gone', function () {
    $branch = makeBranch();
    $year = lmsYear($branch);
    $section = lmsSection($branch);
    [$teacher, $employee] = lmsTeacher($branch);
    $class = lmsClass($branch, $year, $section, $employee);
    $questions = lmsQuestions(lmsBank($branch, $teacher));

    Sanctum::actingAs($teacher);

    // A new exam cannot open on a day already gone.
    $this->postJson('/api/v1/quizzes', [
        'subject_assignment_ids' => [$class->id],
        'kind' => 'exam', 'title' => 'Backdated exam',
        'settings' => ['opens_at' => now()->subDays(2)->toIso8601String()],
    ], branchContext($branch))->assertUnprocessable()
        ->assertJsonValidationErrors('settings.opens_at');

    // …but an edit that keeps an existing past window untouched still saves.
    $running = lmsQuiz($class, $questions, ['opens_at' => now()->subDays(3)->toIso8601String()], [
        'created_by' => $teacher->id,
    ]);
    $this->putJson("/api/v1/quizzes/{$running->id}", [
        'title' => 'Renamed, window untouched',
        'settings' => $running->settings,
    ], branchContext($branch))->assertOk();

    // Publishing a paper whose window already closed is refused.
    $stale = lmsQuiz($class, $questions, ['closes_at' => now()->subHour()->toIso8601String()], [
        'status' => 'draft', 'published_at' => null, 'title' => 'Stale window',
    ]);
    $this->postJson("/api/v1/quizzes/{$stale->id}/publish", [], branchContext($branch))
        ->assertUnprocessable();

    // Assignments: a deadline on a day already gone is refused on create.
    $this->postJson('/api/v1/assignments', [
        'subject_assignment_id' => $class->id, 'title' => 'Old homework',
        'submission_types' => ['text'],
        'due_at' => now()->subDays(2)->toIso8601String(),
    ], branchContext($branch))->assertUnprocessable()
        ->assertJsonValidationErrors('due_at');
});

it('takes the classwork file types teachers actually hand out — including a real .csv', function () {
    $branch = makeBranch();
    $year = lmsYear($branch);
    $section = lmsSection($branch);
    [$teacher, $employee] = lmsTeacher($branch);
    $class = lmsClass($branch, $year, $section, $employee);

    Storage::fake('r2');
    Sanctum::actingAs($teacher);

    // Real content, not `fake()->create()` — `mimes:` matches the extension
    // GUESSED from the bytes, so an empty stub proves nothing. A .csv reads
    // as text/plain, which is exactly the case that used to be refused.
    $files = [
        UploadedFile::fake()->createWithContent('marks.csv', "student,score\nSara,88\n"),
        UploadedFile::fake()->createWithContent('notes.txt', 'Read chapter 3.'),
        UploadedFile::fake()->createWithContent('rubric.md', '# Rubric'),
    ];

    $created = $this->postJson('/api/v1/assignments', [
        'subject_assignment_id' => $class->id, 'title' => 'Data handling',
        'submission_types' => ['text'],
        'attachments' => $files,
    ], branchContext($branch))->assertCreated();

    expect($created->json('data.attachments'))->toHaveCount(3);

    // The list is a whitelist, not a free-for-all: nothing the browser would
    // execute from a signed link gets in.
    $this->postJson('/api/v1/assignments', [
        'subject_assignment_id' => $class->id, 'title' => 'Sneaky',
        'submission_types' => ['text'],
        'attachments' => [UploadedFile::fake()->createWithContent('x.html', '<script>alert(1)</script>')],
    ], branchContext($branch))->assertUnprocessable()
        ->assertJsonValidationErrors('attachments.0');
});

it('refuses an assignment deadline that does not follow its start', function () {
    $branch = makeBranch();
    $year = lmsYear($branch);
    $section = lmsSection($branch);
    [$teacher, $employee] = lmsTeacher($branch);
    $class = lmsClass($branch, $year, $section, $employee);

    Sanctum::actingAs($teacher);

    $opens = Ethiopia::now()->addDay()->startOfHour();

    // Same instant is not "after" — work that closes as it opens is a mistake.
    foreach ([$opens, $opens->copy()->subHour()] as $due) {
        $this->postJson('/api/v1/assignments', [
            'subject_assignment_id' => $class->id, 'title' => 'Impossible window',
            'submission_types' => ['text'],
            'available_from' => $opens->toIso8601String(),
            'due_at' => $due->toIso8601String(),
        ], branchContext($branch))->assertUnprocessable()
            ->assertJsonValidationErrors('due_at');
    }

    $created = $this->postJson('/api/v1/assignments', [
        'subject_assignment_id' => $class->id, 'title' => 'Real window',
        'submission_types' => ['text'],
        'available_from' => $opens->toIso8601String(),
        'due_at' => $opens->copy()->addDays(3)->toIso8601String(),
    ], branchContext($branch))->assertCreated()->json('data.id');

    // An edit that moves only the deadline is judged against the SAVED start,
    // so the window can never be inverted one field at a time.
    $this->putJson("/api/v1/assignments/{$created}", [
        'due_at' => $opens->copy()->subHours(2)->toIso8601String(),
    ], branchContext($branch))->assertUnprocessable()
        ->assertJsonValidationErrors('due_at');
});

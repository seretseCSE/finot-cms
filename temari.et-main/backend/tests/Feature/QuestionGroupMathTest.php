<?php

use App\Actions\EnrollStudentAction;
use App\Actions\SaveAcademicYearAction;
use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\GradeLevel;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\Quiz;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectAssignment;
use App\Models\User;
use App\Services\Lms\QuizAttemptService;
use Database\Seeders\GradeLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SubjectSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

/*
 * Question groups (passage + sub-questions) and rich math/choice content.
 * Guards: parent/child integrity (one level, same bank), group-as-unit
 * behaviour through exam papers (shuffle contiguity, draw exclusion, group
 * expansion on sync), the staff preview endpoint (fixed + sample draws,
 * keys stripped, tenant-scoped), and the sanitizer contract for math
 * markers and rich answer choices.
 */

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(GradeLevelSeeder::class);
    $this->seed(SubjectSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

// ── local fixtures (LmsTest's helpers are file-scoped, not shared) ──

/** @return array{0: User, 1: Employee} */
function qgTeacher(Branch $branch): array
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

function qgYear(Branch $branch): AcademicYear
{
    return (new SaveAcademicYearAction)->execute($branch, ['name' => '2017 E.C.', 'status' => 'active']);
}

function qgSection(Branch $branch): Section
{
    return $branch->sections()->create([
        'school_id' => $branch->school_id,
        'grade_level_id' => GradeLevel::where('code', 'G7')->value('id'),
        'name' => 'A',
    ]);
}

function qgClass(Branch $branch, AcademicYear $year, Section $section, ?Employee $teacher): SubjectAssignment
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

function qgBank(Branch $branch, ?User $creator = null): QuestionBank
{
    return QuestionBank::create([
        'school_id' => $branch->school_id,
        'branch_id' => $branch->id,
        'name' => 'Math Unit 1',
        'created_by' => $creator?->id,
    ]);
}

/** Four standalone auto-gradable questions. */
function qgQuestions(QuestionBank $bank): array
{
    return [
        $bank->questions()->create([
            'type' => 'mcq_single',
            'body' => ['stem' => '2 + 2 = ?', 'options' => [
                ['id' => 'a', 'text' => '3'], ['id' => 'b', 'text' => '4'], ['id' => 'c', 'text' => '5'],
            ]],
            'answer_key' => ['correct' => 'b'],
            'points' => 1,
        ]),
        $bank->questions()->create([
            'type' => 'mcq_multi',
            'body' => ['stem' => 'Pick the even numbers', 'options' => [
                ['id' => 'a', 'text' => '2'], ['id' => 'b', 'text' => '3'], ['id' => 'c', 'text' => '4'],
            ]],
            'answer_key' => ['correct' => ['a', 'c']],
            'points' => 2,
        ]),
        $bank->questions()->create([
            'type' => 'numeric',
            'body' => ['stem' => 'What is 10 / 4?'],
            'answer_key' => ['value' => 2.5, 'tolerance' => 0.01],
            'points' => 2,
        ]),
        $bank->questions()->create([
            'type' => 'true_false',
            'body' => ['stem' => 'A triangle has 3 sides.'],
            'answer_key' => ['correct' => true],
            'points' => 1,
        ]),
    ];
}

/** A published fixed-paper quiz on the class. */
function qgQuiz(SubjectAssignment $class, array $questions, array $settings = [], array $extra = []): Quiz
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

/** A student with a portal account, actively enrolled in the section. */
function qgStudent(Branch $branch, AcademicYear $year, Section $section, string $name = 'Sara'): Student
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

/** A group container + two published sub-questions in the bank. */
function groupWithChildren(QuestionBank $bank, ?User $creator = null): array
{
    $group = $bank->questions()->create([
        'type' => 'group',
        'body' => ['stem' => '<p>Read the passage, then answer.</p>'],
        'points' => 1,
        'created_by' => $creator?->id,
    ]);

    $first = $bank->questions()->create([
        'parent_id' => $group->id,
        'position' => 1,
        'type' => 'true_false',
        'body' => ['stem' => 'The passage mentions a river.'],
        'answer_key' => ['correct' => true],
        'points' => 2,
        'created_by' => $creator?->id,
    ]);

    $second = $bank->questions()->create([
        'parent_id' => $group->id,
        'position' => 2,
        'type' => 'numeric',
        'body' => ['stem' => 'How many days pass in the passage?'],
        'answer_key' => ['value' => 3],
        'points' => 2,
        'created_by' => $creator?->id,
    ]);

    return [$group, $first, $second];
}

// ───────────────────────── authoring groups ─────────────────────────

it('creates a group and files sub-questions under it in order', function () {
    $branch = makeBranch();
    [$teacher] = qgTeacher($branch);
    $bank = qgBank($branch, $teacher);

    Sanctum::actingAs($teacher);

    $group = $this->postJson("/api/v1/question-banks/{$bank->id}/questions", [
        'type' => 'group',
        'body' => ['stem' => '<p>Read this passage.</p>'],
    ], branchContext($branch))->assertCreated()->json('data');

    $first = $this->postJson("/api/v1/question-banks/{$bank->id}/questions", [
        'type' => 'true_false',
        'parent_id' => $group['id'],
        'body' => ['stem' => 'Statement one.'],
        'answer_key' => ['correct' => true],
    ], branchContext($branch))->assertCreated()->json('data');

    $second = $this->postJson("/api/v1/question-banks/{$bank->id}/questions", [
        'type' => 'numeric',
        'parent_id' => $group['id'],
        'body' => ['stem' => 'How many?'],
        'answer_key' => ['value' => 4],
    ], branchContext($branch))->assertCreated()->json('data');

    expect($first['parent_id'])->toBe($group['id'])
        ->and($first['position'])->toBe(1)
        ->and($second['position'])->toBe(2);

    // The bank list carries the children count on the container.
    $rows = $this->getJson("/api/v1/question-banks/{$bank->id}/questions", branchContext($branch))
        ->assertOk()->json('data');
    $container = collect($rows)->firstWhere('id', $group['id']);
    expect($container['children_count'])->toBe(2);
});

it('reorders a passage\'s sub-questions and refuses partial or foreign id lists', function () {
    $branch = makeBranch();
    [$teacher] = qgTeacher($branch);
    $bank = qgBank($branch, $teacher);

    Sanctum::actingAs($teacher);

    $group = $this->postJson("/api/v1/question-banks/{$bank->id}/questions", [
        'type' => 'group',
        'body' => ['stem' => '<p>Read this passage.</p>'],
    ], branchContext($branch))->assertCreated()->json('data');

    $ids = collect([
        ['type' => 'short_answer', 'body' => ['stem' => 'Explain briefly.'], 'answer_key' => ['accepted' => []]],
        ['type' => 'true_false', 'body' => ['stem' => 'Statement one.'], 'answer_key' => ['correct' => true]],
        ['type' => 'mcq_single', 'body' => ['stem' => 'Pick one.', 'options' => [['id' => 'a', 'text' => 'A'], ['id' => 'b', 'text' => 'B']]], 'answer_key' => ['correct' => 'a']],
    ])->map(fn (array $payload): int => $this->postJson(
        "/api/v1/question-banks/{$bank->id}/questions",
        [...$payload, 'parent_id' => $group['id']],
        branchContext($branch),
    )->assertCreated()->json('data.id'));

    // Group-by-type order: true/false, then multiple choice, then short answer.
    $reordered = [$ids[1], $ids[2], $ids[0]];
    $this->postJson("/api/v1/questions/{$group['id']}/reorder", [
        'question_ids' => $reordered,
    ], branchContext($branch))->assertOk();

    expect(Question::find($group['id'])->children()->pluck('id')->all())->toBe($reordered);

    // The list must name every child exactly once — no partial shuffles.
    $this->postJson("/api/v1/questions/{$group['id']}/reorder", [
        'question_ids' => [$ids[0]],
    ], branchContext($branch))->assertUnprocessable();

    // Only a group can be reordered.
    $this->postJson("/api/v1/questions/{$ids[0]}/reorder", [
        'question_ids' => $reordered,
    ], branchContext($branch))->assertUnprocessable();
});

it('rejects nesting groups, foreign-bank parents and answer keys on groups', function () {
    $branch = makeBranch();
    [$teacher] = qgTeacher($branch);
    $bank = qgBank($branch, $teacher);
    $otherBank = qgBank($branch, $teacher);
    [$group] = groupWithChildren($bank);
    [$foreignGroup] = groupWithChildren($otherBank);

    Sanctum::actingAs($teacher);

    // A group cannot sit inside another group.
    $this->postJson("/api/v1/question-banks/{$bank->id}/questions", [
        'type' => 'group',
        'parent_id' => $group->id,
        'body' => ['stem' => '<p>Nested?</p>'],
    ], branchContext($branch))->assertUnprocessable();

    // The parent must live in the SAME bank.
    $this->postJson("/api/v1/question-banks/{$bank->id}/questions", [
        'type' => 'true_false',
        'parent_id' => $foreignGroup->id,
        'body' => ['stem' => 'Wrong bank.'],
        'answer_key' => ['correct' => true],
    ], branchContext($branch))->assertUnprocessable();

    // A group holds no answer key of its own.
    $this->postJson("/api/v1/question-banks/{$bank->id}/questions", [
        'type' => 'group',
        'body' => ['stem' => '<p>Passage.</p>'],
        'answer_key' => ['correct' => true],
    ], branchContext($branch))->assertUnprocessable();
});

it('deletes a group with its sub-questions, retiring the family when referenced', function () {
    $branch = makeBranch();
    [$teacher, $employee] = qgTeacher($branch);
    $bank = qgBank($branch, $teacher);

    Sanctum::actingAs($teacher);

    // Unreferenced: the whole family deletes.
    [$group, $a, $b] = groupWithChildren($bank, $teacher);
    $this->deleteJson("/api/v1/questions/{$group->id}", [], branchContext($branch))->assertOk();
    expect(Question::find($group->id))->toBeNull()
        ->and(Question::find($a->id))->toBeNull()
        ->and(Question::find($b->id))->toBeNull();

    // Referenced by a quiz: the family retires instead.
    [$group2, $c, $d] = groupWithChildren($bank, $teacher);
    $year = qgYear($branch);
    $class = qgClass($branch, $year, qgSection($branch), $employee);
    qgQuiz($class, [$c, $d]);

    $this->deleteJson("/api/v1/questions/{$group2->id}", [], branchContext($branch))->assertOk();
    expect(Question::find($group2->id)->status)->toBe('retired')
        ->and(Question::find($c->id)->status)->toBe('retired')
        ->and(Question::find($d->id)->status)->toBe('retired');
});

// ─────────────── math markers & rich answer choices ───────────────

it('keeps validated math markers and sanitizes rich option texts', function () {
    $branch = makeBranch();
    [$teacher] = qgTeacher($branch);
    $bank = qgBank($branch, $teacher);

    Sanctum::actingAs($teacher);

    $question = $this->postJson("/api/v1/question-banks/{$bank->id}/questions", [
        'type' => 'mcq_single',
        'body' => [
            'stem' => '<p>Evaluate <span data-math="\int_0^1 2x\,dx" onclick="evil()" class="x">ignored</span>.</p>',
            'options' => [
                ['id' => 'a', 'text' => '<p><span data-math="\frac{1}{2}" data-display="block"></span></p>'],
                ['id' => 'b', 'text' => '<p><b>1</b><script>alert(1)</script></p>'],
                // Plain text with a raw "<" must survive untouched.
                ['id' => 'c', 'text' => 'x < 5'],
            ],
        ],
        'answer_key' => ['correct' => 'b'],
    ], branchContext($branch))->assertCreated()->json('data');

    // The stem's math marker survives with ONLY its LaTeX + display flag.
    expect($question['body']['stem'])
        ->toContain('data-math="\int_0^1 2x\,dx"')
        ->not->toContain('onclick')
        ->not->toContain('class="x"');

    $options = collect($question['body']['options'])->keyBy('id');
    expect($options['a']['text'])->toContain('data-math="\frac{1}{2}"')
        ->and($options['a']['text'])->toContain('data-display="block"')
        ->and($options['b']['text'])->toContain('<b>1</b>')
        ->and($options['b']['text'])->not->toContain('<script>')
        ->and($options['c']['text'])->toBe('x < 5');
});

// ───────────────── papers: contiguity, draws, sync ─────────────────

it('keeps a group contiguous through shuffled papers and never seats the container', function () {
    $branch = makeBranch();
    [$teacher, $employee] = qgTeacher($branch);
    $year = qgYear($branch);
    $section = qgSection($branch);
    $class = qgClass($branch, $year, $section, $employee);
    $bank = qgBank($branch, $teacher);

    [$group, $first, $second] = groupWithChildren($bank);
    [$mcq, $multi, $numeric, $tf] = qgQuestions($bank);

    $quiz = qgQuiz(
        $class,
        [$mcq, $first, $second, $multi, $numeric, $tf],
        ['shuffle_questions' => true, 'duration_minutes' => 30],
    );
    // The container itself on the paper must be ignored, never served.
    $quiz->quizQuestions()->create(['question_id' => $group->id, 'sort_order' => 99]);

    $engine = app(QuizAttemptService::class);

    foreach (range(1, 8) as $i) {
        $student = qgStudent($branch, $year, $section, "Taker{$i}");
        $attempt = $engine->start($quiz, $student->user);

        $ids = collect($attempt->question_ids)->pluck('id');
        expect($ids)->not->toContain($group->id);

        // Siblings sit next to each other, in authored order.
        $posFirst = $ids->search($first->id);
        $posSecond = $ids->search($second->id);
        expect($posSecond)->toBe($posFirst + 1);

        // The player payload names the group; its passage ships once.
        $paper = $engine->paper($attempt);
        $rows = collect($paper)->keyBy('question_id');
        expect($rows[$first->id]['group_id'])->toBe($group->id)
            ->and($rows[$mcq->id]['group_id'])->toBeNull();

        $groups = $engine->groupStems($paper);
        expect($groups)->toHaveKey($group->id)
            ->and($groups[$group->id]['stem'])->toContain('Read the passage');
    }
});

it('excludes groups and their members from random draws', function () {
    $branch = makeBranch();
    [$teacher, $employee] = qgTeacher($branch);
    $year = qgYear($branch);
    $class = qgClass($branch, $year, qgSection($branch), $employee);
    $bank = qgBank($branch, $teacher);

    groupWithChildren($bank);
    qgQuestions($bank); // 4 standalone questions

    Sanctum::actingAs($teacher);

    // Only the 4 standalone questions are drawable: asking for 5 must fail…
    $quiz = qgQuiz($class, [], [], ['status' => 'draft', 'draw' => [
        ['question_bank_id' => $bank->id, 'count' => 5],
    ]]);
    $this->postJson("/api/v1/quizzes/{$quiz->id}/publish", [], branchContext($branch))
        ->assertUnprocessable();

    // …while 4 publishes and every sampled paper stays group-free.
    $quiz->update(['draw' => [['question_bank_id' => $bank->id, 'count' => 4]]]);
    $this->postJson("/api/v1/quizzes/{$quiz->id}/publish", [], branchContext($branch))->assertOk();

    $sample = app(QuizAttemptService::class)->samplePaper($quiz->refresh());
    expect($sample)->toHaveCount(4)
        ->and(Question::whereIn('id', $sample->pluck('id'))->pluck('parent_id')->filter())->toBeEmpty();
});

it('expands a picked group into its published sub-questions on save', function () {
    $branch = makeBranch();
    [$teacher, $employee] = qgTeacher($branch);
    $year = qgYear($branch);
    $class = qgClass($branch, $year, qgSection($branch), $employee);
    $bank = qgBank($branch, $teacher);
    [$group, $first, $second] = groupWithChildren($bank);
    [$mcq] = qgQuestions($bank);

    Sanctum::actingAs($teacher);

    $quiz = $this->postJson('/api/v1/quizzes', [
        'subject_assignment_ids' => [$class->id],
        'kind' => 'exam',
        'title' => 'Passage exam',
        'settings' => ['attempts_allowed' => 1],
        'questions' => [
            ['question_id' => $mcq->id],
            // The GROUP id stands in for its whole sibling block.
            ['question_id' => $group->id],
        ],
    ], branchContext($branch))->assertCreated()->json('data');

    $rows = $this->getJson("/api/v1/quizzes/{$quiz['id']}", branchContext($branch))
        ->assertOk()->json('data');

    expect(collect($rows['questions'])->pluck('id')->all())
        ->toBe([$mcq->id, $first->id, $second->id]);
    expect($rows['groups'])->toHaveKey((string) $group->id);
});

// ─────────────────────────── preview lane ───────────────────────────

it('serves a key-free staff preview for fixed papers, published included', function () {
    $branch = makeBranch();
    [$teacher, $employee] = qgTeacher($branch);
    $year = qgYear($branch);
    $class = qgClass($branch, $year, qgSection($branch), $employee);
    $bank = qgBank($branch, $teacher);
    [$group, $first, $second] = groupWithChildren($bank);
    [$mcq] = qgQuestions($bank);

    $quiz = qgQuiz($class, [$mcq, $first, $second]); // already published

    Sanctum::actingAs($teacher);

    $data = $this->getJson("/api/v1/quizzes/{$quiz->id}/preview", branchContext($branch))
        ->assertOk()->json('data');

    expect($data['is_sample'])->toBeFalse()
        ->and(collect($data['questions'])->pluck('id')->all())->toBe([$mcq->id, $first->id, $second->id])
        ->and(collect($data['questions'])->first())->not->toHaveKey('answer_key')
        ->and($data['groups'])->toHaveKey((string) $group->id);
});

it('never leaks a preview across schools', function () {
    $branch = makeBranch();
    [, $employee] = qgTeacher($branch);
    $year = qgYear($branch);
    $class = qgClass($branch, $year, qgSection($branch), $employee);
    $bank = qgBank($branch);
    $quiz = qgQuiz($class, qgQuestions($bank));

    $foreign = makeBranch('AA-0002');
    [$outsider] = qgTeacher($foreign);

    Sanctum::actingAs($outsider);

    $this->getJson("/api/v1/quizzes/{$quiz->id}/preview", branchContext($foreign))
        ->assertForbidden();
});

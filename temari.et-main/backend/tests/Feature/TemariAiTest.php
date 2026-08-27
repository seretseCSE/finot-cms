<?php

use App\Actions\EnrollStudentAction;
use App\Actions\SaveAcademicYearAction;
use App\Ai\AiContext;
use App\Ai\Tools\Chat\ChatRecipientsTool;
use App\Ai\Tools\Family\StudentExamHistoryTool;
use App\Ai\Tools\Leadership\ClassCatalogTool;
use App\Ai\Tools\Platform\CreateMockExamTool;
use App\Ai\Tools\Platform\ExamPrepCatalogTool;
use App\Ai\Tools\Teacher\CreateExamTool;
use App\Ai\Tools\Teacher\DraftQuestionsTool;
use App\Ai\Tools\Teacher\MyQuestionBanksTool;
use App\Ai\Tools\Teacher\UpdateExamTool;
use App\Enums\AiLane;
use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\AiFeedback;
use App\Models\AiSubscription;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\GatewayTransaction;
use App\Models\GradeLevel;
use App\Models\ParentProfile;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\Quiz;
use App\Models\School;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentGuardian;
use App\Models\Subject;
use App\Models\SubjectAssignment;
use App\Models\User;
use App\Services\Ai\AiUsageService;
use App\Services\Ai\ChatAttachments;
use Database\Seeders\GradeLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SubjectSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

/** A user with their own student row (the student lane). */
function aiStudent(): User
{
    $user = User::factory()->create();
    $branch = makeBranch('AA-'.random_int(1000, 9999));
    $branch->students()->create([
        'school_id' => $branch->school_id,
        'user_id' => $user->id,
        'first_name' => 'Abel', 'father_name' => 'Bekele', 'gender' => 'male',
    ]);

    return $user;
}

// ── Context & assistants ───────────────────────────────────────────────────

it('offers the family assistant with the free quota to a student account', function () {
    Sanctum::actingAs(aiStudent());

    $response = $this->getJson('/api/v1/ai/context')->assertOk();

    $assistants = collect($response->json('data.assistants'));
    $family = $assistants->firstWhere('surface', 'family');
    expect($family['lanes'])->toContain('student')
        ->and($family['entitlement']['plan'])->toBe('free')
        ->and($family['entitlement']['daily_limit'])->toBe((int) config('temari-ai.quotas.free'));
});

it('offers the family assistant with the parent hat to a guardian account', function () {
    $user = User::factory()->create();
    ParentProfile::create(['user_id' => $user->id]);
    Sanctum::actingAs($user);

    $assistants = collect($this->getJson('/api/v1/ai/context')->assertOk()->json('data.assistants'));

    expect($assistants->firstWhere('surface', 'family')['lanes'])->toContain('parent');
});

it('offers the school assistant only inside the matching workspace context', function () {
    $branch = makeBranch();
    $teacher = memberOf($branch, Role::Teacher);
    Sanctum::actingAs($teacher);

    // No context headers → no school assistant.
    $bare = collect($this->getJson('/api/v1/ai/context')->assertOk()->json('data.assistants'));
    expect($bare->pluck('surface'))->not->toContain('school');

    $scoped = collect($this->getJson('/api/v1/ai/context', branchContext($branch))
        ->assertOk()->json('data.assistants'));
    $school = $scoped->firstWhere('surface', 'school');
    expect($school['lanes'])->toContain('teacher')
        ->and($school['entitlement']['plan'])->toBe('staff_free');
});

it('composes every staff hat into ONE school assistant — never a picker', function () {
    $branch = makeBranch();
    $director = memberOf($branch, Role::Director);
    Sanctum::actingAs($director);

    $assistants = collect($this->getJson('/api/v1/ai/context', branchContext($branch))
        ->assertOk()->json('data.assistants'));

    // Exactly one school assistant, its lane set composed from the kernel:
    // leadership first (the stored primary), registrar folded in — never a
    // separate assistant per hat.
    expect($assistants->where('surface', 'school'))->toHaveCount(1);
    $school = $assistants->firstWhere('surface', 'school');
    expect($school['lanes'][0])->toBe('leadership')
        ->and($school['lanes'])->toContain('registrar');

    // Creating without any body lands on the workspace default: the school
    // surface, stored under its primary lane.
    $created = $this->postJson('/api/v1/ai/conversations', [], branchContext($branch))
        ->assertCreated()->json('data');
    expect($created['surface'])->toBe('school')
        ->and($created['lane'])->toBe('leadership')
        ->and($created['school_id'])->toBe($branch->school_id);
});

// ── Conversation CRUD & self-scoping ───────────────────────────────────────

it('creates, renames, pins and deletes a conversation — strictly self-scoped', function () {
    $user = aiStudent();
    Sanctum::actingAs($user);

    $created = $this->postJson('/api/v1/ai/conversations', ['surface' => 'family'])
        ->assertCreated()->json('data');

    expect($created['lane'])->toBe('student')
        ->and($created['surface'])->toBe('family')
        ->and($created['title'])->toBe('New chat');

    $this->getJson('/api/v1/ai/conversations')
        ->assertOk()
        ->assertJsonPath('data.0.id', $created['id']);

    $this->patchJson("/api/v1/ai/conversations/{$created['id']}", ['title' => 'Algebra help', 'pinned' => true])
        ->assertOk()
        ->assertJsonPath('data.title', 'Algebra help')
        ->assertJsonPath('data.pinned', true);

    // A different user can neither read, edit nor delete it.
    Sanctum::actingAs(User::factory()->create());
    $this->getJson("/api/v1/ai/conversations/{$created['id']}/messages")->assertNotFound();
    $this->patchJson("/api/v1/ai/conversations/{$created['id']}", ['title' => 'X'])->assertNotFound();
    $this->deleteJson("/api/v1/ai/conversations/{$created['id']}")->assertNotFound();

    Sanctum::actingAs($user);
    $this->deleteJson("/api/v1/ai/conversations/{$created['id']}")->assertOk();
    $this->getJson('/api/v1/ai/conversations')->assertOk()->assertJsonCount(0, 'data');
});

it('refuses a surface the user does not hold (legacy lane params included)', function () {
    Sanctum::actingAs(aiStudent());

    $this->postJson('/api/v1/ai/conversations', ['surface' => 'school'])->assertForbidden();
    // Old deep links send `lane` — only its surface counts, same refusal.
    $this->postJson('/api/v1/ai/conversations', ['lane' => 'leadership'])->assertForbidden();
});

it('freezes the workspace context on staff conversations', function () {
    $branch = makeBranch();
    $teacher = memberOf($branch, Role::Teacher);
    Sanctum::actingAs($teacher);

    $created = $this->postJson('/api/v1/ai/conversations', ['lane' => 'teacher'], branchContext($branch))
        ->assertCreated()->json('data');

    expect($created['school_id'])->toBe($branch->school_id)
        ->and($created['branch_id'])->toBe($branch->id);
});

it('rejects a parent conversation focused on an unlinked child', function () {
    $user = User::factory()->create();
    ParentProfile::create(['user_id' => $user->id]);
    Sanctum::actingAs($user);

    $branch = makeBranch();
    $stranger = $branch->students()->create([
        'school_id' => $branch->school_id,
        'first_name' => 'Sara', 'father_name' => 'Kebede', 'gender' => 'female',
    ]);

    $this->postJson('/api/v1/ai/conversations', ['lane' => 'parent', 'student_id' => $stranger->id])
        ->assertForbidden();
});

// ── Quotas & entitlement enforcement ───────────────────────────────────────

it('blocks prompts with 402 when the plan has no allowance', function () {
    config()->set('temari-ai.quotas.free', 0);

    $user = aiStudent();
    Sanctum::actingAs($user);

    $conversation = $this->postJson('/api/v1/ai/conversations', ['lane' => 'student'])->json('data');

    $this->postJson("/api/v1/ai/conversations/{$conversation['id']}/messages", ['content' => 'Hi'])
        ->assertStatus(402);
});

it('blocks prompts with 429 once the daily quota is spent', function () {
    config()->set('temari-ai.quotas.free', 1);

    $user = aiStudent();
    Sanctum::actingAs($user);

    app(AiUsageService::class)->recordMessage($user);

    $conversation = $this->postJson('/api/v1/ai/conversations', ['lane' => 'student'])->json('data');

    $this->postJson("/api/v1/ai/conversations/{$conversation['id']}/messages", ['content' => 'Hi'])
        ->assertStatus(429);
});

it('reports premium once an AI subscription is active', function () {
    $user = aiStudent();
    AiSubscription::create([
        'user_id' => $user->id, 'plan' => 'monthly', 'amount' => 199,
        'status' => 'active', 'starts_at' => now(), 'ends_at' => now()->addDays(20),
    ]);
    Sanctum::actingAs($user);

    $assistants = collect($this->getJson('/api/v1/ai/context')->assertOk()->json('data.assistants'));

    expect($assistants->firstWhere('surface', 'family')['entitlement']['plan'])->toBe('premium');
});

// ── Subscription fulfilment (gateway payable) ─────────────────────────────

it('activates and extends a subscription when the gateway settles', function () {
    $user = User::factory()->create();

    $subscription = AiSubscription::create([
        'user_id' => $user->id, 'plan' => 'monthly', 'amount' => 199, 'status' => 'pending_payment',
    ]);

    $transaction = GatewayTransaction::create([
        'tx_ref' => GatewayTransaction::allocateRef(), 'gateway' => 'chapa',
        'purpose' => 'ai_subscription', 'payable_type' => AiSubscription::class,
        'payable_id' => $subscription->id, 'user_id' => $user->id,
        'amount' => 199, 'currency' => 'ETB', 'status' => 'pending',
    ]);

    $subscription->gatewayPaid($transaction);
    $subscription->refresh();

    expect($subscription->status)->toBe('active')
        ->and(now()->diffInDays($subscription->ends_at))->toBeGreaterThan(28);

    // A renewal extends from the current end — early renewal loses nothing.
    $renewal = AiSubscription::create([
        'user_id' => $user->id, 'plan' => 'monthly', 'amount' => 199, 'status' => 'pending_payment',
    ]);
    $renewal->gatewayPaid($transaction);

    expect(now()->diffInDays($renewal->refresh()->ends_at))->toBeGreaterThan(55);
});

// ── School Plan grant (platform lane) ─────────────────────────────────────

it('lets platform staff grant and revoke the School AI plan', function () {
    $branch = makeBranch();
    $school = School::find($branch->school_id);

    Sanctum::actingAs(platformAdmin());

    $this->postJson("/api/v1/schools/{$school->id}/ai-plan", ['months' => 3])
        ->assertOk()
        ->assertJsonPath('data.active', true);

    expect($school->refresh()->aiPlanActive())->toBeTrue();

    // Staff of the school now resolve to the `school` plan tier.
    $teacher = memberOf($branch, Role::Teacher);
    Sanctum::actingAs($teacher);
    $assistants = collect($this->getJson('/api/v1/ai/context', branchContext($branch))->json('data.assistants'));
    expect($assistants->firstWhere('surface', 'school')['entitlement']['plan'])->toBe('school');

    // Revoke ends it; school-side users can never grant.
    Sanctum::actingAs(platformAdmin());
    $this->deleteJson("/api/v1/schools/{$school->id}/ai-plan")->assertOk();
    expect($school->refresh()->aiPlanActive())->toBeFalse();

    Sanctum::actingAs($teacher);
    $this->postJson("/api/v1/schools/{$school->id}/ai-plan", ['months' => 3], branchContext($branch))
        ->assertForbidden();
});

// ── Feedback ───────────────────────────────────────────────────────────────

it('accepts feedback only on messages in the caller\'s own conversations', function () {
    $user = aiStudent();
    Sanctum::actingAs($user);

    $conversation = $this->postJson('/api/v1/ai/conversations', ['lane' => 'student'])->json('data');

    $messageId = (string) Str::uuid7();
    DB::table('agent_conversation_messages')->insert([
        'id' => $messageId, 'conversation_id' => $conversation['uuid'],
        'user_id' => $user->id, 'agent' => 'test', 'role' => 'assistant',
        'content' => 'Answer', 'attachments' => '[]', 'tool_calls' => '[]',
        'tool_results' => '[]', 'usage' => '[]', 'meta' => '[]',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->postJson('/api/v1/ai/feedback', ['message_id' => $messageId, 'rating' => 'down', 'comment' => 'off'])
        ->assertOk();

    expect(AiFeedback::query()->where('message_id', $messageId)->value('rating'))->toBe('down');

    Sanctum::actingAs(User::factory()->create());
    $this->postJson('/api/v1/ai/feedback', ['message_id' => $messageId, 'rating' => 'up'])
        ->assertNotFound();
});

// ── Embedded AI actions ────────────────────────────────────────────────────

it('refuses AI actions for accounts without a matching staff lane', function () {
    Sanctum::actingAs(aiStudent());

    $this->postJson('/api/v1/ai/actions', ['action' => 'quiz_questions', 'params' => []])
        ->assertStatus(422); // No school workspace at all.

    // A parent forging staff context headers: the kernel strips the invalid
    // context (a client can only narrow itself), so no school resolves — 422.
    $branch = makeBranch();
    $parentUser = User::factory()->create();
    ParentProfile::create(['user_id' => $parentUser->id]);
    Sanctum::actingAs($parentUser);

    $this->postJson('/api/v1/ai/actions', ['action' => 'quiz_questions', 'params' => []], branchContext($branch))
        ->assertStatus(422);
});

it('keeps the transcript endpoint self-scoped and user/assistant only', function () {
    $user = aiStudent();
    Sanctum::actingAs($user);

    $conversation = $this->postJson('/api/v1/ai/conversations', ['lane' => 'student'])->json('data');

    foreach ([['user', 'Hi'], ['assistant', 'Hello!'], ['tool', 'secret tool payload']] as [$role, $content]) {
        DB::table('agent_conversation_messages')->insert([
            'id' => (string) Str::uuid7(), 'conversation_id' => $conversation['uuid'],
            'user_id' => $user->id, 'agent' => 'test', 'role' => $role,
            'content' => $content, 'attachments' => '[]', 'tool_calls' => '[]',
            'tool_results' => '[]', 'usage' => '[]', 'meta' => '[]',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    $messages = $this->getJson("/api/v1/ai/conversations/{$conversation['id']}/messages")
        ->assertOk()->json('data.messages');

    expect(collect($messages)->pluck('role')->unique()->sort()->values()->all())
        ->toBe(['assistant', 'user']);
});

// ── Attachments ────────────────────────────────────────────────────────────

/** Insert one transcript row and return its id. */
function aiMessageRow(string $conversationUuid, int $userId, string $role, string $content, array $attachments = []): string
{
    $id = (string) Str::uuid7();
    DB::table('agent_conversation_messages')->insert([
        'id' => $id, 'conversation_id' => $conversationUuid,
        'user_id' => $userId, 'agent' => 'test', 'role' => $role,
        'content' => $content, 'attachments' => json_encode($attachments),
        'tool_calls' => '[]', 'tool_results' => '[]', 'usage' => '[]', 'meta' => '[]',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return $id;
}

it('lists attachment metadata on the transcript without the payload', function () {
    $user = aiStudent();
    Sanctum::actingAs($user);

    $conversation = $this->postJson('/api/v1/ai/conversations', ['lane' => 'student'])->json('data');

    aiMessageRow($conversation['uuid'], $user->id, 'user', 'See these', [
        ['type' => 'base64-image', 'name' => 'homework.png', 'base64' => base64_encode('png-bytes'), 'mime' => 'image/png'],
        ['type' => 'base64-document', 'name' => 'notes.pdf', 'base64' => base64_encode('pdf-bytes'), 'mime' => 'application/pdf'],
    ]);

    $messages = $this->getJson("/api/v1/ai/conversations/{$conversation['id']}/messages")
        ->assertOk()->json('data.messages');

    $attachments = $messages[0]['attachments'];
    expect($attachments)->toHaveCount(2)
        ->and($attachments[0])->toMatchArray(['index' => 0, 'name' => 'homework.png', 'mime' => 'image/png', 'kind' => 'image'])
        ->and($attachments[1])->toMatchArray(['index' => 1, 'name' => 'notes.pdf', 'mime' => 'application/pdf', 'kind' => 'file'])
        ->and(json_encode($attachments))->not->toContain(base64_encode('png-bytes'));
});

it('serves attachment bytes only to the conversation owner', function () {
    $user = aiStudent();
    Sanctum::actingAs($user);

    $conversation = $this->postJson('/api/v1/ai/conversations', ['lane' => 'student'])->json('data');

    $messageId = aiMessageRow($conversation['uuid'], $user->id, 'user', 'Check this', [
        ['type' => 'base64-image', 'name' => 'photo.png', 'base64' => base64_encode('the-image-bytes'), 'mime' => 'image/png'],
    ]);

    $response = $this->get("/api/v1/ai/conversations/{$conversation['id']}/messages/{$messageId}/attachments/0")
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
    expect($response->getContent())->toBe('the-image-bytes');

    $this->get("/api/v1/ai/conversations/{$conversation['id']}/messages/{$messageId}/attachments/5")
        ->assertNotFound();

    Sanctum::actingAs(User::factory()->create());
    $this->get("/api/v1/ai/conversations/{$conversation['id']}/messages/{$messageId}/attachments/0")
        ->assertNotFound();
});

it('rejects unsupported attachment types on send', function () {
    $user = aiStudent();
    Sanctum::actingAs($user);

    $conversation = $this->postJson('/api/v1/ai/conversations', ['lane' => 'student'])->json('data');

    $this->postJson("/api/v1/ai/conversations/{$conversation['id']}/messages", [
        'content' => 'Look at this',
        'attachments' => [UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')],
    ])->assertUnprocessable()->assertJsonValidationErrors(['attachments.0']);
});

it('extracts readable text from office files', function () {
    $extractor = new ChatAttachments;

    // A minimal real .docx built in-memory: zip with word/document.xml.
    $docx = tempnam(sys_get_temp_dir(), 'ai').'.docx';
    $zip = new ZipArchive;
    $zip->open($docx, ZipArchive::CREATE);
    $zip->addFromString('word/document.xml',
        '<w:document><w:body><w:p><w:r><w:t>Lesson plan for grade 9</w:t></w:r></w:p>'
        .'<w:p><w:r><w:t>Second paragraph</w:t></w:r></w:p></w:body></w:document>');
    $zip->close();

    $text = $extractor->extractOfficeText($docx, 'docx');
    expect($text)->toContain('Lesson plan for grade 9')
        ->and($text)->toContain('Second paragraph');

    // A minimal .xlsx: shared strings + one sheet.
    $xlsx = tempnam(sys_get_temp_dir(), 'ai').'.xlsx';
    $zip = new ZipArchive;
    $zip->open($xlsx, ZipArchive::CREATE);
    $zip->addFromString('xl/sharedStrings.xml', '<sst><si><t>Student</t></si><si><t>Abel Bekele</t></si></sst>');
    $zip->addFromString('xl/worksheets/sheet1.xml',
        '<worksheet><sheetData><row><c t="s"><v>0</v></c><c><v>92.5</v></c></row>'
        .'<row><c t="s"><v>1</v></c><c><v>88</v></c></row></sheetData></worksheet>');
    $zip->close();

    $text = $extractor->extractOfficeText($xlsx, 'xlsx');
    expect($text)->toContain('Student')->and($text)->toContain('Abel Bekele')->and($text)->toContain('92.5');

    @unlink($docx);
    @unlink($xlsx);
});

it('routes prompts with attachments to the attachment-capable model', function () {
    config([
        'temari-ai.model' => 'text-model',
        'temari-ai.attachment_model' => 'vision-model',
    ]);

    expect(ChatAttachments::modelFor([]))->toBe('text-model');

    $image = (new ChatAttachments)->wrap(UploadedFile::fake()->image('homework.png'));
    expect(ChatAttachments::modelFor([$image]))->toBe('vision-model');
});

it('guards regenerate: owner-only and only with an exchange to redo', function () {
    $user = aiStudent();
    Sanctum::actingAs($user);

    $conversation = $this->postJson('/api/v1/ai/conversations', ['lane' => 'student'])->json('data');

    // Nothing sent yet → nothing to regenerate.
    $this->postJson("/api/v1/ai/conversations/{$conversation['id']}/messages/regenerate")
        ->assertStatus(422);

    // A stranger never sees the conversation.
    Sanctum::actingAs(User::factory()->create());
    $this->postJson("/api/v1/ai/conversations/{$conversation['id']}/messages/regenerate")
        ->assertNotFound();
});

// ── Family tools ───────────────────────────────────────────────────────────

it('reviews a finished attempt per question through the exam-history tool', function () {
    $user = aiStudent();
    $student = $user->studentProfile()->first();

    $bank = QuestionBank::create(['name' => 'Mock pool', 'created_by' => $user->id]);
    $question = $bank->questions()->create([
        'type' => 'mcq_single',
        'body' => ['stem' => '<p>2 + 2 = ?</p>', 'options' => [
            ['id' => 'a', 'text' => '3'], ['id' => 'b', 'text' => '4'],
        ]],
        'answer_key' => ['correct' => 'b'],
        'points' => 2,
    ]);

    $quiz = Quiz::create([
        'is_platform' => true, 'kind' => 'mock', 'title' => 'Maths Mock',
        'settings' => ['results_policy' => 'immediately', 'reveal_answers' => true],
        'status' => 'published', 'published_at' => now(), 'created_by' => $user->id,
    ]);

    $attempt = $quiz->attempts()->create([
        'user_id' => $user->id,
        'student_id' => $student->id,
        'attempt_number' => 1,
        'status' => 'graded',
        'started_at' => now()->subHour(),
        'seed' => 1,
        // The engine's frozen-paper entry format (QuizAttemptService::start).
        'question_ids' => [['id' => $question->id, 'points' => 2.0, 'part' => null]],
        'max_score' => 2,
    ]);
    // Grading fields are not mass-assignable — the engine forceFills them.
    $attempt->forceFill([
        'score' => 0,
        'submitted_at' => now()->subMinutes(30),
        'graded_at' => now()->subMinutes(30),
    ])->save();
    $attempt->answers()->create([
        'question_id' => $question->id,
        'answer' => 'a',
        'auto_score' => 0,
        'answered_at' => now()->subMinutes(40),
    ]);

    $tool = new StudentExamHistoryTool(new AiContext(user: $user, lane: AiLane::Student));

    // History list: the graded attempt shows with its score.
    $history = json_decode((string) $tool->handle(new Request([])), true);
    expect($history['ok'])->toBeTrue()
        ->and($history['data']['attempts'][0]['attempt_id'])->toBe($attempt->id)
        ->and($history['data']['attempts'][0]['score'])->toEqual(0);

    // Per-question review: stem as plain text, miss flagged, key revealed.
    $review = json_decode((string) $tool->handle(new Request(['attempt_id' => $attempt->id])), true);
    expect($review['ok'])->toBeTrue();
    $row = $review['data']['questions'][0];
    expect($row['question'])->toBe('2 + 2 = ?')
        ->and($row['points'])->toEqual(2)
        ->and($row['student_answer'])->toBe('a')
        ->and($row['correct'])->toBeFalse()
        ->and($row['answer_key'])->toBe(['correct' => 'b']);

    // Answer key stays hidden when the teacher has not released it.
    $quiz->update(['settings' => ['results_policy' => 'immediately', 'reveal_answers' => false]]);
    $hidden = json_decode((string) $tool->handle(new Request(['attempt_id' => $attempt->id])), true);
    expect($hidden['ok'])->toBeTrue()
        ->and($hidden['data']['questions'][0])->not->toHaveKey('answer_key');
});

// ── Exam builder (teacher lane write tools) ────────────────────────────────

/** @return array{0: User, 1: SubjectAssignment, 2: Branch} */
function aiTeacherClass(): array
{
    test()->seed(GradeLevelSeeder::class);
    test()->seed(SubjectSeeder::class);

    $branch = makeBranch('AA-'.random_int(1000, 9999));
    $year = (new SaveAcademicYearAction)->execute($branch, ['name' => '2017 E.C.', 'status' => 'active']);

    $section = $branch->sections()->create([
        'school_id' => $branch->school_id,
        'grade_level_id' => GradeLevel::where('code', 'G7')->value('id'),
        'name' => 'A',
    ]);

    $user = memberOf($branch, Role::Teacher);
    $employee = Employee::create([
        'user_id' => $user->id, 'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => 'Alemu', 'father_name' => 'Bekele', 'gender' => 'male',
    ]);

    $assignment = SubjectAssignment::create([
        'school_id' => $branch->school_id,
        'branch_id' => $branch->id,
        'academic_year_id' => $year->id,
        'section_id' => $section->id,
        'subject_id' => Subject::where('code', 'MATH')->value('id'),
        'term_id' => $year->terms()->first()->id,
        'employee_id' => $employee->id,
        'periods_per_week' => 5,
    ]);

    return [$user, $assignment, $branch];
}

it('builds a whole draft exam in one CreateExamTool call — bank, published questions, targets, order', function () {
    [$teacher, $assignment, $branch] = aiTeacherClass();

    // One pre-existing published question to mix in.
    $bank = QuestionBank::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'name' => 'Math Unit 1', 'created_by' => $teacher->id,
    ]);
    $existing = $bank->questions()->create([
        'type' => 'mcq_single',
        'body' => ['stem' => '2 + 2 = ?', 'options' => [['id' => 'a', 'text' => '3'], ['id' => 'b', 'text' => '4']]],
        'answer_key' => ['correct' => 'b'],
        'points' => 1,
        'status' => 'published',
    ]);

    $context = new AiContext(user: $teacher, lane: AiLane::Teacher, school: $branch->school, branch: $branch);

    $result = json_decode((string) (new CreateExamTool($context))->handle(new Request([
        'title' => 'Mathematics — Chapter 1 Test',
        'kind' => 'exam',
        'subject_id' => $assignment->subject_id,
        'section_ids' => [$assignment->section_id],
        'duration_minutes' => 45,
        'instructions' => 'Answer all questions.',
        'bank_name' => 'Chapter 1 — AI drafts',
        'new_questions' => [
            ['type' => 'mcq_single', 'stem' => '5 × 3 = ?', 'options' => [
                ['id' => 'a', 'text' => '15'], ['id' => 'b', 'text' => '8'], ['id' => 'c', 'text' => '53'],
            ], 'correct' => 'a', 'topic' => 'Chapter 1', 'difficulty' => 'easy'],
            // Over the tool wire booleans arrive as strings — "false" must
            // land as false, never truthy.
            ['type' => 'true_false', 'stem' => '7 is greater than 10.', 'correct' => 'false', 'topic' => 'Chapter 1'],
        ],
        'question_ids' => [$existing->id],
    ])), true);

    expect($result['ok'])->toBeTrue()
        ->and($result['data']['status'])->toBe('draft')
        ->and($result['data']['question_count'])->toBe(3)
        ->and($result['data']['link'])->toBe('/lms/exams/'.$result['data']['quiz_id']);

    $quiz = Quiz::findOrFail($result['data']['quiz_id']);
    expect($quiz->status->value)->toBe('draft')
        ->and($quiz->kind)->toBe('exam')
        ->and($quiz->subject_assignment_id)->toBe($assignment->id)
        ->and($quiz->setting('duration_minutes'))->toBe(45)
        ->and($quiz->targets()->count())->toBe(1)
        ->and($quiz->quizQuestions()->count())->toBe(3)
        // Existing picks come first, then the new drafts in authored order.
        ->and($quiz->quizQuestions()->orderBy('sort_order')->first()->question_id)->toBe($existing->id);

    // The new bank carries the class identity; AI questions land PUBLISHED so
    // the exam is ready to publish once the teacher reviews it.
    $newBank = QuestionBank::where('name', 'Chapter 1 — AI drafts')->firstOrFail();
    expect($newBank->subject_id)->toBe($assignment->subject_id)
        ->and($newBank->grade_level_id)->toBe($assignment->section->grade_level_id)
        ->and($newBank->questions()->where('status', 'published')->count())->toBe(2)
        ->and($newBank->questions()->where('type', 'true_false')->first()->answer_key)->toBe(['correct' => false])
        ->and($newBank->topics)->toContain('Chapter 1');

    // MyQuestionBanksTool sees both banks with their counts.
    $banks = json_decode((string) (new MyQuestionBanksTool($context))->handle(new Request([])), true);
    expect($banks['ok'])->toBeTrue();
    $row = collect($banks['data']['banks'])->firstWhere('question_bank_id', $newBank->id);
    expect($row['draft_questions'])->toBe(0)->and($row['published_questions'])->toBe(2);
});

it('saves AI passage groups and matching drafts, and expands a group id onto an exam paper', function () {
    [$teacher, $assignment, $branch] = aiTeacherClass();
    $context = new AiContext(user: $teacher, lane: AiLane::Teacher, school: $branch->school, branch: $branch);

    $drafted = json_decode((string) (new DraftQuestionsTool($context))->handle(new Request([
        'bank_name' => 'Reading — AI drafts',
        'questions' => [
            [
                'type' => 'group',
                'stem' => 'Abebe walks to school every morning with his sister Almaz. The walk takes thirty minutes.',
                'topic' => 'Unit 2',
                'sub_questions' => [
                    ['type' => 'mcq_single', 'stem' => 'Who walks with Abebe?', 'options' => [
                        ['id' => 'a', 'text' => 'Almaz'], ['id' => 'b', 'text' => 'His father'],
                    ], 'correct' => 'a'],
                    ['type' => 'true_false', 'stem' => 'The walk takes one hour.', 'correct' => 'false'],
                    ['type' => 'short_answer', 'stem' => 'How long does the walk take?', 'accepted_answers' => ['thirty minutes', '30 minutes']],
                ],
            ],
            [
                'type' => 'matching',
                'stem' => 'Match each word with its meaning.',
                'matching_pairs' => [
                    ['left' => 'morning', 'right' => 'the early part of the day'],
                    ['left' => 'sister', 'right' => 'a female sibling'],
                    ['left' => 'school', 'right' => 'a place of learning'],
                ],
            ],
        ],
    ])), true);

    // The group's ATTACHABLE ids are its sub-questions; the container is
    // reported separately.
    expect($drafted['ok'])->toBeTrue()
        ->and($drafted['data']['saved_question_ids'])->toHaveCount(4)
        ->and($drafted['data']['passage_group_ids'])->toHaveCount(1);

    $groupId = $drafted['data']['passage_group_ids'][0];
    $group = Question::findOrFail($groupId);
    expect($group->type->value)->toBe('group')
        ->and($group->answer_key)->toBeNull()
        ->and($group->children()->count())->toBe(3)
        // Sub-questions inherit the passage's topic and land published.
        ->and($group->children()->pluck('topic')->unique()->all())->toBe(['Unit 2'])
        ->and($group->children()->pluck('status')->unique()->all())->toBe(['published']);

    // Matching: ids are server-minted, the key pairs every left to its right.
    $matching = Question::where('type', 'matching')->firstOrFail();
    expect($matching->body['left'])->toHaveCount(3)
        ->and($matching->body['right'])->toHaveCount(3)
        ->and($matching->answer_key['pairs'])->toHaveCount(3)
        ->and($matching->answer_key['pairs']['l1'])->toBe('r1');
    $rightText = collect($matching->body['right'])->firstWhere('id', 'r1')['text'];
    expect($rightText)->toBe('the early part of the day');

    // Building an exam with the GROUP id expands it to the sub-questions —
    // the container never sits the paper (same rule as the studio).
    $exam = json_decode((string) (new CreateExamTool($context))->handle(new Request([
        'title' => 'English — Reading Test',
        'subject_id' => $assignment->subject_id,
        'section_ids' => [$assignment->section_id],
        'question_ids' => [$groupId, $matching->id],
    ])), true);

    expect($exam['ok'])->toBeTrue()->and($exam['data']['question_count'])->toBe(4);

    $quiz = Quiz::findOrFail($exam['data']['quiz_id']);
    $paperIds = $quiz->quizQuestions()->orderBy('sort_order')->pluck('question_id');
    expect($paperIds)->not->toContain($groupId)
        ->and($paperIds->take(3)->all())->toBe($group->children()->pluck('id')->all());

    // UpdateExamTool add_question_ids expands a group the same way.
    $second = json_decode((string) (new CreateExamTool($context))->handle(new Request([
        'title' => 'English — Quiz',
        'subject_id' => $assignment->subject_id,
        'section_ids' => [$assignment->section_id],
        'question_ids' => [$matching->id],
    ])), true);
    $updated = json_decode((string) (new UpdateExamTool($context))->handle(new Request([
        'quiz_id' => $second['data']['quiz_id'],
        'add_question_ids' => [$groupId],
    ])), true);
    expect($updated['ok'])->toBeTrue();
    expect(Quiz::findOrFail($second['data']['quiz_id'])->quizQuestions()->count())->toBe(4);
});

it('refuses exam creation outside the teacher\'s own classes, without authority, or in a closed term', function () {
    [$teacher, $assignment, $branch] = aiTeacherClass();

    $context = new AiContext(user: $teacher, lane: AiLane::Teacher, school: $branch->school, branch: $branch);
    $tool = new CreateExamTool($context);

    $payload = [
        'title' => 'Test', 'subject_id' => $assignment->subject_id,
        'section_ids' => [$assignment->section_id],
        'new_questions' => [['type' => 'true_false', 'stem' => 'Water boils at 100°C.', 'correct' => true]],
        'bank_name' => 'Drafts',
    ];

    // A section the teacher does not teach.
    $foreign = $branch->sections()->create([
        'school_id' => $branch->school_id,
        'grade_level_id' => GradeLevel::where('code', 'G8')->value('id'),
        'name' => 'B',
    ]);
    $denied = json_decode((string) $tool->handle(new Request([...$payload, 'section_ids' => [$foreign->id]])), true);
    expect($denied['ok'])->toBeFalse()->and($denied['reason'])->toContain('do not teach');

    // No LMS authority in this context (a plain account).
    $stranger = new AiContext(user: User::factory()->create(), lane: AiLane::Teacher, school: $branch->school, branch: $branch);
    $unauthorized = json_decode((string) (new CreateExamTool($stranger))->handle(new Request($payload)), true);
    expect($unauthorized['ok'])->toBeFalse();

    // Closed semesters are read-only (TermGate).
    $assignment->term->update(['status' => 'closed']);
    $closed = json_decode((string) $tool->handle(new Request($payload)), true);
    expect($closed['ok'])->toBeFalse()->and($closed['reason'])->toContain('closed');

    expect(Quiz::count())->toBe(0);
});

it('anchors the exam to the CURRENT semester when the class exists in several terms', function () {
    [$teacher, $assignment, $branch] = aiTeacherClass();

    // The same class in semester 2, which is the current one — the tool must
    // pick it by itself instead of refusing over the two term rows.
    $year = AcademicYear::findOrFail($assignment->academic_year_id);
    $term2 = $year->terms()->where('id', '!=', $assignment->term_id)->first()
        ?? $year->terms()->create([
            'school_id' => $branch->school_id, 'branch_id' => $branch->id,
            'school_program_id' => $assignment->term->school_program_id,
            'name' => 'Semester 2', 'sequence' => 2, 'period_minutes' => 45,
            'is_current' => false, 'is_active' => true,
        ]);
    $term2->update(['is_current' => true]);

    $current = SubjectAssignment::create([
        'school_id' => $branch->school_id,
        'branch_id' => $branch->id,
        'academic_year_id' => $year->id,
        'section_id' => $assignment->section_id,
        'subject_id' => $assignment->subject_id,
        'term_id' => $term2->id,
        'employee_id' => $assignment->employee_id,
        'periods_per_week' => 5,
    ]);

    $context = new AiContext(user: $teacher, lane: AiLane::Teacher, school: $branch->school, branch: $branch);

    $result = json_decode((string) (new CreateExamTool($context))->handle(new Request([
        'title' => 'Semester 2 Quiz',
        'kind' => 'quiz',
        'subject_id' => $assignment->subject_id,
        'section_ids' => [$assignment->section_id],
        'bank_name' => 'S2 drafts',
        'new_questions' => [['type' => 'true_false', 'stem' => 'The Earth orbits the Sun.', 'correct' => 'true']],
    ])), true);

    expect($result['ok'])->toBeTrue();
    expect(Quiz::findOrFail($result['data']['quiz_id'])->subject_assignment_id)->toBe($current->id);
});

it('falls back to a writable term when the current one is closed, and names sections user-safely', function () {
    [$teacher, $assignment, $branch] = aiTeacherClass();

    // Current-but-CLOSED semester 1; open semester 2 — the exam must land in
    // the writable term, not die on the term gate.
    $year = AcademicYear::findOrFail($assignment->academic_year_id);
    $assignment->term->update(['is_current' => true, 'status' => 'closed']);
    $term2 = $year->terms()->where('id', '!=', $assignment->term_id)->firstOrFail();
    $term2->update(['is_current' => false, 'status' => 'active']);
    $open = SubjectAssignment::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'academic_year_id' => $year->id, 'section_id' => $assignment->section_id,
        'subject_id' => $assignment->subject_id, 'term_id' => $term2->id,
        'employee_id' => $assignment->employee_id, 'periods_per_week' => 5,
    ]);

    $context = new AiContext(user: $teacher, lane: AiLane::Teacher, school: $branch->school, branch: $branch);
    $tool = new CreateExamTool($context);

    $result = json_decode((string) $tool->handle(new Request([
        'title' => 'Fallback Quiz', 'kind' => 'quiz',
        'subject_id' => $assignment->subject_id,
        'section_ids' => [$assignment->section_id],
        'bank_name' => 'Fallback drafts',
        'new_questions' => [['type' => 'true_false', 'stem' => 'Water is a liquid at room temperature.', 'correct' => 'true']],
    ])), true);

    expect($result['ok'])->toBeTrue();
    expect(Quiz::findOrFail($result['data']['quiz_id'])->subject_assignment_id)->toBe($open->id);

    // A class the teacher does not own is refused by NAME — internal ids
    // must never reach the chat.
    $foreign = $branch->sections()->create([
        'school_id' => $branch->school_id,
        'grade_level_id' => GradeLevel::where('code', 'G8')->value('id'),
        'name' => 'B',
    ]);
    $denied = json_decode((string) $tool->handle(new Request([
        'title' => 'X', 'subject_id' => $assignment->subject_id,
        'section_ids' => [$foreign->id], 'bank_name' => 'X',
        'new_questions' => [['type' => 'true_false', 'stem' => 'S', 'correct' => 'true']],
    ])), true);
    expect($denied['ok'])->toBeFalse()
        ->and($denied['reason'])->toContain('Grade 8 B')
        ->and($denied['reason'])->not->toContain((string) $foreign->id);
});

// ── Leadership + platform exam authoring ───────────────────────────────────

it('lets a director or principal build a draft exam for any class in scope — anchored to the class teacher', function () {
    [$teacher, $assignment, $branch] = aiTeacherClass();
    $assignment->term->update(['is_current' => true]);

    $director = memberOf($branch, Role::Director);
    $context = new AiContext(user: $director, lane: AiLane::Leadership, school: $branch->school, branch: $branch);

    // The catalog hands leadership the exact section/subject ids of REAL classes.
    $catalog = json_decode((string) (new ClassCatalogTool($context))->handle(new Request([])), true);
    expect($catalog['ok'])->toBeTrue();
    $row = collect($catalog['data']['classes'])->firstWhere('section_id', $assignment->section_id);
    expect($row['subject_id'])->toBe($assignment->subject_id)
        ->and($row['teacher'])->toBe('Alemu Bekele');

    $payload = [
        'title' => 'Branch Common Exam', 'kind' => 'exam',
        'subject_id' => $assignment->subject_id,
        'section_ids' => [$assignment->section_id],
        'bank_name' => 'Leadership drafts',
        'new_questions' => [['type' => 'true_false', 'stem' => 'The Nile flows north.', 'correct' => 'true']],
    ];

    $result = json_decode((string) (new CreateExamTool($context))->handle(new Request($payload)), true);
    expect($result['ok'])->toBeTrue();

    $quiz = Quiz::findOrFail($result['data']['quiz_id']);
    expect($quiz->subject_assignment_id)->toBe($assignment->id)
        ->and($quiz->created_by)->toBe($director->id)
        ->and($quiz->status->value)->toBe('draft');

    // A principal in a school-wide session (no branch context) reaches it too.
    $principal = schoolPrincipal($branch);
    $wide = new AiContext(user: $principal, lane: AiLane::Leadership, school: $branch->school);
    $wideResult = json_decode((string) (new CreateExamTool($wide))->handle(new Request([
        ...$payload, 'title' => 'School Common Exam', 'bank_name' => 'Principal drafts',
    ])), true);
    expect($wideResult['ok'])->toBeTrue();
    expect(Quiz::findOrFail($wideResult['data']['quiz_id'])->branch_id)->toBe($branch->id);

    // Supervisory reach never crosses the tenant: a director of ANOTHER
    // school is refused for these classes.
    $otherBranch = makeBranch('AA-'.random_int(1000, 9999));
    $foreign = new AiContext(
        user: memberOf($otherBranch, Role::Director),
        lane: AiLane::Leadership, school: $otherBranch->school, branch: $otherBranch,
    );
    $denied = json_decode((string) (new CreateExamTool($foreign))->handle(new Request($payload)), true);
    expect($denied['ok'])->toBeFalse();
    expect(Quiz::count())->toBe(2);
});

it('creates a DRAFT platform mock exam through CreateMockExamTool — exam-prep staff only', function () {
    test()->seed(GradeLevelSeeder::class);
    test()->seed(SubjectSeeder::class);

    $admin = platformAdmin();
    $context = new AiContext(user: $admin, lane: AiLane::Platform);

    $catalog = json_decode((string) (new ExamPrepCatalogTool($context))->handle(new Request([])), true);
    expect($catalog['ok'])->toBeTrue();
    $subjectId = collect($catalog['data']['subjects'])->firstWhere('code', 'MATH')['subject_id'];
    $gradeId = collect($catalog['data']['grade_levels'])->firstWhere('code', 'G12')['grade_level_id'];

    $result = json_decode((string) (new CreateMockExamTool($context))->handle(new Request([
        'title' => 'EUEE Mathematics Mock 1',
        'subject_id' => $subjectId,
        'grade_level_id' => $gradeId,
        'exam_kind' => 'national_past',
        'exam_year_ec' => 2016,
        'stream' => 'natural',
        'duration_minutes' => 120,
        'bank_name' => 'EUEE Mathematics',
        'new_questions' => [
            ['type' => 'mcq_single', 'stem' => '2 + 2 = ?', 'options' => [
                ['id' => 'a', 'text' => '4'], ['id' => 'b', 'text' => '5'],
            ], 'correct' => 'a', 'topic' => 'Arithmetic'],
            ['type' => 'true_false', 'stem' => '7 is a prime number.', 'correct' => 'true'],
        ],
    ])), true);

    expect($result['ok'])->toBeTrue();

    $quiz = Quiz::findOrFail($result['data']['quiz_id']);
    expect($quiz->is_platform)->toBeTrue()
        ->and($quiz->kind)->toBe('mock')
        ->and($quiz->status->value)->toBe('draft')
        ->and($quiz->exam_kind)->toBe('national_past')
        ->and($quiz->exam_year_ec)->toBe(2016)
        ->and($quiz->stream)->toBe('natural')
        ->and($quiz->school_id)->toBeNull()
        ->and($quiz->subject_id)->toBe($subjectId)
        ->and($quiz->quizQuestions()->count())->toBe(2);

    // The new questions live in a PLATFORM bank (the national bank).
    $bank = QuestionBank::where('name', 'EUEE Mathematics')->firstOrFail();
    expect($bank->school_id)->toBeNull()
        ->and($bank->questions()->where('status', 'published')->count())->toBe(2);

    // No exam_prep.manage — no reach, whatever the lane says.
    $stranger = new AiContext(user: User::factory()->create(), lane: AiLane::Platform);
    $denied = json_decode((string) (new CreateMockExamTool($stranger))->handle(new Request(['title' => 'X'])), true);
    expect($denied['ok'])->toBeFalse();
    expect(Quiz::query()->count())->toBe(1);
});

// ── Paper parts + UpdateExamTool (edit / regroup / publish) ────────────────

it('creates a grouped paper, regroups it with UpdateExamTool, and publishes only after confirmation', function () {
    [$teacher, $assignment, $branch] = aiTeacherClass();
    $context = new AiContext(user: $teacher, lane: AiLane::Teacher, school: $branch->school, branch: $branch);

    $created = json_decode((string) (new CreateExamTool($context))->handle(new Request([
        'title' => 'Grouped Test',
        'subject_id' => $assignment->subject_id,
        'section_ids' => [$assignment->section_id],
        'bank_name' => 'Grouped drafts',
        'parts' => [
            ['title' => 'Part I — Multiple Choice', 'instructions' => 'Choose the best answer.', 'new_questions' => [
                ['type' => 'mcq_single', 'stem' => '3 × 4 = ?', 'options' => [
                    ['id' => 'a', 'text' => '12'], ['id' => 'b', 'text' => '7'],
                ], 'correct' => 'a'],
            ]],
            ['title' => 'Part II — True/False', 'new_questions' => [
                ['type' => 'true_false', 'stem' => '9 is an even number.', 'correct' => 'false'],
            ]],
        ],
    ])), true);

    // The app renders "Part I — {title}" itself, so a model-sent "Part I — "
    // prefix is stripped: stored titles carry no numbering.
    expect($created['ok'])->toBeTrue()
        ->and($created['data']['parts'])->toBe(['Multiple Choice', 'True/False']);

    $quiz = Quiz::findOrFail($created['data']['quiz_id']);
    expect(array_column($quiz->parts, 'title'))->toBe(['Multiple Choice', 'True/False'])
        ->and($quiz->quizQuestions()->orderBy('sort_order')->pluck('part_index')->all())->toBe([0, 1]);

    $tool = new UpdateExamTool($context);

    // quiz_id alone reads the paper — the ids the model regroups with.
    $paper = json_decode((string) $tool->handle(new Request(['quiz_id' => $quiz->id])), true);
    expect($paper['ok'])->toBeTrue()->and($paper['data']['question_count'])->toBe(2);
    [$mcq, $tf] = array_column($paper['data']['questions'], 'question_id');

    // Regroup with the parts reversed: True/False first.
    $regrouped = json_decode((string) $tool->handle(new Request([
        'quiz_id' => $quiz->id,
        'parts' => [
            ['title' => 'Part I — True/False', 'question_ids' => [$tf]],
            ['title' => 'Part II — Multiple Choice', 'question_ids' => [$mcq]],
        ],
    ])), true);
    expect($regrouped['ok'])->toBeTrue();
    $quiz->refresh();
    expect(array_column($quiz->parts, 'title'))->toBe(['True/False', 'Multiple Choice'])
        ->and($quiz->quizQuestions()->orderBy('sort_order')->first()->question_id)->toBe($tf);

    // Publishing without the user's explicit confirmation is refused…
    $unconfirmed = json_decode((string) $tool->handle(new Request([
        'quiz_id' => $quiz->id, 'set_status' => 'publish',
    ])), true);
    expect($unconfirmed['ok'])->toBeFalse()->and($unconfirmed['reason'])->toContain('confirmation');
    expect(Quiz::findOrFail($quiz->id)->status->value)->toBe('draft');

    // …and goes through with confirmed=true, freezing points like the studio.
    $published = json_decode((string) $tool->handle(new Request([
        'quiz_id' => $quiz->id, 'set_status' => 'publish', 'confirmed' => 'true',
    ])), true);
    expect($published['ok'])->toBeTrue();
    $quiz->refresh();
    expect($quiz->status->value)->toBe('published')->and((float) $quiz->total_points)->toBe(2.0);
});

it('freezes the layout once someone sat the paper, and keeps other workspaces out of UpdateExamTool', function () {
    [$teacher, $assignment, $branch] = aiTeacherClass();
    $context = new AiContext(user: $teacher, lane: AiLane::Teacher, school: $branch->school, branch: $branch);

    $created = json_decode((string) (new CreateExamTool($context))->handle(new Request([
        'title' => 'Frozen Test', 'subject_id' => $assignment->subject_id,
        'section_ids' => [$assignment->section_id], 'bank_name' => 'Frozen drafts',
        'new_questions' => [
            ['type' => 'true_false', 'stem' => 'Water freezes at 0°C.', 'correct' => 'true'],
            ['type' => 'true_false', 'stem' => 'The sun is cold.', 'correct' => 'false'],
        ],
    ])), true);
    $quiz = Quiz::findOrFail($created['data']['quiz_id']);
    [$q1, $q2] = $quiz->quizQuestions()->orderBy('sort_order')->pluck('question_id')->all();

    $quiz->attempts()->create([
        'user_id' => User::factory()->create()->id,
        'attempt_number' => 1,
        'status' => 'submitted',
        'started_at' => now(),
        'seed' => 1,
        'question_ids' => [['id' => $q1, 'points' => 1, 'part' => null]],
    ]);

    $tool = new UpdateExamTool($context);

    $denied = json_decode((string) $tool->handle(new Request([
        'quiz_id' => $quiz->id,
        'parts' => [['title' => 'Part I', 'question_ids' => [$q1, $q2]]],
    ])), true);
    expect($denied['ok'])->toBeFalse()->and($denied['reason'])->toContain('frozen');

    // Non-structural edits stay possible.
    $renamed = json_decode((string) $tool->handle(new Request([
        'quiz_id' => $quiz->id, 'title' => 'Frozen Test — Final',
    ])), true);
    expect($renamed['ok'])->toBeTrue();
    expect(Quiz::findOrFail($quiz->id)->title)->toBe('Frozen Test — Final');

    // A session frozen at another school never reaches this exam.
    $otherBranch = makeBranch('AA-'.random_int(1000, 9999));
    $foreign = new AiContext(
        user: memberOf($otherBranch, Role::Director),
        lane: AiLane::Leadership, school: $otherBranch->school, branch: $otherBranch,
    );
    $out = json_decode((string) (new UpdateExamTool($foreign))->handle(new Request(['quiz_id' => $quiz->id])), true);
    expect($out['ok'])->toBeFalse();
});

// ── Chat handoff (ChatRecipientsTool mirrors the new-chat picker) ──────────

/** Enroll a fresh student (with one guardian) into a section. */
function aiEnrolledStudent(Branch $branch, int $academicYearId, Section $section, string $name = 'Sara'): Student
{
    $student = Student::create([
        'first_name' => $name, 'father_name' => 'Tesfaye', 'gender' => 'female',
    ]);

    app(EnrollStudentAction::class)->execute($student, [
        'academic_year_id' => $academicYearId,
        'section_id' => $section->id,
        'grade_level_id' => $section->grade_level_id,
    ]);

    $parent = ParentProfile::create([
        'user_id' => User::factory()->create()->id,
        'first_name' => 'Worknesh', 'father_name' => 'Abebe', 'gender' => 'female',
    ]);
    StudentGuardian::create([
        'student_id' => $student->id, 'parent_id' => $parent->id,
        'relationship' => 'mother', 'is_primary' => true, 'is_active' => true,
        'can_view_grades' => true, 'can_view_attendance' => true, 'can_pay_fees' => true,
    ]);

    return $student;
}

it('bounds a teacher\'s chat recipients to their own families plus reachable staff', function () {
    [$teacher, $assignment, $branch] = aiTeacherClass();
    $section = Section::findOrFail($assignment->section_id);

    $own = aiEnrolledStudent($branch, $assignment->academic_year_id, $section, 'Sara');

    // Same branch, DIFFERENT section — a non-supervisory teacher must not see it.
    $otherSection = $branch->sections()->create([
        'school_id' => $branch->school_id,
        'grade_level_id' => $section->grade_level_id,
        'name' => 'Z',
    ]);
    $stranger = aiEnrolledStudent($branch, $assignment->academic_year_id, $otherSection, 'Kalkidan');

    $colleague = memberOf($branch, Role::Registrar);

    $context = new AiContext(user: $teacher, lane: AiLane::Teacher, school: $branch->school, branch: $branch);
    $result = json_decode((string) (new ChatRecipientsTool($context))->handle(new Request([])), true);

    expect($result['ok'])->toBeTrue();

    $families = collect($result['data']['families']);
    expect($families->pluck('student_id'))->toContain($own->id)
        ->and($families->pluck('student_id'))->not->toContain($stranger->id)
        ->and($families->firstWhere('student_id', $own->id)['guardians'])->toContain('Worknesh Abebe');

    expect(collect($result['data']['staff'])->pluck('user_id'))->toContain($colleague->id);
});

it('gives a guardian their child\'s teachers and branch office as chat recipients', function () {
    [$teacherUser, $assignment, $branch] = aiTeacherClass();
    $section = Section::findOrFail($assignment->section_id);

    $child = aiEnrolledStudent($branch, $assignment->academic_year_id, $section, 'Hana');
    $guardian = User::findOrFail(ParentProfile::whereIn(
        'id', StudentGuardian::where('student_id', $child->id)->select('parent_id'),
    )->value('user_id'));

    $director = memberOf($branch, Role::Director);

    $context = new AiContext(user: $guardian, lane: AiLane::Parent);
    $result = json_decode((string) (new ChatRecipientsTool($context))->handle(new Request([])), true);

    expect($result['ok'])->toBeTrue();

    $card = collect($result['data']['children'])->firstWhere('student_id', $child->id);
    $partners = collect($card['partners']);

    expect($partners->pluck('user_id'))->toContain($teacherUser->id)
        ->and($partners->pluck('user_id'))->toContain($director->id)
        ->and($partners->firstWhere('user_id', $teacherUser->id)['role'])->toBe('teacher');

    // Name search narrows the partner list.
    $searched = json_decode((string) (new ChatRecipientsTool($context))->handle(new Request([
        'q' => 'no-such-person-xyz',
    ])), true);
    expect(collect($searched['data']['children'])->firstWhere('student_id', $child->id)['partners'])->toBeEmpty();
});

it('denies chat recipients outside a school workspace and for unlinked accounts', function () {
    // Staff lane with no frozen school context — nothing to list.
    $teacher = User::factory()->create();
    $noScope = json_decode((string) (new ChatRecipientsTool(
        new AiContext(user: $teacher, lane: AiLane::Teacher),
    ))->handle(new Request([])), true);
    expect($noScope['ok'])->toBeFalse();

    // Family lane with no guardian links — graceful refusal, no data.
    $childless = json_decode((string) (new ChatRecipientsTool(
        new AiContext(user: User::factory()->create(), lane: AiLane::Parent),
    ))->handle(new Request([])), true);
    expect($childless['ok'])->toBeFalse();
});

<?php

use App\Actions\EnrollStudentAction;
use App\Actions\SaveAcademicYearAction;
use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\ChatMessage;
use App\Models\Employee;
use App\Models\GradeLevel;
use App\Models\Notification;
use App\Models\ParentProfile;
use App\Models\School;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentGuardian;
use App\Models\Subject;
use App\Models\SubjectAssignment;
use App\Models\User;
use App\Services\Chat\ConversationAccess;
use App\Services\Sms\SmsClient;
use Database\Seeders\GradeLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SubjectSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

/*
 * THE CHAT GUARD RAIL (ADR-019). Asserts the one-engine design end to end:
 * cross-tenant isolation, the deny-by-default reachability matrix (no
 * parent↔parent, teachers only reach their own students' families), the
 * communication-book approval gate (pending messages invisible to the family,
 * branch override), rule-derived channel audiences (classroom / staff room /
 * announcements, students off by default), mentions/reactions/read state,
 * and director audit access that is always activity-logged.
 */

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(GradeLevelSeeder::class);
    $this->seed(SubjectSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    app(ConversationAccess::class)->flush();
});

// ───────────────────────────── fixtures ─────────────────────────────

function chatYear(Branch $branch): AcademicYear
{
    return (new SaveAcademicYearAction)->execute($branch, ['name' => '2017 E.C.', 'status' => 'active']);
}

function chatSection(Branch $branch, string $name = 'A'): Section
{
    return $branch->sections()->create([
        'school_id' => $branch->school_id,
        'grade_level_id' => GradeLevel::where('code', 'G7')->value('id'),
        'name' => $name,
    ]);
}

/** @return array{0: User, 1: Employee} */
function chatTeacher(Branch $branch): array
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

function chatClass(Branch $branch, AcademicYear $year, Section $section, ?Employee $teacher): SubjectAssignment
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

function chatStudent(Branch $branch, AcademicYear $year, Section $section, string $name = 'Sara'): Student
{
    $student = Student::create([
        'user_id' => User::factory()->create()->id,
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

function chatGuardian(Student $student): User
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
        'can_view_grades' => true,
        'can_view_attendance' => true,
        'can_pay_fees' => true,
        'is_primary' => true,
        'is_active' => true,
    ]);

    return $user;
}

/** The whole cast of one school in one call. */
function chatWorld(string $code = 'AA-0001'): array
{
    $branch = makeBranch($code);
    $year = chatYear($branch);
    $section = chatSection($branch);
    [$teacherUser, $teacherEmployee] = chatTeacher($branch);
    chatClass($branch, $year, $section, $teacherEmployee);
    $student = chatStudent($branch, $year, $section);
    $guardian = chatGuardian($student);

    return compact('branch', 'year', 'section', 'teacherUser', 'teacherEmployee', 'student', 'guardian');
}

function turnOffApprovalGate(Branch $branch): void
{
    School::find($branch->school_id)->update([
        'settings' => ['chat_teacher_parent_approval' => false],
    ]);
}

// ─────────────────────── reachability matrix ───────────────────────

it('lets a teacher open the family thread of their own student and message it', function () {
    $w = chatWorld();
    turnOffApprovalGate($w['branch']);
    Sanctum::actingAs($w['teacherUser']);

    $create = $this->postJson('/api/v1/chat/conversations', [
        'kind' => 'direct',
        'student_id' => $w['student']->id,
    ], branchContext($w['branch']))->assertCreated();

    $conversationId = $create->json('data.id');

    $this->postJson("/api/v1/chat/conversations/{$conversationId}/messages", [
        'body' => 'Selam — Sara did very well today.',
    ], branchContext($w['branch']))->assertCreated()->assertJsonPath('data.status', 'sent');

    // The guardian sees the same thread through the relationship lane.
    Sanctum::actingAs($w['guardian']);
    $this->getJson("/api/v1/me/chat/conversations/{$conversationId}/messages")
        ->assertOk()
        ->assertJsonPath('data.0.body', 'Selam — Sara did very well today.');
});

it('blocks a teacher from the family of a student they do not teach', function () {
    $w = chatWorld();
    $otherSection = chatSection($w['branch'], 'B');
    $otherStudent = chatStudent($w['branch'], $w['year'], $otherSection, 'Hanna');

    Sanctum::actingAs($w['teacherUser']);
    $this->postJson('/api/v1/chat/conversations', [
        'kind' => 'direct',
        'student_id' => $otherStudent->id,
    ], branchContext($w['branch']))->assertForbidden();
});

it('never lets a parent reach another parent', function () {
    $w = chatWorld();
    $otherStudent = chatStudent($w['branch'], $w['year'], $w['section'], 'Hanna');
    $otherGuardian = chatGuardian($otherStudent);

    Sanctum::actingAs($w['guardian']);

    // Not in the partner directory…
    $partners = $this->getJson('/api/v1/me/chat/partners')->assertOk()->json('data');
    $partnerIds = collect($partners)->flatMap(fn ($card) => collect($card['partners'])->pluck('user_id'));
    expect($partnerIds)->not->toContain($otherGuardian->id);

    // …and a forged create is rejected.
    $this->postJson('/api/v1/me/chat/conversations', [
        'kind' => 'direct',
        'student_id' => $w['student']->id,
        'user_id' => $otherGuardian->id,
    ])->assertForbidden();
});

it('keeps chat strictly tenant-isolated', function () {
    $w = chatWorld();
    $other = chatWorld('AA-0002');
    turnOffApprovalGate($w['branch']);

    Sanctum::actingAs($w['teacherUser']);
    $conversationId = $this->postJson('/api/v1/chat/conversations', [
        'kind' => 'direct', 'student_id' => $w['student']->id,
    ], branchContext($w['branch']))->assertCreated()->json('data.id');
    $this->postJson("/api/v1/chat/conversations/{$conversationId}/messages", [
        'body' => 'private to school A',
    ], branchContext($w['branch']))->assertCreated();

    // A teacher at school B sees nothing of it: not in the list, no read,
    // no post, no search hits.
    app(ConversationAccess::class)->flush();
    Sanctum::actingAs($other['teacherUser']);

    $list = $this->getJson('/api/v1/chat/conversations', branchContext($other['branch']))->assertOk()->json('data');
    expect(collect($list)->pluck('id'))->not->toContain($conversationId);

    $this->getJson("/api/v1/chat/conversations/{$conversationId}/messages", branchContext($other['branch']))->assertForbidden();
    $this->postJson("/api/v1/chat/conversations/{$conversationId}/messages", ['body' => 'x'], branchContext($other['branch']))->assertForbidden();

    $hits = $this->getJson('/api/v1/chat/search?q=private+to+school', branchContext($other['branch']))->assertOk()->json('data');
    expect($hits)->toBeEmpty();

    // A director of school B cannot even audit it.
    Sanctum::actingAs(directorOf($other['branch']));
    $this->getJson("/api/v1/chat/conversations/{$conversationId}", branchContext($other['branch']))->assertForbidden();
});

// ─────────────────── the communication-book gate ───────────────────

it('parks teacher→parent messages for approval by default, invisible to the family', function () {
    $w = chatWorld();
    $director = directorOf($w['branch']);

    Sanctum::actingAs($w['teacherUser']);
    $conversationId = $this->postJson('/api/v1/chat/conversations', [
        'kind' => 'direct', 'student_id' => $w['student']->id,
    ], branchContext($w['branch']))->assertCreated()->json('data.id');

    $send = $this->postJson("/api/v1/chat/conversations/{$conversationId}/messages", [
        'body' => 'Please buy the exercise book.',
    ], branchContext($w['branch']))->assertCreated();
    expect($send->json('data.status'))->toBe('pending');

    $messageId = $send->json('data.id');

    // Invisible to the guardian…
    app(ConversationAccess::class)->flush();
    Sanctum::actingAs($w['guardian']);
    $bodies = $this->getJson("/api/v1/me/chat/conversations/{$conversationId}/messages")->assertOk()->json('data');
    expect(collect($bodies)->pluck('id'))->not->toContain($messageId);

    // …until the director approves from the communication book.
    app(ConversationAccess::class)->flush();
    Sanctum::actingAs($director);
    $pending = $this->getJson('/api/v1/chat/approvals', branchContext($w['branch']))->assertOk()->json('data');
    expect(collect($pending)->pluck('id'))->toContain($messageId);

    $this->postJson("/api/v1/chat/messages/{$messageId}/approve", [], branchContext($w['branch']))->assertOk();

    app(ConversationAccess::class)->flush();
    Sanctum::actingAs($w['guardian']);
    $this->getJson("/api/v1/me/chat/conversations/{$conversationId}/messages")
        ->assertOk()
        ->assertJsonPath('data.0.status', 'sent');

    // The teacher was told (in-app row via the one pipeline).
    expect(Notification::where('user_id', $w['teacherUser']->id)->where('event', 'chat.message_decided')->exists())->toBeTrue();
});

it('rejecting a pending message keeps it from the family and carries the note back', function () {
    $w = chatWorld();
    $director = directorOf($w['branch']);

    Sanctum::actingAs($w['teacherUser']);
    $conversationId = $this->postJson('/api/v1/chat/conversations', [
        'kind' => 'direct', 'student_id' => $w['student']->id,
    ], branchContext($w['branch']))->assertCreated()->json('data.id');
    $messageId = $this->postJson("/api/v1/chat/conversations/{$conversationId}/messages", [
        'body' => 'Rude draft.',
    ], branchContext($w['branch']))->json('data.id');

    Sanctum::actingAs($director);
    $this->postJson("/api/v1/chat/messages/{$messageId}/reject", [
        'note' => 'Please rephrase and resend.',
    ], branchContext($w['branch']))->assertOk();

    expect(ChatMessage::find($messageId)->status)->toBe('rejected');

    // The author still sees their rejected message (with the note).
    app(ConversationAccess::class)->flush();
    Sanctum::actingAs($w['teacherUser']);
    $mine = $this->getJson("/api/v1/chat/conversations/{$conversationId}/messages", branchContext($w['branch']))->json('data');
    $row = collect($mine)->firstWhere('id', $messageId);
    expect($row['status'])->toBe('rejected')
        ->and($row['review_note'])->toBe('Please rephrase and resend.');

    // The family never does.
    app(ConversationAccess::class)->flush();
    Sanctum::actingAs($w['guardian']);
    $bodies = $this->getJson("/api/v1/me/chat/conversations/{$conversationId}/messages")->json('data');
    expect(collect($bodies)->pluck('id'))->not->toContain($messageId);
});

it('honours the branch override that opens teacher↔parent chat', function () {
    $w = chatWorld();
    $w['branch']->update(['settings' => ['chat_teacher_parent_approval' => false]]);

    Sanctum::actingAs($w['teacherUser']);
    $conversationId = $this->postJson('/api/v1/chat/conversations', [
        'kind' => 'direct', 'student_id' => $w['student']->id,
    ], branchContext($w['branch']))->assertCreated()->json('data.id');

    $this->postJson("/api/v1/chat/conversations/{$conversationId}/messages", [
        'body' => 'No gate here.',
    ], branchContext($w['branch']))->assertCreated()->assertJsonPath('data.status', 'sent');
});

it('gates only until the first approval in first_message_only mode', function () {
    $w = chatWorld();
    $director = directorOf($w['branch']);
    School::find($w['branch']->school_id)->update([
        'settings' => ['chat_teacher_parent_approval' => 'first'],
    ]);

    Sanctum::actingAs($w['teacherUser']);
    $conversationId = $this->postJson('/api/v1/chat/conversations', [
        'kind' => 'direct', 'student_id' => $w['student']->id,
    ], branchContext($w['branch']))->assertCreated()->json('data.id');

    // First contact waits for the director…
    $first = $this->postJson("/api/v1/chat/conversations/{$conversationId}/messages", [
        'body' => 'Selam, first contact.',
    ], branchContext($w['branch']))->assertCreated();
    expect($first->json('data.status'))->toBe('pending');

    // …and until it is decided, follow-ups keep queueing.
    $second = $this->postJson("/api/v1/chat/conversations/{$conversationId}/messages", [
        'body' => 'Still before approval.',
    ], branchContext($w['branch']))->assertCreated();
    expect($second->json('data.status'))->toBe('pending');

    app(ConversationAccess::class)->flush();
    Sanctum::actingAs($director);
    $this->postJson("/api/v1/chat/messages/{$first->json('data.id')}/approve", [], branchContext($w['branch']))->assertOk();

    // Once one message is approved, the lane is open for this thread.
    app(ConversationAccess::class)->flush();
    Sanctum::actingAs($w['teacherUser']);
    $this->postJson("/api/v1/chat/conversations/{$conversationId}/messages", [
        'body' => 'Flows freely now.',
    ], branchContext($w['branch']))->assertCreated()->assertJsonPath('data.status', 'sent');
});

it('never gates the parent side or the director side', function () {
    $w = chatWorld();
    $director = directorOf($w['branch']);

    // Parent opens the thread with the teacher and posts — instantly sent.
    Sanctum::actingAs($w['guardian']);
    $conversationId = $this->postJson('/api/v1/me/chat/conversations', [
        'kind' => 'direct',
        'student_id' => $w['student']->id,
        'user_id' => $w['teacherUser']->id,
    ])->assertCreated()->json('data.id');

    $this->postJson("/api/v1/me/chat/conversations/{$conversationId}/messages", [
        'body' => 'How is Sara doing?',
    ])->assertCreated()->assertJsonPath('data.status', 'sent');

    // Director messaging the family is exempt from the gate too.
    app(ConversationAccess::class)->flush();
    Sanctum::actingAs($director);
    $dc = $this->postJson('/api/v1/chat/conversations', [
        'kind' => 'direct', 'student_id' => $w['student']->id,
    ], branchContext($w['branch']))->assertCreated()->json('data.id');
    $this->postJson("/api/v1/chat/conversations/{$dc}/messages", [
        'body' => 'Reminder: fees due Friday.',
    ], branchContext($w['branch']))->assertCreated()->assertJsonPath('data.status', 'sent');
});

// ─────────────────────── channels & audiences ───────────────────────

it('provisions system channels and derives audiences from live enrollment', function () {
    $w = chatWorld();
    turnOffApprovalGate($w['branch']);

    // Teacher: staff room + branch announcements + their classroom.
    Sanctum::actingAs($w['teacherUser']);
    $list = collect($this->getJson('/api/v1/chat/conversations', branchContext($w['branch']))->assertOk()->json('data'));
    expect($list->pluck('system'))->toContain('staff_room', 'branch_announcements', 'classroom', 'school_announcements');

    $classroom = $list->firstWhere('system', 'classroom');

    // The guardian is in the classroom + announcement channels (via the
    // child's LIVE enrollment) but never in the staff room.
    app(ConversationAccess::class)->flush();
    Sanctum::actingAs($w['guardian']);
    $parentList = collect($this->getJson('/api/v1/me/chat/conversations')->assertOk()->json('data'));
    expect($parentList->pluck('system'))->toContain('classroom', 'branch_announcements')
        ->and($parentList->pluck('system'))->not->toContain('staff_room');

    // The student's own account stays OUT until the school enables students.
    app(ConversationAccess::class)->flush();
    Sanctum::actingAs($w['student']->user);
    $studentList = collect($this->getJson('/api/v1/me/chat/conversations')->assertOk()->json('data'));
    expect($studentList->pluck('id'))->not->toContain($classroom['id']);

    School::find($w['branch']->school_id)->update(['settings' => ['chat_students_enabled' => true]]);
    app(ConversationAccess::class)->flush();
    $studentList = collect($this->getJson('/api/v1/me/chat/conversations')->assertOk()->json('data'));
    expect($studentList->pluck('id'))->toContain($classroom['id']);
});

it('lets teachers post to the classroom channel and parents read it', function () {
    $w = chatWorld();
    turnOffApprovalGate($w['branch']);

    Sanctum::actingAs($w['teacherUser']);
    $list = collect($this->getJson('/api/v1/chat/conversations', branchContext($w['branch']))->json('data'));
    $classroom = $list->firstWhere('system', 'classroom');

    $this->postJson("/api/v1/chat/conversations/{$classroom['id']}/messages", [
        'body' => 'Homework: page 42 for tomorrow.',
    ], branchContext($w['branch']))->assertCreated();

    app(ConversationAccess::class)->flush();
    Sanctum::actingAs($w['guardian']);
    $this->getJson("/api/v1/me/chat/conversations/{$classroom['id']}/messages")
        ->assertOk()
        ->assertJsonPath('data.0.body', 'Homework: page 42 for tomorrow.');
});

it('locks admin-posted announcement channels to chat.announce holders', function () {
    $w = chatWorld();
    $director = directorOf($w['branch']);

    Sanctum::actingAs($w['teacherUser']);
    $list = collect($this->getJson('/api/v1/chat/conversations', branchContext($w['branch']))->json('data'));
    $announcements = $list->firstWhere('system', 'branch_announcements');

    $this->postJson("/api/v1/chat/conversations/{$announcements['id']}/messages", [
        'body' => 'I should not be able to post here.',
    ], branchContext($w['branch']))->assertForbidden();

    app(ConversationAccess::class)->flush();
    Sanctum::actingAs($director);
    $this->postJson("/api/v1/chat/conversations/{$announcements['id']}/messages", [
        'body' => 'School closes early on Friday.',
    ], branchContext($w['branch']))->assertCreated()->assertJsonPath('data.status', 'sent');
});

it('lets chat.announce holders create custom channels; teachers cannot', function () {
    $w = chatWorld();
    $director = directorOf($w['branch']);

    Sanctum::actingAs($w['teacherUser']);
    $this->postJson('/api/v1/chat/conversations', [
        'kind' => 'channel', 'title' => 'Nope', 'targets' => [['audience' => 'staff']],
    ], branchContext($w['branch']))->assertForbidden();

    Sanctum::actingAs($director);
    $this->postJson('/api/v1/chat/conversations', [
        'kind' => 'channel',
        'title' => 'Grade 7 teachers',
        'posting' => 'all',
        'targets' => [['audience' => 'staff', 'job_title' => 'teacher']],
    ], branchContext($w['branch']))->assertCreated();
});

it('serves channel building blocks (roles, grades, sections) to announce holders only', function () {
    $w = chatWorld();
    $director = directorOf($w['branch']);

    // A teacher (no chat.announce) cannot read the mass-message options.
    Sanctum::actingAs($w['teacherUser']);
    $this->getJson('/api/v1/chat/channel-options', branchContext($w['branch']))->assertForbidden();

    Sanctum::actingAs($director);
    $res = $this->getJson('/api/v1/chat/channel-options', branchContext($w['branch']))->assertOk();

    expect($res->json('data.roles'))->toContain('teacher', 'director');
    expect(collect($res->json('data.grades'))->pluck('id'))->toContain($w['section']->grade_level_id);
    expect(collect($res->json('data.sections'))->pluck('id'))->toContain($w['section']->id);
    expect($res->json('data.needs_branch'))->toBeFalse();
});

it('sends an SMS-flagged channel post as PLAIN text with a media link, never a raw placeholder', function () {
    $sms = Mockery::spy(SmsClient::class);
    app()->instance(SmsClient::class, $sms);

    $w = chatWorld();
    $director = directorOf($w['branch']);
    $w['guardian']->update(['phone' => '0911223344', 'preferred_language' => 'en']);

    Sanctum::actingAs($director);
    $channelId = $this->postJson('/api/v1/chat/conversations', [
        'kind' => 'channel', 'title' => 'GRADE 7', 'posting' => 'admins',
        'targets' => [['audience' => 'parents', 'grade_level_id' => $w['section']->grade_level_id]],
    ], branchContext($w['branch']))->assertCreated()->json('data.id');

    $this->postJson("/api/v1/chat/conversations/{$channelId}/messages", [
        'body' => '<b>Closed</b> tomorrow',
        'emergency' => true,
        'attachments' => [['name' => 'notice.jpg', 'path' => 'chat/1/notice.jpg', 'mime_type' => 'image/jpeg', 'size' => 1024]],
    ], branchContext($w['branch']))->assertCreated();

    $sms->shouldHaveReceived('send')->withArgs(function (string $phone, string $body): bool {
        return ! str_contains($body, ':links')       // no leaked placeholder
            && ! str_contains($body, ':preview')
            && ! str_contains($body, '<b>')           // HTML stripped
            && str_contains($body, 'Closed tomorrow')
            && str_contains($body, '/messages?c=');   // media link present
    })->once();
});

it('narrows a families channel to a grade and derives its parent audience', function () {
    $w = chatWorld();
    $director = directorOf($w['branch']);

    Sanctum::actingAs($director);
    $channelId = $this->postJson('/api/v1/chat/conversations', [
        'kind' => 'channel',
        'title' => 'Grade 7 families',
        'posting' => 'admins',
        'targets' => [['audience' => 'parents', 'grade_level_id' => $w['section']->grade_level_id]],
    ], branchContext($w['branch']))->assertCreated()->json('data.id');

    // The CREATOR is a member of their own channel and can post — not merely a
    // read-only supervisor — even though they match no audience rule.
    $detail = $this->getJson("/api/v1/chat/conversations/{$channelId}", branchContext($w['branch']))->assertOk();
    expect($detail->json('data.access'))->toBe('member');
    expect($detail->json('data.can_post'))->toBeTrue();
    $this->postJson("/api/v1/chat/conversations/{$channelId}/messages", [
        'body' => 'Parent–teacher day is Friday.',
    ], branchContext($w['branch']))->assertCreated();

    // The detail view names the reach (grade name) for the info sheet.
    $target = $detail->json('data.targets.0');
    expect($target['audience'])->toBe('parents');
    expect($target['grade_name'])->not->toBeNull();

    // The guardian of a Grade 7 student lands in the channel; the audience is
    // rule-derived from the live enrollment.
    Sanctum::actingAs($w['guardian']);
    $ids = collect($this->getJson('/api/v1/me/chat/conversations')->assertOk()->json('data'))->pluck('id');
    expect($ids)->toContain($channelId);
});

// ───────────────────────── pinning & forwarding ─────────────────────────

it('pins and unpins a message; the pinned list surfaces on the conversation', function () {
    $w = chatWorld();
    turnOffApprovalGate($w['branch']);
    $director = directorOf($w['branch']);

    // A group both act in.
    Sanctum::actingAs($director);
    $groupId = $this->postJson('/api/v1/chat/conversations', [
        'kind' => 'group', 'title' => 'Staff room', 'user_ids' => [$w['teacherUser']->id],
    ], branchContext($w['branch']))->assertCreated()->json('data.id');

    $messageId = $this->postJson("/api/v1/chat/conversations/{$groupId}/messages", [
        'body' => 'Meeting at 3pm',
    ], branchContext($w['branch']))->assertCreated()->json('data.id');

    // The group owner (director) pins it.
    $this->postJson("/api/v1/chat/messages/{$messageId}/pin", [], branchContext($w['branch']))
        ->assertOk()->assertJsonPath('data.pinned', true);

    $this->getJson("/api/v1/chat/conversations/{$groupId}", branchContext($w['branch']))
        ->assertOk()
        ->assertJsonPath('data.can_pin', true)
        ->assertJsonPath('data.pinned_messages.0.id', $messageId);

    // Unpin toggles it back off.
    $this->postJson("/api/v1/chat/messages/{$messageId}/pin", [], branchContext($w['branch']))
        ->assertOk()->assertJsonPath('data.pinned', false);

    // A plain member (teacher) cannot manage pins in the group.
    Sanctum::actingAs($w['teacherUser']);
    $this->postJson("/api/v1/chat/messages/{$messageId}/pin", [], branchContext($w['branch']))
        ->assertForbidden();
});

it('forwards messages into another conversation, tagged with their origin', function () {
    $w = chatWorld();
    turnOffApprovalGate($w['branch']);
    $director = directorOf($w['branch']);

    Sanctum::actingAs($director);
    $sourceId = $this->postJson('/api/v1/chat/conversations', [
        'kind' => 'group', 'title' => 'Source', 'user_ids' => [$w['teacherUser']->id],
    ], branchContext($w['branch']))->assertCreated()->json('data.id');
    $targetId = $this->postJson('/api/v1/chat/conversations', [
        'kind' => 'group', 'title' => 'Target', 'user_ids' => [$w['teacherUser']->id],
    ], branchContext($w['branch']))->assertCreated()->json('data.id');

    $m1 = $this->postJson("/api/v1/chat/conversations/{$sourceId}/messages", ['body' => 'first'], branchContext($w['branch']))->json('data.id');
    $m2 = $this->postJson("/api/v1/chat/conversations/{$sourceId}/messages", ['body' => 'second'], branchContext($w['branch']))->json('data.id');

    $this->postJson("/api/v1/chat/conversations/{$targetId}/forward", [
        'source_conversation_id' => $sourceId,
        'message_ids' => [$m1, $m2],
    ], branchContext($w['branch']))->assertCreated()->assertJsonPath('data.count', 2);

    $forwarded = $this->getJson("/api/v1/chat/conversations/{$targetId}/messages", branchContext($w['branch']))
        ->assertOk()->json('data');

    $bodies = collect($forwarded)->pluck('body')->all();
    expect($bodies)->toContain('first', 'second');
    $forwardedRow = collect($forwarded)->firstWhere('body', 'first');
    expect($forwardedRow['meta']['forwarded']['from'])->not->toBeNull();
});

// ───────────────── messages: mentions, reactions, state ─────────────────

it('supports groups with mentions, reactions, replies and unread state', function () {
    $w = chatWorld();
    $colleague = memberOf($w['branch']);

    Sanctum::actingAs($w['teacherUser']);
    $groupId = $this->postJson('/api/v1/chat/conversations', [
        'kind' => 'group', 'title' => 'Maths department', 'user_ids' => [$colleague->id],
    ], branchContext($w['branch']))->assertCreated()->json('data.id');

    $first = $this->postJson("/api/v1/chat/conversations/{$groupId}/messages", [
        'body' => "Welcome @[user:{$colleague->id}] to the department!",
        'client_uuid' => 'a3bb189e-8bf9-3888-9912-ace4e6543002',
    ], branchContext($w['branch']))->assertCreated();

    // Idempotent resend (3G retry) returns the SAME message.
    $retry = $this->postJson("/api/v1/chat/conversations/{$groupId}/messages", [
        'body' => "Welcome @[user:{$colleague->id}] to the department!",
        'client_uuid' => 'a3bb189e-8bf9-3888-9912-ace4e6543002',
    ], branchContext($w['branch']))->assertCreated();
    expect($retry->json('data.id'))->toBe($first->json('data.id'));

    // The mention landed as its own notification.
    expect(Notification::where('user_id', $colleague->id)->where('event', 'chat.mention')->exists())->toBeTrue();

    // Colleague: 1 unread, reacts, replies, reads.
    app(ConversationAccess::class)->flush();
    Sanctum::actingAs($colleague);
    expect($this->getJson('/api/v1/chat/unread-count', branchContext($w['branch']))->json('data.count'))->toBeGreaterThanOrEqual(1);

    $messageId = $first->json('data.id');
    $this->postJson("/api/v1/chat/messages/{$messageId}/reactions", ['emoji' => '👍'], branchContext($w['branch']))
        ->assertOk()
        ->assertJsonPath('data.reactions.0.emoji', '👍');

    $this->postJson("/api/v1/chat/conversations/{$groupId}/messages", [
        'body' => 'Glad to be here!', 'reply_to_id' => $messageId,
    ], branchContext($w['branch']))->assertCreated()->assertJsonPath('data.reply_to.id', $messageId);

    $this->postJson("/api/v1/chat/conversations/{$groupId}/read", ['message_id' => $messageId + 1], branchContext($w['branch']))->assertOk();
});

it('searches only my own conversations and edits/removes within the rules', function () {
    $w = chatWorld();
    $colleague = memberOf($w['branch']);

    Sanctum::actingAs($w['teacherUser']);
    $groupId = $this->postJson('/api/v1/chat/conversations', [
        'kind' => 'group', 'title' => 'Notes', 'user_ids' => [$colleague->id],
    ], branchContext($w['branch']))->json('data.id');

    $messageId = $this->postJson("/api/v1/chat/conversations/{$groupId}/messages", [
        'body' => 'The unique zebra phrase.',
    ], branchContext($w['branch']))->json('data.id');

    expect(collect($this->getJson('/api/v1/chat/search?q=zebra', branchContext($w['branch']))->json('data'))->pluck('id'))
        ->toContain($messageId);

    // Author edits inside the window…
    $this->putJson("/api/v1/chat/messages/{$messageId}", ['body' => 'The edited zebra phrase.'], branchContext($w['branch']))
        ->assertOk()
        ->assertJsonPath('data.body', 'The edited zebra phrase.');

    // …a non-author cannot edit or delete.
    app(ConversationAccess::class)->flush();
    Sanctum::actingAs($colleague);
    $this->putJson("/api/v1/chat/messages/{$messageId}", ['body' => 'hijack'], branchContext($w['branch']))->assertForbidden();
    $this->deleteJson("/api/v1/chat/messages/{$messageId}", [], branchContext($w['branch']))->assertForbidden();

    // Author removes: the row stays as a "removed" tombstone.
    app(ConversationAccess::class)->flush();
    Sanctum::actingAs($w['teacherUser']);
    $this->deleteJson("/api/v1/chat/messages/{$messageId}", [], branchContext($w['branch']))->assertOk();
    $rows = $this->getJson("/api/v1/chat/conversations/{$groupId}/messages", branchContext($w['branch']))->json('data');
    $tombstone = collect($rows)->firstWhere('id', $messageId);
    expect($tombstone['removed'])->toBeTrue()->and($tombstone['body'])->toBeNull();
});

// ───────────────────────── director audit ─────────────────────────

it('gives directors read-only audited access to family threads in their scope', function () {
    $w = chatWorld();
    turnOffApprovalGate($w['branch']);
    $director = directorOf($w['branch']);

    Sanctum::actingAs($w['teacherUser']);
    $conversationId = $this->postJson('/api/v1/chat/conversations', [
        'kind' => 'direct', 'student_id' => $w['student']->id,
    ], branchContext($w['branch']))->json('data.id');
    $this->postJson("/api/v1/chat/conversations/{$conversationId}/messages", [
        'body' => 'note to family',
    ], branchContext($w['branch']))->assertCreated();

    app(ConversationAccess::class)->flush();
    Sanctum::actingAs($director);

    // Read allowed, flagged as audit, activity-logged.
    $show = $this->getJson("/api/v1/chat/conversations/{$conversationId}", branchContext($w['branch']))->assertOk();
    expect($show->json('data.access'))->toBe('audit')
        ->and($show->json('data.can_post'))->toBeFalse();

    $this->assertDatabaseHas('activity_logs', [
        'actor_id' => $director->id,
        'action' => 'chat.audit_view',
        'subject_id' => $conversationId,
    ]);

    // Write denied.
    $this->postJson("/api/v1/chat/conversations/{$conversationId}/messages", [
        'body' => 'director barging in',
    ], branchContext($w['branch']))->assertForbidden();
});

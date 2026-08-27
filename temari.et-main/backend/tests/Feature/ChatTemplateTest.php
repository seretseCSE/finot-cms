<?php

use App\Actions\EnrollStudentAction;
use App\Actions\SaveAcademicYearAction;
use App\Models\Branch;
use App\Models\ChatMessageTemplate;
use App\Models\Employee;
use App\Models\GradeLevel;
use App\Models\ParentProfile;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentGuardian;
use App\Models\Subject;
use App\Models\SubjectAssignment;
use App\Models\User;
use App\Services\Chat\ConversationAccess;
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
    app(ConversationAccess::class)->flush();
});

/** A branch with a teacher, their student Sara and her Amharic-reading mother. */
function tmplWorld(): array
{
    $branch = makeBranch();
    $year = (new SaveAcademicYearAction)->execute($branch, ['name' => '2017 E.C.', 'status' => 'active']);
    $section = $branch->sections()->create([
        'school_id' => $branch->school_id,
        'grade_level_id' => GradeLevel::where('code', 'G7')->value('id'),
        'name' => 'A',
    ]);

    $teacherUser = memberOf($branch);
    $teacher = Employee::create([
        'user_id' => $teacherUser->id,
        'school_id' => $branch->school_id,
        'branch_id' => $branch->id,
        'first_name' => 'Alemu',
        'father_name' => 'Bekele',
        'gender' => 'male',
    ]);
    SubjectAssignment::create([
        'school_id' => $branch->school_id,
        'branch_id' => $branch->id,
        'academic_year_id' => $year->id,
        'section_id' => $section->id,
        'subject_id' => Subject::where('code', 'MATH')->value('id'),
        'term_id' => $year->terms()->first()->id,
        'employee_id' => $teacher->id,
        'periods_per_week' => 5,
    ]);

    $student = Student::create(['first_name' => 'Sara', 'father_name' => 'Tesfaye', 'gender' => 'female']);
    app(EnrollStudentAction::class)->execute($student, [
        'academic_year_id' => $year->id,
        'section_id' => $section->id,
        'grade_level_id' => $section->grade_level_id,
    ]);

    $guardianUser = User::factory()->create(['preferred_language' => 'am']);
    $parent = ParentProfile::create([
        'user_id' => $guardianUser->id,
        'first_name' => 'Worknesh',
        'father_name' => 'Abebe',
        'gender' => 'female',
    ]);
    StudentGuardian::create([
        'student_id' => $student->id,
        'parent_id' => $parent->id,
        'relationship' => 'mother',
        'is_primary' => true,
        'is_active' => true,
    ]);

    return compact('branch', 'teacherUser', 'student', 'guardianUser');
}

function tmplSettings(Branch $branch, array $settings): void
{
    $school = School::find($branch->school_id);
    $school->update(['settings' => array_merge($school->settings ?? [], $settings)]);
}

function tmplCreate(Branch $branch, array $body, bool $active = true): ChatMessageTemplate
{
    return ChatMessageTemplate::create([
        'school_id' => $branch->school_id,
        'name' => 'Absence note',
        'category' => 'attendance',
        'body' => $body,
        'is_active' => $active,
    ]);
}

it('lets a director curate templates and keeps teachers out of the studio', function () {
    $w = tmplWorld();

    Sanctum::actingAs(directorOf($w['branch']));
    $this->postJson('/api/v1/chat/templates', [
        'name' => 'Homework reminder',
        'category' => 'homework',
        'body' => ['en' => 'Dear parent, {student_name} has homework due.', 'am' => 'ውድ ወላጅ፣ {student_name} የቤት ስራ አለበት።'],
    ], branchContext($w['branch']))->assertCreated();

    $this->getJson('/api/v1/chat/templates', branchContext($w['branch']))
        ->assertOk()
        ->assertJsonCount(1, 'data');

    Sanctum::actingAs($w['teacherUser']);
    $this->postJson('/api/v1/chat/templates', [
        'name' => 'Rogue', 'category' => 'general', 'body' => ['en' => 'x'],
    ], branchContext($w['branch']))->assertForbidden();
    $this->getJson('/api/v1/chat/templates', branchContext($w['branch']))->assertForbidden();
});

it('resolves picker templates in the family language with the child named', function () {
    $w = tmplWorld();
    tmplSettings($w['branch'], ['chat_teacher_parent_approval' => 'off']);
    tmplCreate($w['branch'], [
        'en' => '{student_name} was absent today.',
        'am' => '{student_name} ዛሬ ቀርቷል።',
    ]);

    Sanctum::actingAs($w['teacherUser']);
    $conversationId = $this->postJson('/api/v1/chat/conversations', [
        'kind' => 'direct',
        'student_id' => $w['student']->id,
    ], branchContext($w['branch']))->assertCreated()->json('data.id');

    $res = $this->getJson("/api/v1/chat/templates?conversation_id={$conversationId}", branchContext($w['branch']))
        ->assertOk();

    // Mother reads Amharic — the preset arrives in HER language, child named.
    expect($res->json('data.0.resolved_body'))->toBe('Sara ዛሬ ቀርቷል።')
        ->and($res->json('meta.required'))->toBeFalse();
});

it('enforces required mode: free text 422, the preset goes through', function () {
    $w = tmplWorld();
    tmplSettings($w['branch'], [
        'chat_teacher_parent_approval' => 'off',
        'chat_template_mode' => 'required',
    ]);
    tmplCreate($w['branch'], ['en' => '{student_name} was absent today.', 'am' => '{student_name} ዛሬ ቀርቷል።']);

    Sanctum::actingAs($w['teacherUser']);
    $conversationId = $this->postJson('/api/v1/chat/conversations', [
        'kind' => 'direct',
        'student_id' => $w['student']->id,
    ], branchContext($w['branch']))->assertCreated()->json('data.id');

    $this->postJson("/api/v1/chat/conversations/{$conversationId}/messages", [
        'body' => 'Free-hand message the school did not approve.',
    ], branchContext($w['branch']))->assertStatus(422);

    $this->postJson("/api/v1/chat/conversations/{$conversationId}/messages", [
        'body' => 'Sara ዛሬ ቀርቷል።',
    ], branchContext($w['branch']))->assertCreated();

    // Moderators (the director) are never gated — their own family thread
    // takes free text even in required mode.
    Sanctum::actingAs(directorOf($w['branch']));
    $directorThread = $this->postJson('/api/v1/chat/conversations', [
        'kind' => 'direct',
        'student_id' => $w['student']->id,
    ], branchContext($w['branch']))->assertCreated()->json('data.id');

    $this->postJson("/api/v1/chat/conversations/{$directorThread}/messages", [
        'body' => 'Director free text is fine.',
    ], branchContext($w['branch']))->assertCreated();
});

it('blocks forwarding free text into a template-required family thread', function () {
    $w = tmplWorld();
    tmplSettings($w['branch'], ['chat_teacher_parent_approval' => 'off']);

    // Suggested mode first: the teacher legitimately sends free text.
    Sanctum::actingAs($w['teacherUser']);
    $conversationId = $this->postJson('/api/v1/chat/conversations', [
        'kind' => 'direct',
        'student_id' => $w['student']->id,
    ], branchContext($w['branch']))->assertCreated()->json('data.id');

    $messageId = $this->postJson("/api/v1/chat/conversations/{$conversationId}/messages", [
        'body' => 'Old free-hand note.',
    ], branchContext($w['branch']))->assertCreated()->json('data.id');

    // The school flips to required mode — forwarding must not be a side door.
    tmplSettings($w['branch'], ['chat_template_mode' => 'required']);
    tmplCreate($w['branch'], ['en' => '{student_name} was absent today.']);

    $this->postJson("/api/v1/chat/conversations/{$conversationId}/forward", [
        'source_conversation_id' => $conversationId,
        'message_ids' => [$messageId],
    ], branchContext($w['branch']))->assertStatus(422);
});

it('never bricks teachers: required mode without templates lets text through', function () {
    $w = tmplWorld();
    tmplSettings($w['branch'], [
        'chat_teacher_parent_approval' => 'off',
        'chat_template_mode' => 'required',
    ]);
    tmplCreate($w['branch'], ['en' => 'Inactive one.'], active: false);

    Sanctum::actingAs($w['teacherUser']);
    $conversationId = $this->postJson('/api/v1/chat/conversations', [
        'kind' => 'direct',
        'student_id' => $w['student']->id,
    ], branchContext($w['branch']))->assertCreated()->json('data.id');

    $this->postJson("/api/v1/chat/conversations/{$conversationId}/messages", [
        'body' => 'No active templates exist, so this must pass.',
    ], branchContext($w['branch']))->assertCreated();
});

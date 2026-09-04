<?php

use App\Actions\SaveAcademicYearAction;
use App\Models\AttendanceNotificationLog;
use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\Device;
use App\Models\DeviceEvent;
use App\Models\Employee;
use App\Models\EmployeeAttendanceRecord;
use App\Models\GradeLevel;
use App\Models\IdCard;
use App\Models\ParentProfile;
use App\Models\SchoolProgram;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentGuardian;
use App\Models\Term;
use App\Models\User;
use App\Services\Sms\SmsClient;
use App\Support\Ethiopia;
use Database\Seeders\GradeLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(GradeLevelSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->sms = Mockery::spy(SmsClient::class);
    app()->instance(SmsClient::class, $this->sms);
});

// ─── fixtures ────────────────────────────────────────────────────────────

function deviceBranchSetup(Branch $branch): array
{
    $year = (new SaveAcademicYearAction())->execute($branch, ['name' => '2017 E.C.', 'status' => 'active']);
    $section = $branch->sections()->create([
        'school_id' => $branch->school_id,
        'grade_level_id' => GradeLevel::where('code', 'G1')->value('id'),
        'name' => 'A',
    ]);
    // The year action seeds the semesters; anchor the first one on today with
    // a known class start so late-derivation is deterministic.
    $term = Term::where('academic_year_id', $year->id)->orderBy('sequence')->firstOrFail();
    $term->update([
        'starts_on' => now()->subMonth()->toDateString(),
        'ends_on' => now()->addMonths(3)->toDateString(),
        'class_starts_at' => '08:00',
        'is_current' => true,
        'status' => 'active',
    ]);

    return [$section, $year, $term];
}

function enrolledStudent(Branch $branch, Section $section, $year, string $first = 'Abel'): Student
{
    $student = Student::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => $first, 'father_name' => 'Test', 'gender' => 'male',
    ]);
    StudentEnrollment::create([
        'student_id' => $student->id, 'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'academic_year_id' => $year->id, 'school_program_id' => SchoolProgram::defaultFor($branch)->id,
        'section_id' => $section->id,
        'grade_level_id' => $section->grade_level_id, 'status' => 'active', 'enrolled_on' => now(),
    ]);

    return $student;
}

function guardianFor(Student $student, array $overrides = []): User
{
    $user = User::factory()->create(['preferred_language' => 'en']);
    $profile = ParentProfile::create(['user_id' => $user->id, 'first_name' => 'Guard']);
    StudentGuardian::create(array_merge([
        'student_id' => $student->id,
        'parent_id' => $profile->id,
        'relationship' => 'father',
        'is_active' => true,
    ], $overrides));

    return $user;
}

function registerDevice(Branch $branch, string $audience = 'both'): array
{
    $token = Device::mintToken();
    $device = Device::create([
        'school_id' => $branch->school_id,
        'branch_id' => $branch->id,
        'name' => 'Main gate',
        'audience' => $audience,
        'token_hash' => Device::hashToken($token),
    ]);

    return [$device, $token];
}

function cardFor(Branch $branch, $holder, string $uid): IdCard
{
    return IdCard::create([
        'school_id' => $branch->school_id,
        'branch_id' => $branch->id,
        'card_uid' => $uid,
        'holder_type' => $holder->getMorphClass(),
        'holder_id' => $holder->id,
        'issued_on' => now()->toDateString(),
    ]);
}

function scanPayload(string $uid, string $localTime): array
{
    $today = Ethiopia::today();

    return ['events' => [[
        'uid' => $uid,
        'scanned_at' => "{$today}T{$localTime}:00+03:00",
    ]]];
}

// ─── device registry (staff lane) ───────────────────────────────────────

it('lets only Temari.et staff register devices; schools view without tokens', function () {
    $branch = makeBranch();

    // Directors/principals can no longer create, edit or delete devices.
    Sanctum::actingAs(directorOf($branch));
    $this->withHeaders(branchContext($branch))->postJson('/api/v1/devices', [
        'name' => 'Main gate', 'audience' => 'both', 'branch_id' => $branch->id,
    ])->assertForbidden();

    // Platform staff name the school implicitly via the explicit branch_id.
    Sanctum::actingAs(platformAdmin());
    $response = $this->postJson('/api/v1/devices', [
        'name' => 'Main gate', 'audience' => 'both', 'location' => 'Front entrance',
        'branch_id' => $branch->id,
    ])->assertCreated();

    expect($response->json('meta.token'))->toStartWith('tmd_');
    expect($response->json('data.online'))->toBeFalse();

    $deviceId = $response->json('data.id');

    // The school still SEES its device — but no token, no edit, no rotate.
    Sanctum::actingAs(directorOf($branch));
    $list = $this->withHeaders(branchContext($branch))->getJson('/api/v1/devices')->assertOk();
    expect($list->json('data'))->toHaveCount(1);
    expect($list->json('data.0'))->not->toHaveKey('token');
    expect($list->json('data.0'))->not->toHaveKey('token_hash');

    $this->withHeaders(branchContext($branch))
        ->patchJson("/api/v1/devices/{$deviceId}", ['name' => 'Renamed'])->assertForbidden();
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/devices/{$deviceId}/rotate-token")->assertForbidden();
    $this->withHeaders(branchContext($branch))
        ->deleteJson("/api/v1/devices/{$deviceId}")->assertForbidden();
});

it('keeps devices invisible across tenants and forbids teachers', function () {
    $branchA = makeBranch('AA-0001');
    $branchB = makeBranch('AA-0002');
    registerDevice($branchB);

    Sanctum::actingAs(directorOf($branchA));
    $list = $this->withHeaders(branchContext($branchA))->getJson('/api/v1/devices')->assertOk();
    expect($list->json('data'))->toHaveCount(0);

    Sanctum::actingAs(memberOf($branchA));
    $this->withHeaders(branchContext($branchA))->getJson('/api/v1/devices')->assertForbidden();
});

// ─── card lifecycle ──────────────────────────────────────────────────────

it('issues cards from the platform only, with the uniqueness rules intact', function () {
    $branch = makeBranch();
    [$section, $year] = deviceBranchSetup($branch);
    $student = enrolledStudent($branch, $section, $year);

    // The school can no longer issue cards.
    Sanctum::actingAs(directorOf($branch));
    $this->withHeaders(branchContext($branch))->postJson('/api/v1/cards', [
        'holder_type' => 'student', 'holder_id' => $student->id, 'card_uid' => 'aa11bb22',
    ])->assertForbidden();

    Sanctum::actingAs(platformAdmin());
    $card = $this->postJson('/api/v1/cards', [
        'holder_type' => 'student', 'holder_id' => $student->id, 'card_uid' => 'aa11bb22',
    ])->assertCreated()->json('data');

    expect($card['card_uid'])->toBe('AA11BB22');
    expect($card['holder_name'])->toContain('Abel');
    expect($card['issued_on'])->toBe(Ethiopia::today()); // default issue date

    // The same chip cannot be active twice; the same holder cannot hold two.
    $other = enrolledStudent($branch, $section, $year, 'Bini');
    $this->postJson('/api/v1/cards', [
        'holder_type' => 'student', 'holder_id' => $other->id, 'card_uid' => 'AA11BB22',
    ])->assertUnprocessable();
    $this->postJson('/api/v1/cards', [
        'holder_type' => 'student', 'holder_id' => $student->id, 'card_uid' => 'CC33DD44',
    ])->assertUnprocessable();

    // Replacing an ACTIVE card must not trip the one-active-card index
    // (regression: the old card retires before the new one is created).
    $replacement = $this->postJson("/api/v1/cards/{$card['id']}/replace", [
        'card_uid' => 'EE55FF66',
    ])->assertCreated()->json('data');

    expect(IdCard::find($card['id'])->status)->toBe('replaced');
    expect(IdCard::find($card['id'])->replaced_by_id)->toBe($replacement['id']);
    expect(IdCard::find($replacement['id'])->status)->toBe('active');
});

it('runs the lost-card fulfilment pipeline: school reports, platform delivers', function () {
    $branch = makeBranch();
    [$section, $year] = deviceBranchSetup($branch);
    $student = enrolledStudent($branch, $section, $year);
    $card = cardFor($branch, $student, 'AA11BB22');
    $director = directorOf($branch);

    // School side: one action marks the card lost AND opens the request.
    Sanctum::actingAs($director);
    $report = $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/cards/{$card->id}/report-lost", ['note' => 'Left in a taxi'])
        ->assertCreated();
    $requestId = $report->json('data.request_id');

    expect($card->refresh()->status)->toBe('lost');

    // No double-reporting while a request is open.
    $card2 = cardFor($branch, enrolledStudent($branch, $section, $year, 'Bini'), 'BB22CC33');
    $this->withHeaders(branchContext($branch))
        ->postJson("/api/v1/cards/{$card->id}/report-lost")->assertUnprocessable(); // not active anymore

    // The school follows its request read-only…
    $mine = $this->withHeaders(branchContext($branch))->getJson('/api/v1/card-requests')->assertOk();
    expect($mine->json('data.0.status'))->toBe('requested');
    expect($mine->json('data.0.note'))->toBe('Left in a taxi');
    // …but cannot drive the pipeline.
    $this->withHeaders(branchContext($branch))
        ->patchJson("/api/v1/card-requests/{$requestId}", ['status' => 'delivered'])->assertForbidden();

    // Platform side: delivered requires the replacement chip first.
    Sanctum::actingAs(platformAdmin());
    $this->patchJson("/api/v1/card-requests/{$requestId}", ['status' => 'delivered'])->assertUnprocessable();

    $issued = $this->postJson("/api/v1/card-requests/{$requestId}/issue", [
        'card_uid' => 'EE55FF66',
    ])->assertCreated();
    expect($issued->json('data.status'))->toBe('preparing');
    expect($issued->json('data.new_card_uid'))->toBe('EE55FF66');
    expect(IdCard::find($card->id)->replaced_by_id)->not->toBeNull();

    $this->patchJson("/api/v1/card-requests/{$requestId}", ['status' => 'delivering'])->assertOk();
    $delivered = $this->patchJson("/api/v1/card-requests/{$requestId}", ['status' => 'delivered'])->assertOk();
    expect($delivered->json('data.status'))->toBe('delivered');

    // The new chip scans as the student immediately.
    expect(IdCard::where('card_uid', 'EE55FF66')->value('status'))->toBe('active');
    expect($card2->refresh()->status)->toBe('active'); // untouched bystander
});

it('bulk-issues cards to everyone in a branch without one', function () {
    $branch = makeBranch();
    [$section, $year] = deviceBranchSetup($branch);
    $abel = enrolledStudent($branch, $section, $year, 'Abel');
    $bini = enrolledStudent($branch, $section, $year, 'Bini');
    $carded = enrolledStudent($branch, $section, $year, 'Carded');
    cardFor($branch, $carded, 'FF00FF00');

    Sanctum::actingAs(platformAdmin());

    // The worklist lists only people WITHOUT an active card, A to Z, with the
    // class as its own fields for the studio's grade/section filters.
    $candidates = $this->getJson("/api/v1/cards/candidates?branch_id={$branch->id}&type=student")->assertOk();
    expect($candidates->json('data.0.name'))->toContain('Abel');
    expect($candidates->json('data.1.name'))->toContain('Bini');
    expect(collect($candidates->json('data'))->pluck('id'))->not->toContain($carded->id);
    expect($candidates->json('data.0.grade'))->toBe('Grade 1');
    expect($candidates->json('data.0.section'))->toBe('A');

    $this->postJson('/api/v1/cards/bulk', [
        'branch_id' => $branch->id,
        'holder_type' => 'student',
        'rows' => [
            ['holder_id' => $abel->id, 'card_uid' => '11110000', 'note' => 'Batch 1'],
            ['holder_id' => $bini->id, 'card_uid' => '22220000'],
        ],
    ])->assertCreated()->assertJsonPath('meta.issued', 2);

    expect(IdCard::where('status', 'active')->count())->toBe(3);

    // Duplicate UIDs inside one batch are rejected atomically.
    $more = enrolledStudent($branch, $section, $year, 'Dup');
    $this->postJson('/api/v1/cards/bulk', [
        'branch_id' => $branch->id,
        'holder_type' => 'student',
        'rows' => [
            ['holder_id' => $more->id, 'card_uid' => '33330000'],
            ['holder_id' => $more->id, 'card_uid' => '33330000'],
        ],
    ])->assertUnprocessable();
});

it('shares the integration docs behind a rotatable public token', function () {
    $branch = makeBranch();

    // School staff never manage the public share.
    Sanctum::actingAs(directorOf($branch));
    $this->withHeaders(branchContext($branch))->getJson('/api/v1/device-docs-share')->assertForbidden();
    $this->withHeaders(branchContext($branch))->postJson('/api/v1/device-docs-share/rotate')->assertForbidden();

    Sanctum::actingAs(platformAdmin());

    // Off by default: no token, and the public lane 404s on anything.
    expect($this->getJson('/api/v1/device-docs-share')->assertOk()->json('data.token'))->toBeNull();
    $this->getJson('/api/v1/public/device-docs/whatever')->assertNotFound();

    // Generate → the public lane opens for exactly that token.
    $token = $this->postJson('/api/v1/device-docs-share/rotate')->assertOk()->json('data.token');
    expect($token)->toBeString()->and(strlen($token))->toBe(48);
    $this->getJson("/api/v1/public/device-docs/{$token}")->assertOk()->assertJsonPath('data.valid', true);
    $this->getJson('/api/v1/public/device-docs/wrong-token')->assertNotFound();

    // Rotate → the old link dies instantly, the new one works.
    $fresh = $this->postJson('/api/v1/device-docs-share/rotate')->assertOk()->json('data.token');
    expect($fresh)->not->toBe($token);
    $this->getJson("/api/v1/public/device-docs/{$token}")->assertNotFound();
    $this->getJson("/api/v1/public/device-docs/{$fresh}")->assertOk();

    // Revoke → sharing is off entirely.
    $this->deleteJson('/api/v1/device-docs-share')->assertOk();
    expect($this->getJson('/api/v1/device-docs-share')->json('data.token'))->toBeNull();
    $this->getJson("/api/v1/public/device-docs/{$fresh}")->assertNotFound();
});

// ─── machine lane ────────────────────────────────────────────────────────

it('rejects the device lane without a valid token', function () {
    $this->postJson('/api/v1/device/heartbeat')->assertUnauthorized();
    $this->withHeader('Authorization', 'Bearer tmd_wrong')
        ->postJson('/api/v1/device/heartbeat')->assertUnauthorized();
});

it('serves the offline-verification roster scoped to branch, audience and live holders', function () {
    $branch = makeBranch();
    [$section, $year] = deviceBranchSetup($branch);

    // In: an enrolled student and an active employee, each with an active card.
    $student = enrolledStudent($branch, $section, $year);
    cardFor($branch, $student, 'STU00001');
    $employee = Employee::create([
        'user_id' => User::factory()->create()->id,
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => 'Meles',
    ]);
    cardFor($branch, $employee, 'EMP00001');

    // Out: a lost card, a card whose student has no enrollment, an inactive
    // employee's card, and another branch's card.
    $lost = enrolledStudent($branch, $section, $year, 'Bini');
    cardFor($branch, $lost, 'STU00002')->deactivate('lost');
    $ghost = Student::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => 'Ghost', 'father_name' => 'Test', 'gender' => 'male',
    ]);
    cardFor($branch, $ghost, 'STU00003');
    $former = Employee::create([
        'user_id' => User::factory()->create()->id,
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => 'Left', 'is_active' => false,
    ]);
    cardFor($branch, $former, 'EMP00002');
    $other = makeBranch('AA-0002');
    [$otherSection, $otherYear] = deviceBranchSetup($other);
    cardFor($other, enrolledStudent($other, $otherSection, $otherYear, 'Sara'), 'STU00009');

    [$device, $token] = registerDevice($branch);

    // No token, no roster — same machine lane as the other verbs.
    $this->getJson('/api/v1/device/roster')->assertUnauthorized();

    $roster = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/device/roster')->assertOk();

    expect($roster->json('data'))->toBe([
        'students' => ['STU00001'],
        'employees' => ['EMP00001'],
    ]);
    expect($roster->json('meta.students'))->toBe(1);
    expect($roster->json('meta.employees'))->toBe(1);
    expect($roster->json('meta.version'))->toBeString();
    expect($device->refresh()->last_roster_at)->not->toBeNull();

    // The heartbeat advertises the same version — the terminal re-pulls only
    // when the version it stored no longer matches.
    $beat = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/device/heartbeat')->assertOk();
    expect($beat->json('data.roster_version'))->toBe($roster->json('meta.version'));

    // A students-only terminal never learns employee cards (and its version
    // differs, because its roster does).
    [, $studentToken] = registerDevice($branch, 'students');
    $studentsOnly = $this->withHeader('Authorization', "Bearer {$studentToken}")
        ->getJson('/api/v1/device/roster')->assertOk();
    expect($studentsOnly->json('data'))->toBe([
        'students' => ['STU00001'],
        'employees' => [],
    ]);
    expect($studentsOnly->json('meta.version'))->not->toBe($roster->json('meta.version'));
});

it('accepts a batch idempotently and processes scans into attendance', function () {
    $branch = makeBranch();
    [$section, $year] = deviceBranchSetup($branch);
    $student = enrolledStudent($branch, $section, $year);
    cardFor($branch, $student, 'AA11BB22');
    [, $token] = registerDevice($branch);

    // On-time scan (class starts 08:00, grace 15).
    $first = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/device/events', scanPayload('AA11BB22', '07:55'))
        ->assertOk();
    expect($first->json('data.accepted'))->toBe(1);

    $record = AttendanceRecord::where('student_id', $student->id)->first();
    expect($record->status->value)->toBe('present');
    expect($record->source)->toBe('device');
    expect(substr((string) $record->check_in, 0, 5))->toBe('07:55');

    // The exact same batch again: swallowed silently.
    $again = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/device/events', scanPayload('AA11BB22', '07:55'))
        ->assertOk();
    expect($again->json('data.accepted'))->toBe(0);
    expect($again->json('data.duplicates'))->toBe(1);
    expect(AttendanceRecord::where('student_id', $student->id)->count())->toBe(1);

    // Afternoon scan extends the day (check-out), never the status.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/device/events', scanPayload('AA11BB22', '15:40'))
        ->assertOk();
    $record->refresh();
    expect(substr((string) $record->check_out, 0, 5))->toBe('15:40');
    expect($record->status->value)->toBe('present');
});

it('marks a scan after class start + grace as late and keeps unknown cards for audit', function () {
    $branch = makeBranch();
    [$section, $year] = deviceBranchSetup($branch);
    $student = enrolledStudent($branch, $section, $year);
    cardFor($branch, $student, 'AA11BB22');
    [$device, $token] = registerDevice($branch);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/device/events', [
            'events' => [
                ['uid' => 'AA11BB22', 'scanned_at' => Ethiopia::today().'T08:30:00+03:00'],
                ['uid' => 'DEADBEEF', 'scanned_at' => Ethiopia::today().'T08:31:00+03:00'],
            ],
        ])->assertOk();

    expect(AttendanceRecord::where('student_id', $student->id)->value('status')->value)->toBe('late');
    expect(DeviceEvent::where('card_uid', 'DEADBEEF')->value('status'))->toBe('unknown_card');
});

it('never overwrites a manual mark and rejects a lost card', function () {
    $branch = makeBranch();
    [$section, $year, $term] = deviceBranchSetup($branch);
    $student = enrolledStudent($branch, $section, $year);
    $card = cardFor($branch, $student, 'AA11BB22');
    [, $token] = registerDevice($branch);

    // Teacher already marked the student excused.
    AttendanceRecord::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'section_id' => $section->id, 'student_id' => $student->id,
        'academic_year_id' => $year->id, 'term_id' => $term->id,
        'date' => Ethiopia::today(), 'status' => 'excused', 'source' => 'manual',
    ]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/device/events', scanPayload('AA11BB22', '08:10'))
        ->assertOk();

    $record = AttendanceRecord::where('student_id', $student->id)->first();
    expect($record->status->value)->toBe('excused'); // manual wins
    expect(substr((string) $record->check_in, 0, 5))->toBe('08:10'); // blank filled

    // Lost card: scan rejected, no data mutated.
    $card->deactivate('lost');
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/device/events', scanPayload('AA11BB22', '09:00'))
        ->assertOk();
    expect(DeviceEvent::latest('id')->value('status'))->toBe('inactive_card');
});

it('derives employee attendance from scans against their own schedule', function () {
    $branch = makeBranch();
    $employee = Employee::create([
        'user_id' => User::factory()->create()->id,
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => 'Meles', 'check_in' => '08:00', 'check_out' => '17:00',
    ]);
    cardFor($branch, $employee, 'EMP00001');
    [, $token] = registerDevice($branch, 'employees');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/device/events', scanPayload('EMP00001', '08:30'))
        ->assertOk();

    $record = EmployeeAttendanceRecord::where('employee_id', $employee->id)->first();
    expect($record->status->value)->toBe('late');
    expect($record->source)->toBe('device');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/device/events', scanPayload('EMP00001', '17:05'))
        ->assertOk();
    expect(substr((string) $record->refresh()->check_out, 0, 5))->toBe('17:05');
});

// ─── auto-absent sweep ───────────────────────────────────────────────────

it('sweeps unscanned students absent after the cutoff when device mode is on', function () {
    Carbon::setTestNow(
        Carbon::parse(Ethiopia::today().' 10:00:00', Ethiopia::TIMEZONE)
            ->subDays(Ethiopia::now()->isWeekend() ? 2 : 0)
    );

    $branch = makeBranch();
    [$section, $year] = deviceBranchSetup($branch);
    $scanned = enrolledStudent($branch, $section, $year, 'Abel');
    $missing = enrolledStudent($branch, $section, $year, 'Bini');

    // Only device-mode branches sweep.
    $this->artisan('attendance:auto-absent')->assertSuccessful();
    expect(AttendanceRecord::count())->toBe(0);

    $branch->update(['settings' => ['device_auto_absent' => true, 'device_absent_cutoff' => '09:30']]);

    cardFor($branch, $scanned, 'AA11BB22');
    [, $token] = registerDevice($branch);
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/device/events', scanPayload('AA11BB22', '07:55'));

    $this->artisan('attendance:auto-absent')->assertSuccessful();

    expect(AttendanceRecord::where('student_id', $scanned->id)->value('status')->value)->toBe('present');
    expect(AttendanceRecord::where('student_id', $missing->id)->value('status')->value)->toBe('absent');

    // Re-running changes nothing.
    $this->artisan('attendance:auto-absent')->assertSuccessful();
    expect(AttendanceRecord::count())->toBe(2);

    Carbon::setTestNow();
});

// ─── guardian alerts ─────────────────────────────────────────────────────

it('texts and emails the guardian once when a student is marked absent', function () {
    $branch = makeBranch();
    [$section, $year] = deviceBranchSetup($branch);
    $student = enrolledStudent($branch, $section, $year);
    $guardian = guardianFor($student);
    Sanctum::actingAs(directorOf($branch));

    $save = fn () => $this->postJson("/api/v1/sections/{$section->id}/attendance", [
        'date' => Ethiopia::today(),
        'records' => [['student_id' => $student->id, 'status' => 'absent']],
    ])->assertOk();

    $save();

    $this->sms->shouldHaveReceived('send')->once();
    expect(AttendanceNotificationLog::where('channel', 'sms')->count())->toBe(1);
    expect(AttendanceNotificationLog::where('channel', 'sms')->value('recipient'))->toBe($guardian->phone);

    // Re-saving the register never double-texts.
    $save();
    $this->sms->shouldHaveReceived('send')->once();
    expect(AttendanceNotificationLog::where('channel', 'sms')->count())->toBe(1);
});

it('honors the notify-late policy, guardian flags and the branch off-switch', function () {
    $branch = makeBranch();
    [$section, $year] = deviceBranchSetup($branch);

    // Late alerts are off by default.
    $late = enrolledStudent($branch, $section, $year, 'Late');
    guardianFor($late);
    Sanctum::actingAs(directorOf($branch));
    $this->postJson("/api/v1/sections/{$section->id}/attendance", [
        'date' => Ethiopia::today(),
        'records' => [['student_id' => $late->id, 'status' => 'late', 'check_in' => '08:40']],
    ])->assertOk();
    $this->sms->shouldNotHaveReceived('send');

    // Branch override turns late alerts on. Saving the register notifies the
    // new late mark AND the earlier one (it was never alerted — dedupe only
    // blocks repeats, not first sends after a policy change).
    $branch->update(['settings' => ['attendance_sms_late' => true]]);
    $late2 = enrolledStudent($branch, $section, $year, 'Later');
    guardianFor($late2);
    $this->postJson("/api/v1/sections/{$section->id}/attendance", [
        'date' => Ethiopia::today(),
        'records' => [['student_id' => $late2->id, 'status' => 'late', 'check_in' => '08:45']],
    ])->assertOk();
    $this->sms->shouldHaveReceived('send')->twice();

    // A guardian whose link says no-SMS is skipped.
    $silent = enrolledStudent($branch, $section, $year, 'Silent');
    guardianFor($silent, ['can_receive_sms' => false]);
    $this->postJson("/api/v1/sections/{$section->id}/attendance", [
        'date' => Ethiopia::today(),
        'records' => [['student_id' => $silent->id, 'status' => 'absent']],
    ])->assertOk();
    $this->sms->shouldHaveReceived('send')->twice(); // unchanged

    // The whole branch can switch alerts off.
    $branch->update(['settings' => ['attendance_sms_enabled' => false]]);
    $muted = enrolledStudent($branch, $section, $year, 'Muted');
    guardianFor($muted);
    $this->postJson("/api/v1/sections/{$section->id}/attendance", [
        'date' => Ethiopia::today(),
        'records' => [['student_id' => $muted->id, 'status' => 'absent']],
    ])->assertOk();
    $this->sms->shouldHaveReceived('send')->twice(); // still unchanged
});

// ─── parent visibility (/me lane) ────────────────────────────────────────

it('shows a parent the day-by-day register with in/out times, gated per link', function () {
    $branch = makeBranch();
    [$section, $year] = deviceBranchSetup($branch);
    $student = enrolledStudent($branch, $section, $year);
    $guardian = guardianFor($student);
    Sanctum::actingAs(directorOf($branch));

    $this->postJson("/api/v1/sections/{$section->id}/attendance", [
        'date' => Ethiopia::today(),
        'records' => [['student_id' => $student->id, 'status' => 'present', 'check_in' => '07:58', 'check_out' => '15:42']],
    ])->assertOk();

    Sanctum::actingAs($guardian);
    $month = substr(Ethiopia::today(), 0, 7);
    $response = $this->getJson("/api/v1/me/children/{$student->id}/attendance?month={$month}")->assertOk();

    expect($response->json('data.0.check_in'))->toBe('07:58');
    expect($response->json('data.0.check_out'))->toBe('15:42');
    expect($response->json('meta.counts.present'))->toBe(1);

    // Year-to-date vitals ride along for the header — rate, counts, streak.
    expect($response->json('meta.summary.total'))->toBe(1);
    expect($response->json('meta.summary.present'))->toBe(1);
    expect($response->json('meta.summary.rate'))->toBe(100);
    expect($response->json('meta.summary.streak'))->toBe(1);

    // The per-link gate applies.
    StudentGuardian::where('student_id', $student->id)->update(['can_view_attendance' => false]);
    $this->getJson("/api/v1/me/children/{$student->id}/attendance?month={$month}")->assertForbidden();
});

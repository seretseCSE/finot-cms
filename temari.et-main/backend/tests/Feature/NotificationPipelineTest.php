<?php

use App\Enums\Role;
use App\Models\Membership;
use App\Models\Notification;
use App\Models\ParentProfile;
use App\Models\PlatformSetting;
use App\Models\Student;
use App\Models\StudentGuardian;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\Notify\Notifier;
use App\Services\Sms\SmsClient;
use App\Support\NotificationCatalog;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

/*
 * Guard rail for the notification pipeline (ADR-018): the in-app feed is
 * always written; SMS obeys the PLATFORM whitelist × the user's master
 * switch × per-category prefs; the feed API is strictly self-scoped; the
 * whitelist is platform-staff-only; new-device sign-ins alert.
 */

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->sms = Mockery::spy(SmsClient::class);
    app()->instance(SmsClient::class, $this->sms);
    NotificationCatalog::flushWhitelistCache();
});

function notifier(): Notifier
{
    return app(Notifier::class);
}

function familyStudent($branch, string $guardianPhone = '0911223344'): array
{
    $student = Student::create([
        'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'first_name' => 'Sara', 'father_name' => 'Bekele', 'gender' => 'female',
    ]);

    $guardianUser = User::factory()->create(['phone' => $guardianPhone]);
    $parent = ParentProfile::create(['user_id' => $guardianUser->id]);
    StudentGuardian::create([
        'student_id' => $student->id, 'parent_id' => $parent->id,
        'relationship' => 'mother', 'is_active' => true,
        'is_primary' => true, 'can_receive_sms' => true,
        'can_view_grades' => true, 'can_view_attendance' => true, 'can_pay_fees' => true,
    ]);

    return [$student, $guardianUser];
}

// ── Dispatch legs ───────────────────────────────────────────────────────────

it('always writes the in-app feed row and localizes it at read time', function () {
    $user = User::factory()->create(['preferred_language' => 'am', 'notify_via_sms' => false, 'notify_via_email' => false]);

    notifier()->toUser($user, 'security.password_changed', [], ['link' => '/settings']);

    $row = Notification::where('user_id', $user->id)->first();
    expect($row)->not->toBeNull()
        ->and($row->event)->toBe('security.password_changed')
        ->and($row->category)->toBe('security');

    Sanctum::actingAs($user);
    $feed = $this->getJson('/api/v1/me/notifications')->assertOk()->json('data');
    expect($feed[0]['title'])->toBe('የይለፍ ቃልዎ ተቀይሯል');
});

it('rejects events that are not in the catalog', function () {
    notifier()->toUser(User::factory()->create(), 'made.up_event');
})->throws(InvalidArgumentException::class);

it('sends SMS only for whitelisted events', function () {
    $user = User::factory()->create(['phone' => '0911000001']);

    // invoice_issued is whitelisted by default; timetable_published is not.
    notifier()->toUser($user, 'finance.invoice_issued', ['student' => 'Sara', 'fee' => 'Tuition', 'amount' => '500']);
    notifier()->toUser($user, 'academics.timetable_published', ['term' => 'Semester 1']);

    $this->sms->shouldHaveReceived('send')->once();
    expect(Notification::where('user_id', $user->id)->count())->toBe(2);
});

it('stops SMS when the platform operator removes an event from the whitelist', function () {
    PlatformSetting::set(NotificationCatalog::SMS_WHITELIST_KEY, []);
    NotificationCatalog::flushWhitelistCache();

    $user = User::factory()->create(['phone' => '0911000002']);
    notifier()->toUser($user, 'finance.invoice_issued', ['student' => 'Sara', 'fee' => 'Tuition', 'amount' => '500']);

    $this->sms->shouldNotHaveReceived('send');
    expect(Notification::where('user_id', $user->id)->count())->toBe(1);
});

it('respects the master SMS switch and per-category mutes, but critical events pierce category mutes', function () {
    // Master off → nothing texts, ever.
    $off = User::factory()->create(['phone' => '0911000003', 'notify_via_sms' => false]);
    notifier()->toUser($off, 'finance.invoice_issued', ['student' => 'S', 'fee' => 'T', 'amount' => '1']);
    $this->sms->shouldNotHaveReceived('send');

    // Category muted → non-critical stays silent, critical still texts.
    $muted = User::factory()->create([
        'phone' => '0911000004',
        'notification_preferences' => ['finance' => ['sms' => false], 'security' => ['sms' => false]],
    ]);
    notifier()->toUser($muted, 'finance.payment_verified', ['student' => 'S', 'amount' => '1']); // important → muted
    $this->sms->shouldNotHaveReceived('send');
    notifier()->toUser($muted, 'security.new_device', ['device' => 'Chrome on Android']); // critical → sends
    $this->sms->shouldHaveReceived('send')->once();
});

it('notifies the whole family once per user and honours the guardian link SMS consent', function () {
    $branch = makeBranch();
    [$student, $guardian] = familyStudent($branch);
    StudentGuardian::query()->update(['can_receive_sms' => false]);

    notifier()->toFamily($student->fresh(), 'finance.invoice_issued', ['fee' => 'Tuition', 'amount' => '500'], []);

    expect(Notification::where('user_id', $guardian->id)->count())->toBe(1);
    $this->sms->shouldNotHaveReceived('send'); // link consent off
});

it('folds dedupe-keyed repeats into one unread row with a running count', function () {
    $teacher = User::factory()->create();

    notifier()->toUser($teacher, 'lms.submission_received', ['title' => 'Essay 2'], ['dedupeKey' => 'submission:9']);
    notifier()->toUser($teacher, 'lms.submission_received', ['title' => 'Essay 2'], ['dedupeKey' => 'submission:9']);
    notifier()->toUser($teacher, 'lms.submission_received', ['title' => 'Essay 2'], ['dedupeKey' => 'submission:9']);

    $rows = Notification::where('user_id', $teacher->id)->get();
    expect($rows)->toHaveCount(1)
        ->and($rows->first()->data['count'])->toBe(3);
});

it('resolves staff audiences from the membership kernel and never leaks across schools', function () {
    $branch = makeBranch();
    $other = makeBranch('AA-0002');

    $finance = User::factory()->create();
    Membership::create([
        'user_id' => $finance->id, 'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'role' => Role::FinanceOfficer->value, 'scope' => Role::FinanceOfficer->scope()->value, 'is_active' => true,
    ]);
    $otherFinance = User::factory()->create();
    Membership::create([
        'user_id' => $otherFinance->id, 'school_id' => $other->school_id, 'branch_id' => $other->id,
        'role' => Role::FinanceOfficer->value, 'scope' => Role::FinanceOfficer->scope()->value, 'is_active' => true,
    ]);
    $teacher = User::factory()->create();
    Membership::create([
        'user_id' => $teacher->id, 'school_id' => $branch->school_id, 'branch_id' => $branch->id,
        'role' => Role::Teacher->value, 'scope' => Role::Teacher->scope()->value, 'is_active' => true,
    ]);

    notifier()->toStaff($branch->school_id, $branch->id, 'payments.record', 'finance.payment_submitted', [
        'student' => 'Sara', 'amount' => '500',
    ]);

    expect(Notification::where('user_id', $finance->id)->count())->toBe(1)
        ->and(Notification::where('user_id', $otherFinance->id)->count())->toBe(0)
        ->and(Notification::where('user_id', $teacher->id)->count())->toBe(0);
});

// ── Feed API ────────────────────────────────────────────────────────────────

it('serves only the caller their own feed, with unread counts and read tracking', function () {
    $me = User::factory()->create();
    $stranger = User::factory()->create();
    notifier()->toUser($me, 'hr.payslip_ready', ['period' => 'Meskerem']);
    notifier()->toUser($stranger, 'hr.payslip_ready', ['period' => 'Meskerem']);

    Sanctum::actingAs($me);

    $this->getJson('/api/v1/me/notifications/unread-count')->assertOk()->assertJsonPath('data.unread', 1);

    $feed = $this->getJson('/api/v1/me/notifications')->assertOk();
    expect($feed->json('data'))->toHaveCount(1);

    $strangerRow = Notification::where('user_id', $stranger->id)->first();
    $this->postJson("/api/v1/me/notifications/{$strangerRow->id}/read")->assertNotFound();

    $mine = Notification::where('user_id', $me->id)->first();
    $this->postJson("/api/v1/me/notifications/{$mine->id}/read")->assertOk();
    $this->getJson('/api/v1/me/notifications/unread-count')->assertOk()->assertJsonPath('data.unread', 0);

    notifier()->toUser($me, 'hr.payslip_ready', ['period' => 'Tikimt']);
    $this->postJson('/api/v1/me/notifications/read-all')->assertOk();
    expect(Notification::where('user_id', $me->id)->whereNull('read_at')->count())->toBe(0);
});

it('saves per-category notification preferences through /me/preferences', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->putJson('/api/v1/me/preferences', [
        'notification_preferences' => ['lms' => ['email' => false], 'bogus_category' => ['sms' => false]],
    ])->assertOk();

    expect($user->refresh()->notification_preferences)
        ->toHaveKey('lms')
        ->not->toHaveKey('bogus_category');
});

// ── Platform SMS whitelist API ──────────────────────────────────────────────

it('lets only platform catalog managers read and edit the SMS whitelist', function () {
    $branch = makeBranch();
    $director = directorOf($branch);
    Sanctum::actingAs($director);
    $this->getJson('/api/v1/catalogs/notification-events')->assertForbidden();

    $admin = User::factory()->create();
    grantPlatformRole($admin, Role::SuperAdmin);
    Sanctum::actingAs($admin);

    $index = $this->getJson('/api/v1/catalogs/notification-events')->assertOk()->json('data');
    expect(collect($index['events'])->firstWhere('event', 'finance.invoice_issued')['sms_enabled'])->toBeTrue();

    $this->putJson('/api/v1/catalogs/notification-events', [
        'sms_whitelist' => ['security.new_device'],
    ])->assertOk();

    NotificationCatalog::flushWhitelistCache();
    expect(NotificationCatalog::smsAllowed('finance.invoice_issued'))->toBeFalse()
        ->and(NotificationCatalog::smsAllowed('security.new_device'))->toBeTrue();

    $this->putJson('/api/v1/catalogs/notification-events', [
        'sms_whitelist' => ['not.an_event'],
    ])->assertUnprocessable();
});

// ── New-device security alert ───────────────────────────────────────────────

it('alerts on a sign-in from a new device but stays silent on the first ever device', function () {
    $user = User::factory()->create(['phone' => '0911000005', 'password' => Hash::make('secret123')]);

    $login = fn (string $agent) => $this->postJson('/api/v1/auth/login', [
        'identifier' => $user->phone, 'password' => 'secret123',
    ], ['User-Agent' => $agent]);

    $login('Mozilla/5.0 (Linux; Android 14) Chrome/125.0')->assertOk();
    expect(Notification::where('user_id', $user->id)->where('event', 'security.new_device')->count())->toBe(0);

    $login('Mozilla/5.0 (Linux; Android 14) Chrome/125.0')->assertOk();
    expect(Notification::where('user_id', $user->id)->where('event', 'security.new_device')->count())->toBe(0);

    $login('Mozilla/5.0 (Windows NT 10.0) Firefox/126.0')->assertOk();
    expect(Notification::where('user_id', $user->id)->where('event', 'security.new_device')->count())->toBe(1)
        ->and(UserDevice::where('user_id', $user->id)->count())->toBe(2);
});

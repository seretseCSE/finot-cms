<?php

namespace App\Support;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Lang;

/**
 * THE single source of truth for every notification event the platform can
 * emit. Every event a user should be aware of is declared here BEFORE it is
 * dispatched — the Notifier refuses unknown keys. The catalog drives:
 *
 *  - the platform SMS whitelist (SMS costs money per message, so which events
 *    may text is a Temari.et operator decision — `sms` below is only the
 *    DEFAULT; the live whitelist is the `notifications.sms_whitelist`
 *    platform setting, edited in the catalog studio),
 *  - default email behavior per event,
 *  - the user settings matrix (grouped by CATEGORY — users mute categories
 *    per channel, never individual events),
 *  - severity: `critical` events ignore per-category mutes (a parent must
 *    never silently miss "your child is absent" or "new device signed in");
 *    they still respect the master notify_via_* switches, except where a
 *    dedicated flow (password setup) already bypasses them.
 *
 * In-app rows are ALWAYS written — the feed is the system of record the
 * cheap channels summarize. i18n copy lives in lang/{en,am,om}/notifications.php
 * under the event key (`title`, `body`, optional `sms`).
 *
 * NEW-FEATURE RULE: any feature that creates something a user should react
 * to (an approval to decide, money to pay, work to review, a status that
 * changed) MUST register its events here and dispatch through
 * App\Services\Notify\Notifier — never hand-roll SMS/email again.
 */
class NotificationCatalog
{
    public const SMS_WHITELIST_KEY = 'notifications.sms_whitelist';

    public const CATEGORIES = [
        'security', 'finance', 'attendance', 'academics',
        'lms', 'chat', 'movement', 'approvals', 'hr', 'family', 'tutoring', 'system',
    ];

    public const SEVERITY_CRITICAL = 'critical';

    public const SEVERITY_IMPORTANT = 'important';

    public const SEVERITY_INFO = 'info';

    /**
     * event => [category, severity, sms default, email default].
     * `sms` = in the DEFAULT platform whitelist; `email` = sent via the
     * generic NotificationMail when the recipient allows email (events whose
     * notifier sends a bespoke mailable set `email: false` here so the
     * pipeline doesn't double-send).
     *
     * @var array<string, array{category: string, severity: string, sms: bool, email: bool}>
     */
    public const EVENTS = [
        // ── Security (always delivered; category mute ignored) ────────────
        'security.new_device' => ['category' => 'security', 'severity' => 'critical', 'sms' => true,  'email' => true],
        'security.password_changed' => ['category' => 'security', 'severity' => 'critical', 'sms' => true,  'email' => true],

        // ── Finance — family side ──────────────────────────────────────────
        'finance.invoice_issued' => ['category' => 'finance', 'severity' => 'critical', 'sms' => true,  'email' => true],
        'finance.fee_reminder' => ['category' => 'finance', 'severity' => 'critical', 'sms' => true,  'email' => false], // SMS/email sent by FeeReminderService ladder
        'finance.fee_notice' => ['category' => 'finance', 'severity' => 'important', 'sms' => true,  'email' => false], // bespoke InvoiceNoticeMail
        'finance.payment_received' => ['category' => 'finance', 'severity' => 'important', 'sms' => true,  'email' => false], // bespoke PaymentReceiptMail via DocumentNotifier
        'finance.payment_verified' => ['category' => 'finance', 'severity' => 'important', 'sms' => true,  'email' => true],
        'finance.payment_rejected' => ['category' => 'finance', 'severity' => 'critical', 'sms' => true,  'email' => true],
        'finance.concession_granted' => ['category' => 'finance', 'severity' => 'important', 'sms' => false, 'email' => true],

        // ── Finance — staff side ───────────────────────────────────────────
        'finance.payment_submitted' => ['category' => 'approvals', 'severity' => 'important', 'sms' => false, 'email' => false],
        'finance.concession_suggested' => ['category' => 'approvals', 'severity' => 'important', 'sms' => false, 'email' => false],
        'finance.expense_submitted' => ['category' => 'approvals', 'severity' => 'important', 'sms' => false, 'email' => false],
        'finance.expense_decided' => ['category' => 'finance', 'severity' => 'important', 'sms' => false, 'email' => false],

        // ── Attendance ─────────────────────────────────────────────────────
        'attendance.absent' => ['category' => 'attendance', 'severity' => 'critical', 'sms' => true, 'email' => false], // bespoke AttendanceAlertMail
        'attendance.late' => ['category' => 'attendance', 'severity' => 'important', 'sms' => true, 'email' => false],
        'attendance.excuse_filed' => ['category' => 'approvals', 'severity' => 'important', 'sms' => false, 'email' => false],
        'attendance.excuse_decided' => ['category' => 'attendance', 'severity' => 'important', 'sms' => false, 'email' => false],

        // ── Academics ──────────────────────────────────────────────────────
        'academics.term_results_published' => ['category' => 'academics', 'severity' => 'important', 'sms' => false, 'email' => true],
        'academics.enrollment_activated' => ['category' => 'academics', 'severity' => 'important', 'sms' => true,  'email' => true],
        'academics.enrollment_reverted' => ['category' => 'academics', 'severity' => 'important', 'sms' => false, 'email' => false],
        'academics.timetable_published' => ['category' => 'academics', 'severity' => 'info', 'sms' => false, 'email' => false],
        'academics.section_assigned' => ['category' => 'academics', 'severity' => 'info', 'sms' => false, 'email' => false],
        'academics.marklist_submitted' => ['category' => 'approvals', 'severity' => 'important', 'sms' => false, 'email' => false],
        'academics.marklist_decided' => ['category' => 'academics', 'severity' => 'important', 'sms' => false, 'email' => false],
        'academics.marklist_assist' => ['category' => 'academics', 'severity' => 'important', 'sms' => false, 'email' => false],
        'academics.marklist_reminder' => ['category' => 'academics', 'severity' => 'important', 'sms' => false, 'email' => false],
        'academics.annual_plan_submitted' => ['category' => 'approvals', 'severity' => 'important', 'sms' => false, 'email' => false],
        'academics.annual_plan_decided' => ['category' => 'academics', 'severity' => 'important', 'sms' => false, 'email' => false],
        'academics.weekly_plan_submitted' => ['category' => 'approvals', 'severity' => 'important', 'sms' => false, 'email' => false],
        'academics.weekly_plan_decided' => ['category' => 'academics', 'severity' => 'important', 'sms' => false, 'email' => false],

        // ── LMS ────────────────────────────────────────────────────────────
        'lms.assignment_published' => ['category' => 'lms', 'severity' => 'info', 'sms' => false, 'email' => false],
        'lms.assignment_graded' => ['category' => 'lms', 'severity' => 'important', 'sms' => false, 'email' => false],
        'lms.submission_received' => ['category' => 'lms', 'severity' => 'info', 'sms' => false, 'email' => false],
        'lms.quiz_published' => ['category' => 'lms', 'severity' => 'info', 'sms' => false, 'email' => false],
        'lms.material_published' => ['category' => 'lms', 'severity' => 'info', 'sms' => false, 'email' => false],
        'lms.thread_reply' => ['category' => 'lms', 'severity' => 'info', 'sms' => false, 'email' => false],

        // ── Chat (ADR-019) ─────────────────────────────────────────────────
        // Ordinary messages/mentions are in-app only (folded per conversation
        // via dedupeKey); the EMERGENCY lane is the single chat event allowed
        // to text — critical severity pierces category mutes.
        'chat.message' => ['category' => 'chat', 'severity' => 'info', 'sms' => false, 'email' => false],
        'chat.mention' => ['category' => 'chat', 'severity' => 'info', 'sms' => false, 'email' => false],
        'chat.emergency' => ['category' => 'chat', 'severity' => 'critical', 'sms' => true, 'email' => true],
        'chat.approval_pending' => ['category' => 'approvals', 'severity' => 'important', 'sms' => false, 'email' => false],
        'chat.message_decided' => ['category' => 'chat', 'severity' => 'important', 'sms' => false, 'email' => false],

        // ── Student movement (transfers / withdrawal) ──────────────────────
        'movement.transfer_requested' => ['category' => 'movement', 'severity' => 'critical', 'sms' => true, 'email' => false], // bespoke TransferUpdateMail
        'movement.transfer_approved' => ['category' => 'movement', 'severity' => 'critical', 'sms' => true, 'email' => false],
        'movement.transfer_rejected' => ['category' => 'movement', 'severity' => 'important', 'sms' => true, 'email' => false],
        'movement.transfer_cancelled' => ['category' => 'movement', 'severity' => 'important', 'sms' => true, 'email' => false],
        'movement.withdrawal' => ['category' => 'movement', 'severity' => 'critical', 'sms' => true, 'email' => false],
        'movement.application_decided' => ['category' => 'movement', 'severity' => 'important', 'sms' => true, 'email' => false],
        'movement.transfer_action_needed' => ['category' => 'approvals', 'severity' => 'important', 'sms' => false, 'email' => false],
        'movement.application_received' => ['category' => 'approvals', 'severity' => 'important', 'sms' => false, 'email' => false],

        // ── Inventory & school property (staff-only, in-app — never SMS) ──
        'inventory.requisition_submitted' => ['category' => 'approvals', 'severity' => 'important', 'sms' => false, 'email' => false],
        'inventory.requisition_decided' => ['category' => 'system', 'severity' => 'important', 'sms' => false, 'email' => false],
        'inventory.requisition_issued' => ['category' => 'system', 'severity' => 'info', 'sms' => false, 'email' => false],
        'inventory.po_submitted' => ['category' => 'approvals', 'severity' => 'important', 'sms' => false, 'email' => false],
        'inventory.po_decided' => ['category' => 'system', 'severity' => 'important', 'sms' => false, 'email' => false],
        'inventory.low_stock' => ['category' => 'system', 'severity' => 'important', 'sms' => false, 'email' => false],
        'inventory.asset_assigned' => ['category' => 'system', 'severity' => 'info', 'sms' => false, 'email' => false],
        // Family-facing textbook events — in-app only, never SMS money.
        'inventory.textbook_issued' => ['category' => 'family', 'severity' => 'info', 'sms' => false, 'email' => false],
        'inventory.textbook_lost' => ['category' => 'family', 'severity' => 'important', 'sms' => false, 'email' => false],

        // ── HR / employee self-service ─────────────────────────────────────
        'hr.leave_submitted' => ['category' => 'approvals', 'severity' => 'important', 'sms' => false, 'email' => false],
        'hr.leave_decided' => ['category' => 'hr', 'severity' => 'important', 'sms' => false, 'email' => true],
        'hr.payslip_ready' => ['category' => 'hr', 'severity' => 'important', 'sms' => false, 'email' => true],
        'hr.evaluation_shared' => ['category' => 'hr', 'severity' => 'important', 'sms' => false, 'email' => true],
        'hr.evaluation_acknowledged' => ['category' => 'hr', 'severity' => 'info', 'sms' => false, 'email' => false],

        // ── Family / accounts ──────────────────────────────────────────────
        'family.child_registered' => ['category' => 'family', 'severity' => 'important', 'sms' => true,  'email' => false], // bespoke ChildRegisteredMail
        'family.guardian_linked' => ['category' => 'family', 'severity' => 'important', 'sms' => false, 'email' => false],
        'family.account_link_decided' => ['category' => 'family', 'severity' => 'important', 'sms' => true,  'email' => false],
        'family.account_link_requested' => ['category' => 'approvals', 'severity' => 'important', 'sms' => false, 'email' => false],
        'family.card_request_decided' => ['category' => 'family', 'severity' => 'info', 'sms' => false, 'email' => false],

        // ── Tutoring marketplace ────────────────────────────────────────────
        'tutoring.application_approved' => ['category' => 'tutoring', 'severity' => 'important', 'sms' => true,  'email' => true],
        'tutoring.application_declined' => ['category' => 'tutoring', 'severity' => 'important', 'sms' => false, 'email' => true],
        'tutoring.profile_suspended' => ['category' => 'tutoring', 'severity' => 'critical', 'sms' => false, 'email' => true],
        'tutoring.request_received' => ['category' => 'tutoring', 'severity' => 'important', 'sms' => true,  'email' => false],
        'tutoring.request_accepted' => ['category' => 'tutoring', 'severity' => 'important', 'sms' => true,  'email' => false],
        'tutoring.request_declined' => ['category' => 'tutoring', 'severity' => 'info', 'sms' => false, 'email' => false],
        'tutoring.engagement_ended' => ['category' => 'tutoring', 'severity' => 'important', 'sms' => false, 'email' => false],
        // Money moments pierce quiet settings the same way school fees do.
        'tutoring.cycle_due' => ['category' => 'tutoring', 'severity' => 'critical', 'sms' => true,  'email' => true],
        'tutoring.cycle_funded' => ['category' => 'tutoring', 'severity' => 'important', 'sms' => true,  'email' => false],
        'tutoring.cycle_released' => ['category' => 'tutoring', 'severity' => 'important', 'sms' => true,  'email' => false],
        'tutoring.session_scheduled' => ['category' => 'tutoring', 'severity' => 'info', 'sms' => false, 'email' => false],
        'tutoring.session_logged' => ['category' => 'tutoring', 'severity' => 'important', 'sms' => false, 'email' => false],
        'tutoring.session_disputed' => ['category' => 'tutoring', 'severity' => 'important', 'sms' => false, 'email' => false],
        'tutoring.payout_paid' => ['category' => 'tutoring', 'severity' => 'important', 'sms' => true,  'email' => true],
        'tutoring.review_received' => ['category' => 'tutoring', 'severity' => 'info', 'sms' => false, 'email' => false],

        // ── Temari AI (in-app only — AI text never costs SMS money) ────────
        'ai.weekly_briefing' => ['category' => 'system', 'severity' => 'info', 'sms' => false, 'email' => false],
        'ai.parent_digest' => ['category' => 'academics', 'severity' => 'info', 'sms' => false, 'email' => false],

        // ── System (async job completions land where the user waits) ───────
        'system.timetable_generated' => ['category' => 'system', 'severity' => 'important', 'sms' => false, 'email' => false],
        'system.term_results_computed' => ['category' => 'system', 'severity' => 'important', 'sms' => false, 'email' => false],
        'system.student_import_completed' => ['category' => 'system', 'severity' => 'important', 'sms' => false, 'email' => false],
    ];

    public static function exists(string $event): bool
    {
        return isset(self::EVENTS[$event]);
    }

    public static function category(string $event): string
    {
        return self::EVENTS[$event]['category'];
    }

    public static function severity(string $event): string
    {
        return self::EVENTS[$event]['severity'];
    }

    public static function emailDefault(string $event): bool
    {
        return self::EVENTS[$event]['email'];
    }

    /**
     * Events allowed to send SMS by DEFAULT (before any operator edit).
     *
     * @return list<string>
     */
    public static function defaultSmsWhitelist(): array
    {
        return array_keys(array_filter(self::EVENTS, fn (array $def): bool => $def['sms']));
    }

    /**
     * The LIVE platform whitelist — the operator-edited list when one exists,
     * otherwise the defaults. Cached; PlatformSetting::set() flushes.
     *
     * @return list<string>
     */
    public static function smsWhitelist(): array
    {
        return Cache::remember(
            'notification_catalog:sms_whitelist',
            now()->addMinutes(10),
            function (): array {
                $stored = PlatformSetting::get(self::SMS_WHITELIST_KEY);

                $list = is_array($stored) ? $stored : self::defaultSmsWhitelist();

                // Unknown keys (renamed/removed events) are dropped silently.
                return array_values(array_filter($list, self::exists(...)));
            },
        );
    }

    /**
     * May this event go out via SMS? EVERY SMS send in the platform — inside
     * the Notifier or in a bespoke notifier — must pass this gate.
     */
    public static function smsAllowed(string $event): bool
    {
        return in_array($event, self::smsWhitelist(), true);
    }

    public static function flushWhitelistCache(): void
    {
        Cache::forget('notification_catalog:sms_whitelist');
    }

    /**
     * Localize value-words inside notification params: a `status` stored as
     * English ("approved") renders in the reader's language via the
     * `notifications.statuses` map. Non-scalar params are dropped — Lang
     * placeholders only take scalars.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, bool|int|float|string>
     */
    /**
     * @param  array<string, mixed>  $params
     * @param  array{calendar: string, clock: string}|null  $modes  the emitting
     *                                                              school's display modes — ISO-date params render on its calendar
     * @return array<string, scalar>
     */
    public static function localizeParams(array $params, string $locale, ?array $modes = null): array
    {
        $out = [];

        foreach ($params as $key => $value) {
            if (! is_scalar($value)) {
                continue;
            }

            if ($key === 'status' && is_string($value) && Lang::has("notifications.statuses.{$value}", $locale)) {
                $value = Lang::get("notifications.statuses.{$value}", [], $locale);
            }

            if ($key === 'decision' && is_string($value) && Lang::has("notifications.statuses.{$value}", $locale)) {
                $value = Lang::get("notifications.statuses.{$value}", [], $locale);
            }

            // Any bare ISO date param renders as a human date in the reader's
            // language on the emitting school's calendar — dispatchers pass
            // "2026-07-22", families read "Hamle 15, 2018" / "ሐምሌ 15 ቀን 2018".
            if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                $value = DateFormatter::date($value, $modes['calendar'] ?? 'ethiopian', $locale);
            }

            $out[$key] = $value;
        }

        return $out;
    }
}

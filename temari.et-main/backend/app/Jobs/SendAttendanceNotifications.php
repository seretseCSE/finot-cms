<?php

namespace App\Jobs;

use App\Mail\AttendanceAlertMail;
use App\Models\AttendanceNotificationLog;
use App\Models\AttendanceRecord;
use App\Models\StudentGuardian;
use App\Models\User;
use App\Services\Notify\Notifier;
use App\Services\Sms\SmsClient;
use App\Support\Ethiopia;
use App\Support\NotificationCatalog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Fans one batch of absent/late marks out to guardians — SMS first (the
 * Ethiopian primary channel), email in parallel when available. Fired by BOTH
 * entry lanes (manual register save, device pipeline / auto-absent sweep), so
 * every rule lives here once:
 *
 *  - policy: branch override → school default (enabled + late-too);
 *  - guardian gating: link.can_view_attendance + link.can_receive_sms +
 *    user channel prefs (notify_via_sms / notify_via_email);
 *  - same-day only: alerts about a past day are worse than none;
 *  - dedupe: the attendance_notification_logs unique key means a guardian is
 *    texted at most once per (student, day, status, channel) no matter how
 *    many times a register is re-saved or a device syncs late.
 */
class SendAttendanceNotifications implements ShouldQueue
{
    use Queueable;

    public const NOTIFIABLE_STATUSES = ['absent', 'late'];

    /**
     * @param  list<int>  $recordIds
     */
    public function __construct(public array $recordIds) {}

    public function handle(SmsClient $sms, Notifier $notifier): void
    {
        $today = Ethiopia::today();

        $records = AttendanceRecord::query()
            ->whereIn('id', $this->recordIds)
            ->whereIn('status', self::NOTIFIABLE_STATUSES)
            ->whereDate('date', $today)
            ->with([
                'student.guardians.parentProfile.user',
                'branch.school',
            ])
            ->get();

        foreach ($records as $record) {
            $branch = $record->branch;

            if ($branch === null || ! $branch->effectiveAttendanceSmsEnabled()) {
                continue;
            }

            $status = $record->status->value;

            if ($status === 'late' && ! $branch->effectiveAttendanceSmsLate()) {
                continue;
            }

            $schoolName = $branch->school?->name ?? 'your school';

            foreach ($record->student->guardians as $link) {
                $this->notifyGuardian($sms, $notifier, $record, $link, $status, $schoolName, $today);
            }
        }
    }

    private function notifyGuardian(
        SmsClient $sms,
        Notifier $notifier,
        AttendanceRecord $record,
        StudentGuardian $link,
        string $status,
        string $schoolName,
        string $today,
    ): void {
        $user = $link->parentProfile?->user;

        if ($user === null || ! $link->can_view_attendance) {
            return;
        }

        $locale = $user->preferred_language ?: 'en';
        $vars = [
            'student' => $record->student->full_name,
            'school' => $schoolName,
            'time' => $this->localTime($record->check_in, $locale),
        ];

        // In-app row — the ledger's `inapp` channel slot makes it same-day
        // once per (student, day, status) like the other channels.
        $this->deliver($record, $user, $status, 'inapp', 'feed', $today, function () use ($notifier, $user, $record, $status, $today): void {
            $notifier->inApp($user, "attendance.{$status}", [
                'student' => $record->student->full_name,
                'date' => $today,
            ], [
                'link' => '/me/attendance',
                'schoolId' => $record->school_id,
                'branchId' => $record->branch_id,
            ]);
        });

        // SMS is metered: absence/late texts obey the platform whitelist.
        if ($user->notify_via_sms && $link->can_receive_sms && $user->phone
            && NotificationCatalog::smsAllowed("attendance.{$status}")) {
            $this->deliver($record, $user, $status, 'sms', $user->phone, $today, function () use ($sms, $user, $status, $vars, $locale): void {
                $sms->send($user->phone, Lang::get("attendance_alerts.{$status}_sms", $vars, $locale));
            });
        }

        if ($user->notify_via_email && $user->email) {
            $this->deliver($record, $user, $status, 'email', $user->email, $today, function () use ($user, $record, $status, $vars, $locale): void {
                Mail::to($user->email)->send(new AttendanceAlertMail(
                    studentName: $record->student->full_name,
                    schoolName: $vars['school'],
                    status: $status,
                    time: $vars['time'],
                    language: $locale,
                ));
            });
        }
    }

    /** Claim the dedupe slot first; only a fresh claim actually sends. */
    private function deliver(
        AttendanceRecord $record,
        User $guardian,
        string $status,
        string $channel,
        string $recipient,
        string $date,
        callable $send,
    ): void {
        $log = AttendanceNotificationLog::firstOrCreate(
            [
                'student_id' => $record->student_id,
                'date' => $date,
                'status' => $status,
                'guardian_user_id' => $guardian->id,
                'channel' => $channel,
            ],
            [
                'school_id' => $record->school_id,
                'branch_id' => $record->branch_id,
                'recipient' => $recipient,
                'result' => 'sent',
            ],
        );

        if (! $log->wasRecentlyCreated) {
            return;
        }

        try {
            $send();
        } catch (\Throwable $e) {
            $log->update(['result' => 'failed']);
            Log::warning('Attendance alert failed.', [
                'student_id' => $record->student_id,
                'guardian_user_id' => $guardian->id,
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** "8:05 AM" with the locale's own AM/PM labels; null when no check-in. */
    private function localTime(?string $time, string $locale): ?string
    {
        if (! $time || ! preg_match('/^(\d{2}):(\d{2})/', $time, $m)) {
            return null;
        }

        $hour = (int) $m[1];
        $label = Lang::get($hour < 12 ? 'attendance_alerts.time_am' : 'attendance_alerts.time_pm', [], $locale);
        $hour12 = $hour % 12 === 0 ? 12 : $hour % 12;

        return "{$hour12}:{$m[2]} {$label}";
    }
}

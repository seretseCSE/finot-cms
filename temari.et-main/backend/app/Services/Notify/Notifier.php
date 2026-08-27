<?php

namespace App\Services\Notify;

use App\Jobs\FanOutNotificationJob;
use App\Mail\NotificationMail;
use App\Models\Notification;
use App\Models\Student;
use App\Models\User;
use App\Services\Sms\SmsClient;
use App\Support\Authorization\PermissionCatalog;
use App\Support\CommsMute;
use App\Support\DateFormatter;
use App\Support\NotificationCatalog;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * THE single dispatch point for user-facing notifications (ADR-018). Every
 * event flows through here: an in-app feed row is ALWAYS written, then the
 * SMS leg (platform whitelist × master switch × category pref × per-link
 * consent) and the email leg (catalog default or bespoke mailable × prefs)
 * fan out per recipient, each localized to the recipient's language.
 *
 * Guarantees:
 *  - deferred with DB::afterCommit — a rolled-back mutation notifies no one;
 *  - never throws — comms must not undo the domain write (failures logged);
 *  - unknown event keys are a programmer error and throw EARLY (before the
 *    commit hook) so tests catch unregistered events;
 *  - audiences above FANOUT_INLINE_LIMIT are chunked through the queue.
 *
 * Options (all optional):
 *  - link:       frontend route the feed row deep-links to
 *  - schoolId / branchId: emitting scope (deep-link context)
 *  - dedupeKey:  fold repeat events into one unread row (count bumps)
 *  - exceptUserId: leave out the actor (nobody needs "you did X")
 *  - smsVars:    extra :placeholders for the SMS line beyond `data`
 *  - smsKey:     lang key for the SMS body — defaults to
 *                notifications.<event>.sms; no line ⇒ no SMS
 *  - smsAllowed: fn(User): bool per-recipient consent gate (e.g. the guardian
 *                link's can_receive_sms)
 *  - mail:       fn(User, string $locale): Mailable bespoke email (catalog
 *                events with `email: false` may still send one — that flag
 *                only disables the GENERIC mail)
 */
class Notifier
{
    /** Above this many recipients, delivery moves to queued chunks. */
    public const FANOUT_INLINE_LIMIT = 50;

    public function __construct(private readonly SmsClient $sms) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $options
     */
    public function toUser(?User $user, string $event, array $data = [], array $options = []): void
    {
        if ($user === null) {
            return;
        }

        $this->toUsers([$user], $event, $data, $options);
    }

    /**
     * @param  iterable<int, User>  $users
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $options
     */
    public function toUsers(iterable $users, string $event, array $data = [], array $options = []): void
    {
        self::assertKnown($event);

        $unique = [];
        foreach ($users as $user) {
            if ($user !== null && ! isset($unique[$user->id]) && $user->id !== ($options['exceptUserId'] ?? null)) {
                $unique[$user->id] = $user;
            }
        }

        if ($unique === []) {
            return;
        }

        DB::afterCommit(function () use ($unique, $event, $data, $options): void {
            if (count($unique) > self::FANOUT_INLINE_LIMIT) {
                // Closures don't queue — big audiences get generic delivery,
                // which is what broadcast-ish events (timetable, assignments)
                // use anyway.
                FanOutNotificationJob::dispatch(
                    array_keys($unique),
                    $event,
                    $data,
                    self::queueableOptions($options),
                );

                return;
            }

            foreach ($unique as $user) {
                $this->deliver($user, $event, $data, $options);
            }
        });
    }

    /**
     * The in-app feed row ONLY — for notifiers that own their SMS/email legs
     * (ledger-driven flows like the fee-reminder ladder and attendance
     * alerts, which dedupe sends in their own tables). Those flows must still
     * gate their SMS on NotificationCatalog::smsAllowed().
     *
     * @param  User|iterable<int, User>  $users
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $options
     */
    public function inApp(User|iterable $users, string $event, array $data = [], array $options = []): void
    {
        self::assertKnown($event);

        $users = $users instanceof User ? [$users] : $users;
        $category = NotificationCatalog::category($event);

        DB::afterCommit(function () use ($users, $event, $category, $data, $options): void {
            $seen = [];

            foreach ($users as $user) {
                if ($user === null || isset($seen[$user->id])) {
                    continue;
                }
                $seen[$user->id] = true;

                try {
                    $this->insertFeedRow($user, $event, $category, $data, $options);
                } catch (\Throwable $e) {
                    Log::warning('In-app notification insert failed.', [
                        'user_id' => $user->id, 'event' => $event, 'error' => $e->getMessage(),
                    ]);
                }
            }
        });
    }

    /**
     * The student's own account + every ACTIVE guardian account, each behind
     * their own switches; the guardian link's can_receive_sms gates the SMS
     * leg per link. This is the relationship-lane audience — use it for every
     * family-facing event.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $options
     */
    public function toFamily(?Student $student, string $event, array $data = [], array $options = []): void
    {
        if ($student === null) {
            return;
        }

        self::assertKnown($event);

        $student->loadMissing(['user', 'guardians.parentProfile.user']);

        $users = [];
        $smsConsent = [];

        if ($student->user !== null) {
            $users[] = $student->user;
            $smsConsent[$student->user->id] = true;
        }

        foreach ($student->guardians as $link) {
            $user = $link->is_active ? $link->parentProfile?->user : null;
            if ($user !== null) {
                $users[] = $user;
                // A user guarding two children keeps SMS if EITHER link consents.
                $smsConsent[$user->id] = ($smsConsent[$user->id] ?? false) || (bool) $link->can_receive_sms;
            }
        }

        $data['student'] = $student->full_name;

        $this->toUsers($users, $event, $data, [
            ...$options,
            'smsAllowed' => fn (User $u): bool => $smsConsent[$u->id] ?? false,
        ]);
    }

    /**
     * Staff at a scope holding a permission — the approval-queue audience
     * ("a marklist awaits you", "an expense needs a decision"). Resolved from
     * the membership kernel: branch memberships of that exact branch +
     * school-level memberships of the school, roles filtered to those whose
     * catalog grants the permission. Platform staff are deliberately NOT
     * included — they'd drown in every school's operational noise.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $options
     */
    public function toStaff(int $schoolId, ?int $branchId, string $permission, string $event, array $data = [], array $options = []): void
    {
        self::assertKnown($event);

        $roles = self::rolesWithPermission($permission);

        if ($roles === []) {
            return;
        }

        $users = User::query()
            ->whereHas('memberships', function ($q) use ($schoolId, $branchId, $roles): void {
                $q->where('is_active', true)
                    ->whereIn('role', $roles)
                    ->where('school_id', $schoolId)
                    ->where(function ($scope) use ($branchId): void {
                        $scope->whereNull('branch_id');
                        if ($branchId !== null) {
                            $scope->orWhere('branch_id', $branchId);
                        }
                    });
            })
            ->where('status', 'active')
            ->get();

        $this->toUsers($users, $event, $data, [
            'schoolId' => $schoolId,
            'branchId' => $branchId,
            ...$options,
        ]);
    }

    /**
     * One recipient, all three legs. Called inline or from the fan-out job.
     * Public for the job; never call it from feature code — go through
     * toUser/toUsers/toFamily/toStaff so afterCommit + dedupe apply.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $options
     */
    public function deliver(User $user, string $event, array $data, array $options = []): void
    {
        $category = NotificationCatalog::category($event);
        $critical = NotificationCatalog::severity($event) === NotificationCatalog::SEVERITY_CRITICAL;
        $locale = $user->preferred_language ?: 'en';
        // ISO-date params render on the emitting school's calendar (cached).
        $modes = DateFormatter::modesFor($options['schoolId'] ?? null, $options['branchId'] ?? null);

        // 1. The in-app row — always. The feed is the system of record.
        try {
            $this->insertFeedRow($user, $event, $category, $data, $options);
        } catch (\Throwable $e) {
            Log::warning('In-app notification insert failed.', [
                'user_id' => $user->id, 'event' => $event, 'error' => $e->getMessage(),
            ]);
        }

        // 2. SMS — the metered channel. Platform whitelist first: which
        // events may text at all is a Temari.et operator decision. Bulk
        // operations (student import) may mute both outbound legs — the
        // in-app row above is still written.
        try {
            $smsKey = $options['smsKey'] ?? "notifications.{$event}.sms";
            $consent = $options['smsAllowed'] ?? null;

            if (! CommsMute::active()
                && $user->phone !== null
                && NotificationCatalog::smsAllowed($event)
                && $user->notificationChannelEnabled('sms', $category, $critical)
                && ($consent === null || $consent($user))
                && Lang::has($smsKey, $locale)) {
                $vars = NotificationCatalog::localizeParams([...$data, ...($options['smsVars'] ?? [])], $locale, $modes);
                $this->sms->send($user->phone, Lang::get($smsKey, $vars, $locale));
            }
        } catch (\Throwable $e) {
            Log::warning('Notification SMS failed.', [
                'user_id' => $user->id, 'event' => $event, 'error' => $e->getMessage(),
            ]);
        }

        // 3. Email — bespoke mailable when given, else the generic mail when
        // the catalog says so and copy exists.
        try {
            if (! CommsMute::active() && $user->email !== null && $user->notificationChannelEnabled('email', $category, $critical)) {
                $mailFactory = $options['mail'] ?? null;

                if ($mailFactory instanceof Closure) {
                    Mail::to($user->email)->send($mailFactory($user, $locale));
                } elseif (NotificationCatalog::emailDefault($event) && Lang::has("notifications.{$event}.title", $locale)) {
                    $vars = NotificationCatalog::localizeParams($data, $locale, $modes);
                    Mail::to($user->email)->send(new NotificationMail(
                        title: Lang::get("notifications.{$event}.title", $vars, $locale),
                        body: Lang::get("notifications.{$event}.body", $vars, $locale),
                    ));
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Notification email failed.', [
                'user_id' => $user->id, 'event' => $event, 'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Insert the feed row; with a dedupe key, FOLD into the existing unread
     * sibling instead of stacking ("4 new submissions · Essay 2"): the old
     * row is replaced so the folded one returns to the top of the feed.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $options
     */
    private function insertFeedRow(User $user, string $event, string $category, array $data, array $options): void
    {
        $dedupeKey = $options['dedupeKey'] ?? null;

        if ($dedupeKey !== null) {
            // The key is UNIQUE per user, read or not — always resolve the
            // holder. An unread sibling folds (replaced, count bumped); a
            // READ sibling is old news the user acknowledged: release its key
            // (the row stays as plain history) so the fresh row lands as a
            // brand-new unread notification instead of a dropped insert.
            $existing = Notification::query()
                ->where('user_id', $user->id)
                ->where('dedupe_key', $dedupeKey)
                ->first();

            if ($existing !== null && $existing->read_at === null) {
                $data['count'] = ($existing->data['count'] ?? 1) + 1;
                $existing->delete();
            } else {
                if ($existing !== null) {
                    $existing->update(['dedupe_key' => null]);
                }
                $data['count'] = $data['count'] ?? 1;
            }
        }

        Notification::create([
            'user_id' => $user->id,
            'event' => $event,
            'category' => $category,
            'school_id' => $options['schoolId'] ?? null,
            'branch_id' => $options['branchId'] ?? null,
            'data' => $data,
            'link' => $options['link'] ?? null,
            'dedupe_key' => $dedupeKey,
        ]);
    }

    private static function assertKnown(string $event): void
    {
        if (! NotificationCatalog::exists($event)) {
            throw new \InvalidArgumentException(
                "Notification event `{$event}` is not registered in NotificationCatalog.",
            );
        }
    }

    /**
     * Role names whose permission catalog includes $permission — cached per
     * request via PermissionCatalog's own memo.
     *
     * @return list<string>
     */
    private static function rolesWithPermission(string $permission): array
    {
        $roles = [];

        foreach (PermissionCatalog::map() as $role => $permissions) {
            if (in_array($permission, $permissions, true)) {
                $roles[] = $role;
            }
        }

        return $roles;
    }

    /**
     * The subset of options that survives a queue round-trip (no closures).
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private static function queueableOptions(array $options): array
    {
        return array_intersect_key($options, array_flip(['link', 'schoolId', 'branchId', 'smsKey', 'smsVars', 'dedupeKey']));
    }
}

# ADR-018 — The Notification Pipeline: One Dispatch Point for In-App, SMS and Email

Status: **accepted** (July 2026, approved by Abdul)

## Problem

Comms grew channel-first and ad-hoc: five hand-rolled notifier services (registration,
fees, reminders, transfers, receipts) plus an attendance job, each re-implementing the
same recipient loop, locale resolution, preference checks and try/catch. There was no
in-app feed at all — a user learned about an approval, an invoice or a graded assignment
only if a paid SMS or an email happened to fire. Nothing controlled WHICH events were
allowed to spend SMS money, and per-user preferences were three coarse booleans.

## Decision

### 1. A typed event catalog is the single source of truth

`App\Support\NotificationCatalog::EVENTS` declares every event the platform can emit:
`category` (security / finance / attendance / academics / lms / movement / approvals /
hr / family / system), `severity` (critical / important / info), default SMS
whitelisting, and default email behavior. **The Notifier throws on unknown keys** — an
unregistered event is a programmer error caught by tests, never a silent no-op.
i18n copy lives in `lang/{en,am,om}/notifications.php` under the event key
(`title` / `body` / optional `sms`); `statuses.*` localizes value-words substituted into
`:status` placeholders (`NotificationCatalog::localizeParams`).

### 2. One dispatch point — `App\Services\Notify\Notifier`

- `toUser / toUsers` — direct recipients.
- `toFamily(Student…)` — the relationship-lane audience: the student's own account +
  every ACTIVE guardian, deduped per user, the guardian link's `can_receive_sms`
  gating the SMS leg per link.
- `toStaff(schoolId, branchId, permission, …)` — the approval-queue audience, resolved
  from the membership kernel (branch memberships of that exact branch + school-level
  memberships; roles filtered by whose catalog grants the permission; platform staff
  deliberately excluded). Tenant isolation therefore holds by construction.
- `inApp(…)` — feed row only, for ledger-driven flows that own their SMS/email legs
  (fee-reminder ladder, attendance alerts) and dedupe sends in their own tables.

Guarantees: deferred via `DB::afterCommit` (a rolled-back mutation notifies nobody);
never throws past the domain write (per-leg try/catch + log); audiences above 50 go
through `FanOutNotificationJob` in queued 200-row chunks.

### 3. The in-app feed is the system of record

`notifications` table: `user_id`, `event`, `category`, nullable `school_id`/`branch_id`
(deep-link context), `data` JSONB (**params only, never rendered text** — title/body
render at READ time in the reader's `preferred_language`), `link`, `dedupe_key`,
`read_at`. A row is ALWAYS written, whatever the channel prefs — SMS and email are
summaries of the feed, not the other way round. Dedupe folding: an unread row with the
same `(user, dedupe_key)` is replaced and its `data.count` bumped ("4 new submissions"),
so noisy events never stack. No soft deletes — `notifications:prune` (daily, 03:00
Addis) drops read rows after 90 days, unread after 180.

Feed API (self-scoped, every account type): `GET /me/notifications` (paginate, `filter=unread`,
`category=`), `GET /me/notifications/unread-count` (60s client poll), `POST …/{id}/read`,
`POST …/read-all`.

### 4. SMS is metered — the platform whitelist decides who may text

Every SMS send anywhere must pass `NotificationCatalog::smsAllowed($event)`: the live
whitelist is the `notifications.sms_whitelist` platform setting (`platform_settings`
key-value table, cached), edited only by Temari.et staff (`catalogs.manage`) at
`/catalogs/notification-events` (GET/PUT `/api/v1/catalogs/notification-events`).
Catalog defaults whitelist the critical family-facing events (absence, invoices due,
transfers, receipts, security); in-app + email behavior is code-defined and has no knob.

### 5. Preferences: masters × category mutes × severity

`users.notify_via_sms/email/push` stay the master switches. `users.notification_preferences`
(JSONB deltas, validated against catalog categories) mutes a CATEGORY per channel.
Resolution in `User::notificationChannelEnabled(channel, category, critical)`:
master off ⇒ never; `critical` severity pierces the category mute (a parent must not
silently lose "your child is absent" or "new device signed in") but still respects the
masters. The in-app feed ignores all of this — always on.

### 6. Security events needed a device ledger

`user_devices` (user × user-agent-hash fingerprint, coarse by design) +
`App\Services\Notify\DeviceTracker`: first-seen fingerprint ⇒ `security.new_device`
(silent for the very first device — the sign-up itself). Password resets fire
`security.password_changed`. Tracked on login, reset-password and set-password.

## Wired events (July 2026)

Family: invoice issued (skips scholarship-covered), fee reminders/notices, payment
received/verified/rejected, concession granted (student- or guardian-scoped), absence/
late, report cards on term close, enrollment activated, transfers (all states, bespoke
TransferUpdateMail preserved), withdrawal, child registered, account-link decisions,
card-request status. Staff: payment awaiting verification, concession suggestion,
expense four-eyes (submit → approvers, decide → recorder), leave (submit → `leave.manage`,
decide → employee), marklist (submit → `grades.approve`, approve/reopen → teacher),
payslips on payroll approval, incoming transfer requests/applications, student-ID claims
(→ `students.manage`), timetable-solver completion (→ requester), term results computed.
Students: assignment/quiz/material published (first publish only, queued fan-out),
assignment graded, thread replies (both directions, folded per thread), timetable
published (teachers with term workloads + active students).

## The new-feature rule (MANDATORY)

Any feature that creates something a user should react to — an approval to decide,
money to pay, work to review, a status change on something they own — MUST:

1. register its event in `NotificationCatalog::EVENTS` (category, severity, channel
   defaults);
2. add `title`/`body` (+ `sms` if whitelist-worthy) to `lang/{en,am,om}/notifications.php`;
3. dispatch through the Notifier (`toUser` / `toFamily` / `toStaff` / `inApp`) with a
   `link` deep link — never hand-roll SMS or email again;
4. digest-fold repeatable events with a `dedupeKey`;
5. seed sample rows in `DemoSeeder::buildNotifications()` when the event suits a demo
   persona.

Guard rail: `tests/Feature/NotificationPipelineTest.php`.

## Rejected alternatives

- **Laravel's stock notification system / UUID notifications table** — bigint PKs are a
  project rule; the stock morphs and per-class channel routing hide the whitelist and
  category logic this platform needs in one auditable place.
- **Websockets (Reverb) for delivery** — a 60s count poll + refresh-on-focus is
  indistinguishable for school workflows, free on 3G, and adds zero infrastructure on
  Coolify. Revisit only if a genuinely real-time surface (live invigilation) demands it.
- **Per-event user preferences** — 40+ toggles per user is unusable; categories map to
  how parents and staff actually think. Critical severity covers the "never miss" set.
- **Storing rendered text per row** — would freeze each row in the language the sender
  happened to think in; params + read-time rendering follow the reader.

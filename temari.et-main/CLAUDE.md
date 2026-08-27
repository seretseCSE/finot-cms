# Temari.et — AI Agent Instructions

> This file is the **primary source of truth** for any AI agent (Claude Code or otherwise) working on this project.
> Read this before touching any code. Update it when major architectural decisions are made.

---

## 0. What This Project Is

**Temari.et** is a unified Ethiopian education platform replacing School-X (school-x.et)
- Owner: Abdul, Empire Technological Solutions PLC, Addis Ababa
- Domain: temari.et (school-x.et is deprecated — never reference it in code)
- Full knowledge base: `knowledge/temari.et-school-x.et-knowledge.md`

**This is a large-scale, multi-tenant SaaS** serving schools, teachers, students, parents, tutors, and vendors across Ethiopia. Every decision must account for scale, performance, and privacy.

### On School-X / Old Architecture
The knowledge base documents School-X's design, mistakes, and business logic. Use it **only as reference and context** — never copy its architecture, schema, or code patterns directly. Always think critically: *is there a better way to solve this problem from first principles?* The knowledge base is a map of what existed and what failed — not a blueprint to follow. Temari must be independently designed and superior in every dimension.

---

## 1. Tech Stack

| Layer | Tech |
|-------|------|
| Backend | Laravel 13, PHP 8.4, PostgreSQL |
| Frontend | Next.js (monorepo), Tailwind CSS |
| Auth | Laravel Sanctum (personal access tokens) |
| Permissions | Spatie Laravel Permission (RBAC) |
| Queue | Laravel Horizon / Jobs |
| File storage | Cloudflare R2 (S3-compatible) |
| Backend hosting | Coolify (self-hosted VPS) |
| Frontend hosting | Cloudflare Workers |
| Payments | Telebirr, CBE Birr, Stripe |
| AI | Laravel AI SDK (`laravel/ai`) — model ids ONLY in `config/temari-ai.php` (`model` = cheap text-only chat, `attachment_model` = prompts carrying files (routing via `ChatAttachments::modelFor()`), `light_model` = titles/digests/bulk work) |
| SMS | Primary parent notification channel (provider TBD) |
| Analytics | PostHog (product analytics + error tracking, both apps; env-keyed, off without `POSTHOG_KEY` / `NEXT_PUBLIC_POSTHOG_KEY`) |
| Offline | PWA + SQLite + background sync API |

**Backend lives in `./backend/`. Frontend lives in `./frontend/`.**

---

## 2. Non-Negotiable Quality Rules

### 2.0 How these rules are enforced (read this before trusting the prose)

Most of what follows is **machine-checked**. Where a rule below has an enforcement
column entry, the document is a summary of the check, not the check itself — the
tool is the source of truth, and CI fails on it.

| Run | What it enforces |
|---|---|
| `cd backend && composer test` | 789 Pest tests, ~60s. Every ADR guard rail (`CrossTenantIsolationTest`, `PostTransferAccessTest`, `FinanceControlsTest`, `NotificationPipelineTest`, …). |
| `cd backend && composer test:edge` | Same suite with the clock pinned to 22:30 UTC — inside the daily window where the UTC date is still the Addis day before. Catches any test that builds a school-day date with `now()` instead of `Ethiopia::today()`. See `backend/TESTING.md`. |
| `cd backend && ./vendor/bin/pint --dirty --format agent` | PHP formatting. |
| `cd frontend && pnpm lint` | The statically checkable HARD rules — see below. |
| `cd frontend && pnpm test` | Vitest: Ethiopian calendar maths, the phone standard, the date/clock display layer, backend↔frontend enum parity, and i18n key resolution. |
| `cd frontend && pnpm typecheck` | `tsc --noEmit`. |

**ESLint enforces these rules directly** (`frontend/eslint.config.mjs` +
`frontend/eslint-rules.mjs`) — every one of them had a real violation in the
codebase before the rule existed:

- Dates/times only through `lib/dates.ts` — bans `toLocaleDateString`,
  `toLocaleTimeString`, `Intl.DateTimeFormat` and `date-fns` outside the date layer.
- No native `<input type="date|time|datetime-local">` — use the shared DatePicker/TimePicker.
- No `window.print` — official paper renders server-side as a PDF.
- Every DELETE goes through `useConfirmDelete()`.
- No `last_name` (names are patronymic); no `school-x.et` reference.

A legitimate exception is an `eslint-disable-next-line` **with the reason written
above it** — put the explanation first and the directive on the line immediately
before the code, or it silently does not apply.

`frontend/lib/enum-parity.test.ts` compares every `App\Enums\*` against its
`lib/types.ts` union and fails on drift. Enums with no client union are listed
there with a reason — add to that list deliberately, never to silence a failure.

`frontend/lib/i18n-keys.test.ts` catches the silent i18n failure — `t()` renders
the KEY when copy is missing, so "messages.saved" ships as UI text. It resolves
every literal `t("…")` call against its domain dictionary, holds en/am/om at
identical key coverage, and pins `notifications.categories` to
`NotificationCatalog::CATEGORIES`. Interpolated keys (`t(\`x.${status}\`)`) are
NOT covered — when you add a case to an enum, add the label in all 3 languages.

**Rules NOT machine-checked** (§6 in-app docs, translation QUALITY, demo seed
rows, notification wiring, PostHog events) still depend on you. Those are the
ones to re-read.

### 2.1 Bug-free first
- Every feature must be functionally correct before it is considered done.
- After every change: verify routes, permissions, edge cases. Run tests.
- Never leave TODO stubs in production code paths.
- If something can break in the Ethiopian context (low bandwidth, Amharic text, Ethiopian calendar), test for it.

### 2.2 Performance is a feature
- This platform serves many concurrent users. Assume 10,000+ students per school group.
- Every DB query must be indexed. No N+1 queries — use `with()` / `load()` consistently.
- Paginate all list endpoints (default 25, max 100).
- Cache aggressively (Redis / Laravel Cache) for school settings, grade levels, subjects.
- Frontend: lazy-load heavy components. Use Next.js `dynamic()` for dashboards.
- Optimize all images. Serve from Cloudflare R2 with CDN caching.
- Target: API responses < 300ms for common endpoints on 3G.

### 2.3 Mobile-first, offline-capable
- All UI must work and look great on mobile (Android dominant). Mobile = primary device.
- The app must feel like a **native mobile app** on mobile screens.
- Key offline features (attendance, marks entry) must work without internet via PWA/SQLite.
- SMS is the primary channel for parent notifications — never assume push notification delivery.

### 2.4 Multi-language (i18n)
- **Languages in v1:** English (`en`), Amharic (`am`), Afan Oromo (`om`)
- All user-facing **UI strings** must use the i18n system — never hardcode UI text. Translation files live in `lib/i18n/`.
- i18n applies to **interface text only** (labels, messages, buttons, headings). Database content is NOT translated at the schema level.
- User preference stored in `users.preferred_language`.
- Default language fallback: `en`.

### 2.5 Security
- Never expose other schools' data. **The BRANCH is the tenant boundary** (ADR-001): every operational query is scoped by `branch_id` (plus `school_id`), and every permission check goes through the authorization kernel (ADR-010) — `hasPermissionForScope()` / `hasContextPermission()` on `User`. Never call a global permission check (`can('x')`, `hasPermissionTo`) for tenant data.
- Closed terms are read-only — enforced centrally by `App\Support\TermGate` (call it in every mutation of term-anchored data).
- A teacher at School A must have zero visibility into School B's data — guaranteed by the kernel and asserted end-to-end in `tests/Feature/CrossTenantIsolationTest.php`. Every new endpoint must keep that suite green.
- Parents/students access their own data ONLY through the `/api/v1/me/*` relationship lane (ADR-012), never through staff endpoints.
- Fayda IDs stored hashed only.
- All media access through signed Cloudflare R2 URLs, never direct public links for private files.

---

## 3. Database Principles

- **PostgreSQL only** — leverage JSONB, arrays, full-text search, UUID where appropriate.
- **Auto-increment bigint PKs** — no UUIDs as PKs (performance).
- **Multi-tenant via `branch_id` (+ `school_id`) FKs** — single DB; the branch is the tenant boundary (ADR-001), the school is an identity holder above it. Persons (users, students) are GLOBAL — tenancy lives on engagement rows (memberships, enrollments, employees), never on the person (ADR-011).
- **Soft deletes everywhere** — `deleted_at` on all core tables. **Unique indexes on soft-deleting tables must be PARTIAL (`WHERE deleted_at IS NULL`)** so a trashed row never blocks recreating the same name/code (raw `DB::statement` in the create migration — the fluent `unique()` can't express it; note ON CONFLICT/upsert can't target partial indexes, loop `updateOrCreate` instead). Recreating a section resurrects the trashed row (same id → history survives).
- **`term_id` is the universal time anchor** — never mix `semester_id`, `period_id`, `academic_year_id` arbitrarily.
- **No school-scoped grade levels** — `grade_levels` is platform seed data (seeded once).
- **Subjects are platform seed data** (Ethiopian curriculum, stable codes, `category` + an EXPLICIT grade set in the `grade_level_subject` pivot — never a from/to range; empty set = every grade); schools may add custom rows (`school_id` set). The grade set drives semester-grid generation (`Subject::appliesToGradeSort()` / `scopeForGradeSorts()`).
- **Homerooms are YEAR-scoped** (`section_homerooms`), never a column on `sections`.
- **Bank accounts are SCHOOL-owned** (`bank_accounts` + `bank_account_branch` pivot with per-branch `is_active`); the `banks` catalog (banks + wallets) is platform seed data. Fees attach 0..n collection accounts via `fee_structure_bank_account`. Payments SNAPSHOT their collection account — never rewrite history when a fee is re-pointed. `payments.bank_account_id` is REQUIRED for bank/wallet methods whenever the branch has a usable account (cash/other never take one); accounts with payments are deactivated, never deleted.
- **Fee concessions are the POLICY layer above per-invoice discounts** (`fee_concessions`): a standing discount/scholarship for a STUDENT or a GUARDIAN (covers all linked children), scoped to fee types / one year / one semester or lifetime. Resolved at invoice GENERATION time only (`App\Services\FeeConcessionResolver` — best single concession wins, NEVER stacked) and stamped onto the invoice's discount fields + `fee_concession_id`; revoking stops future bills only. School policy (sibling / employee-child percents in `schools.settings`, branch-overridable via `Branch::effectiveSiblingDiscountPercent()` etc. like every other branch setting) files PENDING suggestions via `App\Services\ConcessionSuggestionService` — finance approves each row, no silent discounts. Guard rail: `tests/Feature/FeeConcessionTest.php`.
- **Payroll runs freeze on approval** — `payroll_items.breakdown` snapshots the source lines; tax per `App\Support\EthiopianIncomeTax` (Proclamation 1395/2025), pension 7%/11%.
- **Finance controls are SCHOOL-scope settings a director can never edit** (`schools.settings`, changed only via `SchoolController@updateSettings` → managesSchool): `finance_self_approval` (default OFF — the expense four-eyes rule in `ExpenseController@decide`) and `director_finance_access` (default OFF — in Ethiopia the director is the academic head; money belongs to the finance officer + principal). The kernel (`User::permissionsForScope` × `User::DIRECTOR_FINANCE_GATED` × `App\Support\FinanceControls`) strips fees.manage / payments.record / finance.books.* from the director role unless the school flips the flag; the frontend mirrors it in `useEffectivePermissions` via `memberships[].director_finance_access`. Budget-vs-actual uses a GAPLESS year window (starts_on → day before the next year's starts_on) so kremt spending never falls between years, and surfaces pending-approval sums separately. Guard rail: `tests/Feature/FinanceControlsTest.php`.
- **Official PDFs render server-side through Cloudflare Browser Rendering** (`App\Services\Pdf\PdfRenderer` — REST `/pdf`, never headless Chrome on our VPS; needs `CLOUDFLARE_ACCOUNT_ID`/`CLOUDFLARE_API_TOKEN`). One pipeline (`App\Services\Documents\DocumentService` + type classes, `generated_documents` table): documents are PRE-WARMED at their source event (a recorded payment pre-warms its receipt + SMS/emails the family the link) and cached on R2 by content hash — POST `/documents` re-serves unchanged PDFs instantly, only stale ones re-render (client polls with a "generating…" state, `useDocumentDownload`). Every PDF carries a QR to the public `/verify/{token}` lane (`GET public/documents/{token}`) that proves authenticity WITHOUT exposing marks or pay; revoking kills the QR, never the history. Types: payment_receipt, transfer_letter, withdrawal_letter, transcript, report_card, finance_statement, payslip. Guard rail: `tests/Feature/DocumentPipelineTest.php`.
- **Leave types are SCHOOL-owned policy** (`leave_types`, auto-provisioned per school from `App\Support\LeavePolicy` — Labour Proclamation 1156/2019 defaults); leave requests + employee attendance (`leave_requests`, `employee_attendance_records`) are branch-scoped. Approved leave and `holidays` are **read-time OVERLAYS** on the employee register — never materialised as attendance rows. The leave year is the Ethiopian year (Meskerem 1); leave days are server-computed working days (weekends + holidays excluded).
- **Bulk student import is browser-parsed, silent by default** — the .xlsx never uploads (SheetJS in the studio at `/students/import`); canonical JSON rows land in `student_imports`/`student_import_rows`, validate server-side (`App\Services\Imports\StudentImportRowValidator` — duplicates skip by default with per-row override), and `ImportStudentsJob` executes each row through `RegisterStudentAction` in its OWN transaction. Unless the operator flips the commit toggle, the whole run executes inside `App\Support\CommsMute` — **no SMS/email ever leaves a bulk import by default** (in-app feed still writes). Guard rail: `tests/Feature/StudentImportTest.php`.
- **Enrollments gate on the registration fee** — new enrollments are born `pending` when an applicable `type=registration` fee is unsettled (auto-invoiced by `EnrollStudentAction`); payment/scholarship auto-activates via `App\Services\EnrollmentGate`. School policy `soft` (default: staff may provisionally activate) vs `hard` lives in `schools.settings`. Pending rows hold seats/capacity but appear on NO class list.
- **Marklist drafts belong to the TEACHER (the trust rule)** — while a draft has an owning teacher account, only that teacher writes score cells (`AssessmentPolicy::enterMarks`); supervisors are read-only until they declare on-behalf entry (`POST marklists/{id}/assist`, reason required, teacher notified, entries badged via per-cell `recorded_by` — which `upsertResults` stamps on CHANGED rows only). Teacher-less assignments (vacancy) take direct supervisor entry. Four-eyes on approval: whoever typed cells, declared assistance, or submitted the sheet cannot countersign it. Guard rail: `tests/Feature/MarklistTrustTest.php`.
- **Semester report cards freeze into `student_term_results`** (average + section rank + JSONB per-subject breakdown) when a term closes (`ComputeTermResultsJob`); annual averages and promotion suggestions read THESE rows, never raw assessment_results.
- **Year-end is decide-then-execute** — the promotion board saves decisions into `student_promotions` (one row per source enrollment); `RolloverPromotionsAction` executes them PER STUDENT in separate transactions (re-runnable, partial-safe), creating next-year enrollments through the normal gate. Transfers between Temari schools run `student_transfer_requests` (receiving branch requests, SENDING branch approves = fee clearance) and land in the same audit table.
- **Custody is enrollment-bounded (ADR-017)** — live writes on a student/parent (profile, documents, health, guardians, enrollments) require a scope in `Student::activeAdminScopes()` (pending/active enrollment → else latest enrollment's branch → else registration branch). Every former scope gets a read-only ERA archive served from the **handover snapshot** (`student_transfer_requests.handover_snapshot`, frozen by `App\Services\StudentHandoverSnapshot` at approval): the file as the student LEFT — era documents, address, health, guardians — never anything the new school adds (receiving school's live enrollment masked, `access: "archive"` + `archive` payload). Documents travel FORWARD with the student (a returning student restores full live access automatically); attachments carry provenance (`school_id`/`branch_id`/`uploaded_by`); transfer supporting files surface as `transfer_files` for the two PARTICIPANT schools of each request only. Guard rail: `tests/Feature/PostTransferAccessTest.php`.
- **Lesson planning is the MoE format, timetable-driven, with a pacing gate** — `annual_lesson_plans` (one per teacher × subject × grade × year; goals/methods + `periods_per_week`/`total_periods` + the `annual_plan_units` MoE grid: objectives, rationale, prerequisite knowledge, aids, assessment, `page_from/to`, date window — Ethiopian months on the printed sheet DERIVE from the dates, never typed) → `weekly_lesson_plans` (Monday-anchored CONTAINERS: submission/approval/pacing only, auto-created when a day is planned) → `daily_lesson_plans` (the MoE daily format: topic/subtopic, rationale, prerequisites, objectives, slow/medium/fast learner supports; the three stages in `daily_plan_stages` — intro|main|conclusion × teacher/student activity, assessment, aids; classroom sittings in `daily_plan_deliveries` — section × date × period, COVERAGE PER SITTING, one plan serves all parallel sections). The teacher home is `GET lesson-plans/my-day` (published-timetable slots × plan state, class-list fallback); `POST daily-plans/{id}/duplicate` is the bump/reuse. Workflow: draft → submitted → approved/declined (reason required); **director AND principal each hold `lesson_plans.review` independently**, plus the opt-in `lesson_plan_department_review` school setting (active `department_head` positions review, never their own plans). The gate: submitting week N+1 while week N has uncovered sittings requires `lag_justification` (server-enforced in `WeeklyLessonPlanController@submit`; math in `App\Services\LessonPlans\LessonPlanPacing` — coverage normalised per section). Official PDFs: `annual_plan` + `daily_lesson_plan` document types (MoE sheets with signature lines). Families see APPROVED plans only via `/me/*/lesson-plans`, scoped to the student's own section's sittings. Guard rail: `tests/Feature/LessonPlanTest.php`.
- **Timetables are VERSIONED** (`timetable_versions`: draft → generate → tune → publish, one published per term); slots reference period-schedule NUMBERS (`term_periods`) so re-timing the day never touches slots. The solver (`App\Services\Timetable\TimetableSolver`) + `ConstraintValidator` share hard rules (clashes, availability, daily max, block contiguity); locked slots survive regeneration. Subjects may declare a required `room_type` (lab/gym/ict…) — the solver books a free room of that type automatically, falling back to the section's own classroom.
- **The LMS is two lanes over ONE engine (ADR-016)** — question banks/questions (`answer_key` NEVER serialized to takers), `quizzes` (one table for quiz/exam/mock: class quizzes anchor to `subject_assignment_id`, platform mocks are `is_platform` and open to ANY authenticated user — no-school B2C takers are first-class via `user_id` on attempts), server-authoritative `quiz_attempts` (`deadline_at` stamped at start, per-sitting frozen+shuffled paper in `question_ids`, integrity events are review FLAGS, never auto-fails), `assignments` + `assignment_submissions`, `course_materials` (one row of truth + targets pivot, never per-section copies). Graded LMS work links to an `assessments` slot; `App\Services\Lms\GradebookSync` pushes rescaled scores into `assessment_results` — no double entry, never through a locked marklist or closed term. Students/parents reach it exclusively via `/api/v1/me/lms/*` + `/me/exam-prep`. Guard rail: `tests/Feature/LmsTest.php`.
- **ALL notifications flow through ONE pipeline (ADR-018)** — `App\Support\NotificationCatalog` (the typed event registry: category, severity, channel defaults) + `App\Services\Notify\Notifier` (`toUser`/`toFamily`/`toStaff`/`inApp`). The in-app feed (`notifications` table) is ALWAYS written — rows store event key + params, rendered at read time in the reader's language; SMS/email are gated by the recipient's masters (`notify_via_*`) × per-category mutes (`users.notification_preferences`), with `critical` severity piercing category mutes. **SMS costs money: every SMS send anywhere must pass `NotificationCatalog::smsAllowed()`** — the live whitelist is a platform setting Temari.et staff edit at `/catalogs/notification-events`. Noisy events fold via `dedupeKey`; big audiences fan out queued; `notifications:prune` bounds the table. Guard rail: `tests/Feature/NotificationPipelineTest.php`.
- **The tutoring marketplace is escrow-first (v2, live)** — `tutor_profiles` (ADR-012 tutor hat: owning the row IS the credential; Fayda ENCRYPTED + hashed — the one exception to hash-only, reviewers must read it until the Fayda API lands; approval by `tutors.review`, slug minted at approval, only `approved` profiles are public). Hiring: `tutoring_requests` → `tutoring_engagements` (terms SNAPSHOTTED: rate + commission%) → `tutoring_cycles`, one per Ethiopian month (idempotent on engagement × EC year/month like recurring fees; family prepays via gateway → `funded`; sessions BLOCKED on unpaid months) → `tutoring_sessions` (tutor logs, family confirms, 72h auto-confirm via `tutoring:auto-confirm`; disputes freeze only that session) → release (`App\Services\Tutoring\CycleReleaser`, `marketplace.manage` or auto after N days when the operator enables it): net = confirmed hours × rate − commission → wallet; unfulfilled value carries as family credit into the next cycle (`CycleBiller`). Wallet = `tutor_ledger_entries`, append-only, single writer `App\Support\TutorLedger` (row-locked; aggregates on tutor_profiles are non-fillable by design — writers forceFill). Payouts reserve at approval, pay via Chapa transfer or manual. Reviews only on RELEASED cycles (one per direction), aggregates via `TutorRating`. Boosts (`profile_boosts`) extend `boosted_until` = paid directory ranking. Commission default 10%, per-tutor override; knobs in the `marketplace.settings` platform setting. Guard rails: `tests/Feature/TutorMarketplaceTest.php` + `PaymentGatewayTest.php`.
- **ONE payment-gateway layer for Temari.et's own money only** (tutoring escrow, 199 ETB AI upgrade, boosts, School Plan — NEVER school fees, and the 200 ETB/student/yr core fee is school-collected for now): `gateway_transactions` (polymorphic payable, never deleted) + `App\Services\Payments\PaymentGatewayManager` (drivers: Chapa incl. transfers/payouts, Telebirr Fabric, CBE Birr reserved, Fake simulator blocked in production). The manager is the only writer of `paid` (row-locked settle → `GatewayPayable::gatewayPaid()` exactly once); webhooks are doorbells only — always re-verified server-side. Which gateway serves which purpose is the operator matrix in the `payments.gateways` platform setting (`gateways.manage`, UI at /marketplace/gateways); credentials are env-only.
- **Inventory is an append-only stock LEDGER, never an editable quantity** — `inventory_items` (SCHOOL-owned master; `inventory_categories` platform seed + school custom), `stock_levels` (cached branch × item quantity, non-fillable) and `stock_movements` (the digital bin card: signed change + running `quantity_after`), written ONLY by `App\Services\Inventory\StockLedger` (row-locked, refuses overdraw, low-stock alert once per reorder-level crossing via dedupeKey). Workflows: `requisitions` (any staff `inventory.request` → `inventory.approve` countersigns, NEVER their own — the expense four-eyes rule → `inventory.manage` issues, partial fine), `purchase_orders` (OPTIONAL lane — direct receiving never needs one; same no-self-approval; auto-`received` when all lines land) and `stock_takes` (counting posts differences as adjustments against the LIVE balance; uncounted lines untouched; one open per branch). `storekeeper` is the FIFTH role-mapped job title (`JobTitles::ROLE_MAP` → position-driven membership). Items with ledger history deactivate, never delete. **The ASSET REGISTER is a separate identity book** (`asset_units` per physical unit with a PublicId `tag` + `asset_assignments` custody chain — one open holder per unit via partial unique, holder = employee|student|room|section as explicit FKs): quantities stay in the ledger, identity in the register; LOST auto-closes custody, DISPOSED is terminal and never out of someone's hands; the holder-filtered list answers clearance ("has this teacher returned everything?"); storekeepers name holders via the scoped `inventory/holders` picker (id+label only — never `employees.view`/`students.view`). **Textbook lending** (`textbook_loans`, one per student × book × year, partial unique on open loans): bulk issue to a section posts ONE aggregate ledger movement (atomic overdraw check), re-issues skip current holders, returns post aggregate return movements, LOST posts NO movement (the issue already took the copy off the shelf) — family told in-app, never SMS. Guard rails: `tests/Feature/InventoryTest.php` + `AssetInventoryTest.php`.
- **Chat presets are school-curated tri-language templates** (`chat_message_templates`: school-owned + optional branch rows, JSONB body `{en, am, om}` with `{student_name}`/`{teacher_name}`/`{school_name}`/`{date}` placeholders). The composer picker (`GET /chat/templates?conversation_id=`) resolves server-side in the family's language (primary guardian's `preferred_language`); the `chat_template_mode` setting (`suggested` default | `required`, branch-overridable) hard-gates family-reaching teacher text to the preset list via `ConversationAccess::requiresTemplate` + `ChatController::assertTemplateCompliance` (moderators exempt; zero active templates = gate off, never brick teachers). CRUD = `chat.moderate` at `/messages/templates`. Guard rail: `tests/Feature/ChatTemplateTest.php`.
- **Teacher appraisals are per-term snapshots over a school rubric** — `EvaluationPolicy::templateFor()` auto-provisions the MoE 8-criterion template (weights sum exactly 100, rated out of 5) per school; `teacher_evaluations` (one per employee × term, partial unique) run draft → submitted (teacher notified) → acknowledged (teacher signs, optional comment), with `evaluation_scores` SNAPSHOTTING criteria at creation (payroll-freeze pattern — template edits never rewrite signed history). Permissions: `evaluations.manage`/`.view` (director/principal/school_admin) vs `evaluations.view_own` (the evaluated employee, row-checked, drafts invisible). The detail view surfaces platform signals (marklists approved, weekly plans approved) as evaluator CONTEXT — never auto-scored. Guard rail: `tests/Feature/TeacherEvaluationTest.php`.
- **Patronymic naming** — `first_name`, `father_name`, `grandfather_name`, `mother_name`. Never `last_name`.
- **Amounts in ETB** — `decimal(12,2)`, never `varchar` for money.
- **Audit everything** — use the unified `activity_logs` table.

**School-X anti-patterns to NEVER repeat:**
- ❌ weekday FK columns on timetable (use row-per-slot)
- ❌ JSON blobs for data that needs querying
- ❌ two tables for the same concept (e.g., `timetables` + `class_time_tables`)
- ❌ `period_id: varchar(20)` pseudo-FKs
- ❌ school-scoped grade levels
- ❌ `varchar` for penalty amounts
- ❌ `last_name` field

See `knowledge/temari.et-school-x.et-knowledge.md` §11 for the full mistake list.

---

## 4. Identity & Roles Model

### One user, multiple roles, multiple schools
A single `users` row can be: teacher at School A + parent of student at School B + director at School C. This is intentional and must be respected throughout.

### The authorization kernel (ADR-010) — read before touching any permission
- **`memberships` is the ONLY record of roles** (`user × school? × branch? × role`). Users are NEVER assigned Spatie roles — Spatie tables hold only the role → permission catalog.
- Effective authority = one deny-by-default rule: `User::allowedTo(permission, schoolId, branchId)` — platform memberships apply everywhere, school memberships to their school, branch memberships to their exact branch. **No global fallback exists**; a role at School A grants nothing at School B or without a context.
- In policies/controllers use `hasPermissionForScope($perm, $model->school_id, $model->branch_id)` (row-level, unspoofable) or `hasContextPermission($perm)` (list-level, validated `X-School-Id`/`X-Branch-Id` context).
- Teachers act through OWNERSHIP permissions (`grades.manage_own`, `attendance.record_own`) — only on their own subject assignments / homeroom sections. Supervisory roles hold the unsuffixed variants.
- **The school-wide (All branches) workspace is a first-class WORKING mode, not read-only.** Principals/school admins act on any branch without switching context: branch-anchored writes resolve their target via `Controller::targetBranch()` (explicit `branch_id` in the payload wins, else the validated `X-Branch-Id` context) with the existing `hasPermissionForScope()` check as the authority gate; FormRequest rules mirror it via `ResolvesTargetBranchId`. School-wide lists accept an optional `branch_id` narrowing filter (`Controller::branchFilterId()`). Frontend: `BranchField` in create sheets / `BranchScopePicker` on register pages (`components/ui/branch-select.tsx`), rendered only when `useBranchScope().needsBranch`. **Every new branch-anchored feature must support this** — never force a school manager to switch workspaces to write, and never guess a branch: the target is always named explicitly. Guard rail: `tests/Feature/SchoolWideWriteTest.php`.

### Three access lanes — never mix them
1. **Platform lane** — Temari.et staff (platform memberships), `hasPlatformPermission()`.
2. **Staff lane** — memberships × catalog × scope, per the kernel above.
3. **Relationship lane (ADR-012)** — parents/students/tutors. NEVER membership-backed: a parent's access derives from `student_guardians` (gated per-link by `can_view_grades`/`can_view_attendance`/`can_pay_fees`); a student's from their own `students.user_id` link. Served exclusively via `/api/v1/me/*`. `student`/`parent`/`tutor`/`vendor` in the Role enum are labels for this lane and are never assignable.

**Sign-in is ONE `identifier` field: a phone number OR a Temari student ID.** Students without their own phone get a phone-less account (`users.phone` is nullable for exactly this case) that signs in by `students.public_id` + PIN. ID resolution lives only in `App\Support\LoginIdentifier` and never reaches an account holding memberships or a parent profile (a semi-public card code must not become a staff/guardian handle); login is rate-limited per identifier + IP with generic errors. Setup links and PIN-reset OTPs for phone-less students route to the primary guardian's phone (`LoginIdentifier::resetDelivery`), with SMS copy that names the child and the ID — never confusable with the guardian's own parent account. The student's `primary_phone` is the STUDENT's own; a guardian's number is rejected at registration and account creation. Guard rail: `tests/Feature/StudentIdLoginTest.php`.

Role-to-profile mapping:
- `parent` → `parents` table (+ `student_guardians` links)
- `student` → `students` table (global person; may have no `user_id` for young children; nullable registration provenance, tenancy lives on `student_enrollments`)
- staff roles → `employees` (the person's HR file per branch) + `employee_positions` (the JOBS — one row per job title, multi-job-title is normal) + membership. **Branch staff roles are POSITION-DRIVEN:** the four role-mapped job titles (teacher/director/registrar/finance_officer, `App\Support\JobTitles::ROLE_MAP`) sync memberships via `SyncPositionMembershipsAction` — adding/ending such a position grants/revokes the branch role. Never create staff memberships another way.
- `tutor` → `tutor_profiles` (v2)
- `vendor` → `vendors` (v3)
- platform staff → platform membership only

### Multi-school context switching
The UI must provide a **school/role switcher**. When a user is logged in, they select their active school context. All subsequent actions are scoped to that context. UI must show clearly which school/role is active.

### Time-scoped access
- Terms have a lifecycle: `planned → active → closed` (`terms.status`).
- Current/active term: full read + write. **Closed term: read-only — enforced by `App\Support\TermGate::assertWritable()`; call it in every mutation of term-anchored data.**
- Previous school (no longer employed): read own entries only (planned).
- Another school (not employed): zero access — kernel-guaranteed.

---

## 5. UI/UX Standards

### Design Philosophy
- **Modern, clean, professional** — think Notion meets Linear meets a mobile banking app.
- **Ethiopian users first** — consider low-literacy scenarios, RTL isn't needed (Amharic is LTR), but text must render correctly.
- **Density:** Dashboard = information-dense. Forms = one task at a time, generous spacing.
- **Color:** Use a consistent design system. No random color choices. Define a palette in the Tailwind config.
- **Icons:** Use a single icon library consistently (Lucide preferred).
- **Feedback:** Every action gets visual feedback (loading, success, error). Never leave the user guessing. **Async action buttons use `loading={busyFlag}` on the shared `Button` / `AlertDialogAction`** (spinner + auto-disable); keep `disabled={}` for validation-only conditions, and Cancel/Back buttons next to a running action stay `disabled`, never `loading`.
- **Deleting data ALWAYS requires a confirmation dialog — no exceptions, anywhere in the app.** Use the shared `useConfirmDelete()` hook (`frontend/components/ui/confirm-delete.tsx`); never call a DELETE endpoint directly from a click handler. (Reversible, non-destructive toggles — e.g. notification switches — deliberately skip confirmation.)
- **Every upload surface accepts DRAGGED files as well as picked ones** — wire `useFileDrop()` (`frontend/components/ui/dropzone.tsx`), spread its `dropProps` on the region around the picker, wear `DROP_ACTIVE` while `dragOver`, and hand the hidden input's `onChange` list to the same `takeFiles` so one validator (accept + size, translated errors in `common.upload.*`) serves both paths and a dropped file lands in the same pending/rename step. `<DropHint />` is the desktop-only "or drag files here" nudge; touch keeps the tap wording, since phones have no drag. Guard rail: `components/ui/dropzone.test.tsx`.

### Table & Data Standards (MANDATORY for every table/DataTable)
- **Pagination + search + filters on every list** where they make sense — server-driven (`useServerTable` + `HandlesListQueries`) for big registers, client mode for small loaded sets.
- **Every table pages itself, and the user picks the size.** DataTable slices CLIENT-mode rows automatically (25 by default) and server mode pages through `useServerTable` — nothing to wire per page. The footer's rows-per-page picker offers 25/50/75/100 (`lib/use-page-size.ts`; never more, `HandlesListQueries::perPage` clamps at 100) and the choice is ONE app-wide preference that follows the user across every register. The footer only appears once there is more than 25 rows to walk through, so short lists stay bare. Selection ("select all", bulk actions) covers the CURRENT PAGE — never rows the user cannot see. Opt a table out with `paginated={false}` only when every row must render at once. Guard rail: `components/ui/data-table.test.tsx`.
- **Every labelled column sorts.** Client mode: sortable by DEFAULT (numeric-aware, empty values sink last) — set `sortable: false` only for columns with no meaningful order, `sortValue:` for computed/nested cells. Server mode: opt-in per column AND the key must be in the endpoint's `applySort` whitelist (real table columns / select aliases only — never sort relations without a join). Mobile gets a dedicated **Sort pill** in the toolbar automatically (cards have no headers) — nothing to do per page.
- **No dropdown is ever blank.** `SelectContent` auto-shows a translated notice when it has zero options; when the emptiness has a known setup cause (no branch / active semester / grade offering / section / academic year), pass `emptyNotice={tc("emptySelect.<key>")}` so the user is told the exact fix (keys live in `common.emptySelect`).
- **Filters cascade logically:** a child filter renders only after its parent has a value — section only after grade, branch only after school. Declare it with `dependsOn` on the `DataTableFilter` (options may be a function of the parent value); DataTable hides the child until the parent is picked and clears it when the parent changes. Never show a section/branch filter that ignores its parent.
- **Row actions are inline icon buttons with tooltips — never a 3-dot kebab menu.** Every `DataTableAction` MUST set `icon:` (icon-less actions fall into the legacy overflow menu — that is a bug). The actions column is **sticky to the right edge**: on a horizontally scrolled row the buttons stay put with an edge shadow (built into DataTable — nothing to do per page).
- **Clicking a row performs its main action** — mark that action `primary: true` (view first when a detail surface exists, else edit; never a destructive or approve/reject action). An explicit `onRowClick` overrides it. Cells with their own click behavior (checkboxes, CopyableId, ContactActionCell, switches) must `stopPropagation`.
- **Copyable/shareable values** (phones, emails, bank account numbers, receipt/transaction references) always render via `ContactActionCell` — a popover with Call/Email + Copy + Share (Share appears only where the Web Share API exists, i.e. mobile). Use `kind="value"` for non-contact values. Internal public IDs stay `CopyableId` (instant tap-to-copy — deliberately NOT a popover).
- **Tooltips on every icon-only button** — inline table actions get them automatically; anywhere else use `title` + `aria-label` (translated) or a Radix Tooltip.
- **Bulk actions mirror the row actions, and SKIP-AND-REPORT.** Whatever an admin may do to one row in their scope they should be able to do to a selection (`bulkActions` on DataTable, per-permission). Server side: authorize PER ROW (never one blanket check), do what you can, and return `meta: { <verb>, requested, skipped: [{id, name, reason}] }` via `App\Http\Controllers\Concerns\HandlesBulkActions` (`bulkRows()` + `self::bulkIdRules()`, cap 500) — `reason` is a stable machine key the client translates from the SHARED `common.bulkSkip.*` vocabulary, never a sentence. Guard the actor's own row explicitly (`isSelf()`): a super admin bypasses every policy via `Gate::before`, so the "not on yourself" rules single-row endpoints rely on cannot protect a sweep. **Register `x/bulk/verb` BEFORE `x/{model}/verb`** or the route binding swallows the literal "bulk". Frontend: `runBulk()` / `useBulkConfirm()` / `reportBulkResult()` in `components/ui/bulk-actions.tsx` ("0 done" never shows a green toast next to a skip warning), and approve/reject queues share `components/ui/bulk-decision-dialog.tsx`. Guard rail: `tests/Feature/BulkActionsTest.php`.

### New-Feature Checklist (apply to EVERY new feature)
1. **Demo seed:** add representative rows to `DemoSeeder` (backend/database/seeders/) in demo-worthy states — a feature nobody can see on staging does not exist. Guard rail: `tests/Feature/DemoSeederSmokeTest.php`.
2. **Global search:** if the feature introduces human-referenced records or identifiers (names, titles, receipt/reference numbers…), wire them into `GlobalSearchService` (+ palette group in `components/app-shell/global-search.tsx`, i18n labels, pg_trgm index on the searched column). Visibility must mirror the list endpoint's scoping. Guard rail: `tests/Feature/GlobalSearchTest.php`.
3. **Notifications:** if the feature creates anything a user should react to (an approval to decide, money to pay, work to review, a status change on something they own), register the event(s) in `NotificationCatalog::EVENTS`, add copy to `lang/{en,am,om}/notifications.php`, and dispatch through `Notifier` (`toUser`/`toFamily`/`toStaff`) with a deep `link` — never hand-roll SMS/email (ADR-018). Use a `dedupeKey` for repeatable events.
4. **Tables** follow the Table & Data Standards above.
5. **In-app docs** for the affected roles (§6), in all 3 languages.
6. **Analytics:** every major action ("who did what when") must reach PostHog. Backend: `ActivityLogger::log()` is auto-mirrored — prefer it; for events that aren't audit entries call `App\Services\Analytics\Analytics::capture($actor, 'domain.action', $props, $schoolId, $branchId)` (queued, no-op without `POSTHOG_KEY`, scalars only — never free text/PII). Frontend: `lib/analytics.ts` `track()` for UI-only funnels. Errors are captured automatically (exception handler backend, `capture_exceptions` + error boundary + 5xx `api_error` frontend) and Laravel log records ship to the PostHog **Logs** product via the `posthog` log channel (OTLP, min level `POSTHOG_LOG_LEVEL`) — never add a second error reporter or hand-roll log shipping.

### Mobile Standards
- Breakpoint: `< 768px` = mobile layout. Must feel like a native app.
- Bottom navigation bar on mobile for primary nav (not sidebar).
- Touch targets: minimum 44×44px.
- No horizontal scroll on mobile.
- Form inputs: full-width, large enough for touch.
- Modals on mobile = full-screen sheets.

### Accessibility
- All interactive elements keyboard-navigable.
- Amharic text: use `font-ethiopic` or a compatible web font (e.g., Noto Sans Ethiopic).
- Sufficient color contrast (WCAG AA minimum).

---

## 6. Feature Documentation Rule (MANDATORY)

Every time a feature is added or changed, update the **in-app documentation** for the affected role(s).

Documentation pages live **inside the app** under the sidebar section labeled "Documentation" or "Help" scoped per role:
- Student docs → student-facing docs page
- Teacher docs → teacher-facing docs page
- Parent docs → parent-facing docs page
- Director/Principal/Admin docs → admin-facing docs page
- Super Admin docs → super admin docs page

**Format:** Plain language, step-by-step, with screenshots or diagrams where helpful. Written in all 3 languages.

Also update `knowledge/` files when making architectural decisions.

---

## 7. API Design Rules

- **Versioned:** all routes under `/api/v1/`
- **RESTful** with Laravel API Resources for responses
- **Consistent envelope:**
  ```json
  { "data": {...}, "meta": {...}, "message": "..." }
  ```
- **Error format:**
  ```json
  { "message": "...", "errors": { "field": ["..."] } }
  ```
- **Always paginate** list responses
- **Search is matched WORD BY WORD** — never `column ilike '%$q%'` per column. Ethiopian names live in three columns, so a full name ("Abdi Fikre Gemeda") matches none of them and the record goes invisible. Go through `App\Support\SearchTerm` (`HandlesListQueries::applySearch()` + `$this->needle()`) against the person tables' generated `search_text`; the client mirror is `frontend/lib/search.ts`. Guard rails: `tests/Feature/ListSearchTest.php` + `lib/search.test.ts`
- **Policy-gated:** every controller action must go through a Laravel Policy
- **No raw SQL** in controllers — use Eloquent + query scopes

---

## 8. Code Style & Conventions

### Backend (Laravel)
- Follow `backend/AGENTS.md` (Laravel Boost guidelines)
- Run `vendor/bin/pint --dirty --format agent` after every PHP change
- Tests with Pest — feature tests > unit tests. `composer test` runs them in parallel (~60s); **`backend/TESTING.md` explains the Postgres `max_locks_per_transaction` requirement** — a stock 64 makes parallel runs fail with misleading "out of shared memory" errors that look like hundreds of test failures
- **Any test that builds a date for a school-day field uses `Ethiopia::today()` / `Ethiopia::now()`, never `now()` or `today()`** — the app clock is UTC, school-day judgements run on Addis wall time, and the gap between them is a real 3-hour-a-day failure window
- No logic in controllers — use Actions or Services
- Migrations: always reversible (`down()` implemented) and **one table per migration file** — never bundle several `Schema::create` calls into one migration
- **ADDITIVE migrations only (since 2026-07-22, staging carries test data): NEVER edit an existing migration file.** Every schema change — new column, index, rename, drop — is a NEW timestamped migration (`make:migration add_x_to_y_table`) with a working `down()`, per standard Laravel practice. New tables still get their own `create_<table>_table` file; partial unique indexes still use raw `DB::statement` inside the new migration. `migrate:fresh` only when Abdul explicitly asks.

### Frontend (Next.js)
- **pnpm, not npm** (`pnpm-lock.yaml`). `pnpm lint` / `pnpm test` / `pnpm typecheck` before every push
- Follow `frontend/AGENTS.md` (architecture: contexts, data layer, page checklist) **and `frontend/DESIGN.md`** (the Temari design system: brand, tokens, states, mobile rules) — both are normative for any UI work
- Read `node_modules/next/dist/docs/` before using Next.js APIs
- Components in `components/`, pages in `app/`
- i18n strings in `lib/i18n/` translation files (one file per language per domain)
- No hardcoded strings in JSX
- Use `dynamic()` for heavy dashboard widgets
- New pure logic in `lib/` gets a `lib/<name>.test.ts` (Vitest). Component tests are not set up yet — see the note on `employee-wizard.tsx` below

### Known oversized files (split before adding to them)
- `frontend/components/employees/wizard/employee-wizard.tsx` — one ~1,700-line component. Needs React Testing Library set up and behaviour tests written BEFORE it is split; do not refactor it blind.
- `backend/app/Http/Controllers/Api/V1/ChatController.php` (~1,030 lines) — message-level actions already moved to `ChatMessageController`; conversations, directory and uploads still share one class.
- `frontend/lib/types.ts` (~4,300 lines) — hand-maintained. Enum drift is guarded by `lib/enum-parity.test.ts`; the interface shapes are not.

---

## 9. What is Being Built (v1 Scope)

1. **School Management System (SMS):** Enrollment, attendance, fees, payroll, timetable, reports
2. **Learning Management System (LMS):** Continuous assessment, assignments, course materials, results
3. **AI Exam Prep (B2C):** Grade 6/8/12 national exam questions, AI tutor, learning paths

Pillars 4–7 (tutor marketplace, online courses, ecommerce, vendor portal) are **schema-ready** but not built in v1.

---

## 9b. Temari AI (the /ai assistant)

- **One chat surface, seven lanes, NO user-facing picker** (`App\Enums\AiLane` + `App\Enums\AiSurface`): student tutor / parent / teacher / leadership / registrar / finance / platform — a lane mirrors the platform's access-lane model exactly (family lanes ride ADR-012 guardian links, staff lanes the ADR-010 kernel). **The user never chooses an assistant (Abdul, 2026-07-22): the workspace decides the SURFACE** (school / family / platform — the family-portal nav pins `?surface=family`), and the backend COMPOSES one agent from every lane the user holds there: all staff lanes merge into ONE school assistant (`App\Ai\Agents\StaffAgent` — prompt modules + tool union per held lane; a teacher-only account composes to exactly the old teacher copilot). A conversation stores its PRIMARY lane (surface default order + lane priority in `AiLane::surfacesFor()`) and FREEZES surface + school/branch context at creation; a workspace switch never widens an old session, while the staff tool set follows the roles currently held in the frozen scope.
- **Agents & tools live in `app/Ai/`** (Laravel AI SDK): one agent class per lane, each with permission-scoped tools. **Numbers-from-tools-only** — every figure in an answer comes from a tool query; tools re-check `hasPermissionForScope()` / guardian-link flags internally and deny gracefully. Write tools never put anything in front of students UNASKED: AI-generated question-bank questions land PUBLISHED (a bank question is a reusable building block, not student-facing on its own — `SavesQuestionDrafts`), the exam/quiz that carries them is always CREATED as a draft (`CreateExamTool` / `CreateMockExamTool`, both supporting the studio's paper `parts` layout), and the AI-chat surface has full exam-studio parity via `UpdateExamTool` (inspect the paper, retitle, settings, regroup into parts, reorder, add/remove bank questions, publish/close) — with the rule that **sensitive actions (publish/close) go through only after the USER explicitly confirms in chat** (the tool refuses without `confirmed=true`; the prompt mandates a yes/no choices block first). Publish/close share ONE path with the studio: `App\Services\Lms\QuizPublisher` (points freeze, draft-question refusal, draw check, first-publish student fan-out). Exam authoring follows the kernel's reach: teachers build for their OWN classes (`lms.manage_own`), directors/principals for ANY class in their scope (`lms.manage` — `ClassCatalogTool` discovers the classes, the exam still anchors to the class teacher's subject assignment, one branch + one semester per exam), and platform content staff build platform mocks for the whole platform (`exam_prep.manage` — `ExamPrepCatalogTool` + `CreateMockExamTool`, `is_platform` drafts over platform banks); `UpdateExamTool` re-judges authority via `QuizPolicy` in the QUIZ's own scope plus the conversation's frozen context, and mirrors the studio's frozen-layout rule once someone sat the paper. **Chat-parity principle (Abdul, 2026-07-21): whatever a role can do in the UI should be doable through the AI chat, with explicit in-chat confirmation for sensitive steps** — apply it when extending lanes. The chat is BUTTON-FIRST (preamble rule): every bounded question and every set of follow-up suggestions renders as tappable `choices` chips, and saved exams render as a live card via the `exam_preview` fenced block (`{"quiz_id": N}` → `frontend/components/ai/ai-exam-preview.tsx`, which opens the studio's own full-screen `ExamPreview`, fetched fresh) — the exam flow is DRAFT-FIRST: save immediately, show the card + next-step buttons, never retype a saved paper as chat text.
- **AI→chat handoff (the AI never sends)**: lanes with school chat (teacher/leadership/registrar/finance/parent) carry `ChatRecipientsTool` — a read-only mirror of the ADR-019 chat directory (`ChatDirectory`/`ConversationAccess`, so the AI sees exactly what the new-chat picker would) — and end an approved draft with ONE fenced `send_message` block (`{to: {kind: family|staff|channel, …ids, label}, body}`; protocol in `TemariAgent::chatSendProtocol()`). The app renders a Send card (`frontend/components/ai/ai-send-message.tsx`): the USER reviews/edits and their tap sends through the normal `/chat` | `/me/chat` endpoints — reachability and the communication-book approval gate re-validate server-side, sends are idempotent via a content-derived `client_uuid`, and the card shows the pending-approval state when the gate parks the message.
- **Transcripts** live in the SDK's `agent_conversations` tables; Temari metadata (lane, context, pin, title) in `ai_conversations`. Conversations are strictly self-scoped — no supervisory read exists, ever.
- **Entitlements** (`App\Services\Ai\AiEntitlementService`, quotas in `config/temari-ai.php`): family lanes = free daily teaser or the B2C `AiSubscription` (gateway purpose `ai_subscription`); staff lanes = `schools.ai_plan_until` (School Plan — platform staff grant via `/schools/{school}/ai-plan`, never school-side); platform staff unlimited. Enforced per prompt (402 = no plan, 429 = daily quota).
- **Streaming**: POST `/ai/conversations/{c}/messages` streams SSE (Vercel AI data protocol); frontend client in `frontend/lib/ai.ts`, UI in `frontend/components/ai/`.
- **Embedded generators**: POST `/ai/actions` (quiz_questions / report_comment / lesson_week / parent_message / letter) power the ✨ `AiAssistButton` inside the question-bank studio and lesson planner.
- **Scheduled**: `ai:weekly-briefings` (School Plan leadership, Monday) and `ai:parent-digests` (premium parents, Friday) — in-app notifications only (`ai.*` events), AI text never costs SMS money.
- Academic integrity: the tutor refuses in-progress exam attempts (server-checked via the attempt engine's reveal rules) and never leaks unreleased answer keys.
- Guard rail: `tests/Feature/TemariAiTest.php`.

## 10. Ethiopian Context Specifics

- **Calendar:** Ethiopian (Ge'ez) calendar — 13 months. Handle at application layer. Never assume Gregorian.
- **Calendar & clock are DISPLAY settings (July 2026):** storage/wire stay Gregorian ISO + 24h UTC everywhere; `schools.settings.calendar_mode` (`ethiopian` default | `gregorian`) and `clock_mode` (`ethiopian` dawn-count default — "2:00 ጠዋት" = 8:00 AM | `standard`) decide how dates/times are WRITTEN, branch-overridable like every branch setting. Backend rendering (SMS/email/PDF) goes through `App\Support\DateFormatter` (+ `dualDate()` blade helper — official PDFs ALWAYS print both calendars); notification ISO-date params localize per reader in `NotificationCatalog::localizeParams`. Frontend: **every user-facing date/time comes from `lib/dates.ts` (`fmtDate`/`fmtTime`/`fmtDateTime`…) — never `toLocaleDateString`/date-fns directly**; the workspace stamps prefs via `school-context` (family portal: active child's school), `useCalendar()` for components that branch on the mode, and the shared DatePicker/TimePicker render an Ethiopian 13-month grid / dawn-count wheels while still speaking ISO strings. Guard rail: `tests/Feature/CalendarDisplayTest.php`.
- **Academic year:** 2 semesters, starts September (Ethiopian calendar).
- **Payments:** Telebirr (dominant), CBE Birr, bank transfer. Stripe = international only.
- **Grade levels:** Nationally fixed (KG-1, KG-2, Grade 1–12). Seeded at platform level.
- **Naming:** Patronymic — `first_name + father_name + grandfather_name`. No family surnames.
- **SMS:** Primary parent comms. Many parents have no smartphone or reliable internet.
- **Bandwidth:** Design for 3G. Compress assets. Lazy-load everything non-critical.
- **National exams:** Grade 6 (primary completion), Grade 8 (middle school completion), Grade 12 (EUEE/university entrance). **Grade 10 has NO national exam** — never list it as an exam grade.

---

## 11. Monetization Context (don't break this)

**Temari takes NO cut of school fee payments — ever.** Schools keep 100% of what families pay them; there is no per-transaction platform fee, and no payment gateway sits between families and the school's own accounts. School fee payments are made directly to the school (bank/wallet/cash) and only **verified** through the platform (check.et receipt verification). If a gateway for school fees is ever added, it will still take no cut — Abdul will say so explicitly first.

Revenue is **subscriptions and services** (per the Elon Academy model proposal, June 2026):
- **Core platform: 200 ETB / student / year**, paid by the PARENT at registration — the school carries no software cost for the core platform.
- **School Plan (optional, school-paid): 10 ETB / student / month** — unlocks automated payment verification (check.et), electronic revenue receipts (Ministry of Revenue, when live), and School AI for leadership/teachers. Core features are never paywalled behind it.
- **B2C parent/student AI upgrade: 199 ETB / month** (AI Exam Prep etc.) — collected via Chapa or Telebirr gateway (the ONLY place a payment gateway exists, and it collects Temari's own subscriptions, never school money).
- **NFC hardware:** ID card 300 ETB one-time lifetime (student card parent-paid, staff card school-paid, both optional), replacement 500 ETB, one free attendance reader per branch, extra readers 30,000 ETB.
- **Tutor marketplace commissions** (v2) — e.g. when a tutor is hired through the platform.
- Never expose pricing logic in frontend without going through the subscription/billing service.

---

*Last updated: July 2026. Update this file when major decisions change. Rules that can be machine-checked belong in the enforcement layer (§2.0), not in more prose here.*

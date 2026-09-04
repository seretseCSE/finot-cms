# Temari.et — Developer & AI Agent Guidelines

> Companion to `CLAUDE.md`. This doc covers implementation patterns, workflow, and conventions that every developer (human or AI) must follow.
> Last updated: June 2026.
>
> **On the old platform (School-X):** The knowledge base at `knowledge/temari.et-school-x.et-knowledge.md` documents School-X's design and mistakes. Read it to understand the problem domain and what went wrong — **never to copy from it**. Every Temari design decision must be independently reasoned from first principles. When School-X solved something badly, the question is not "how do we do it like School-X?" but "what is the *right* way to solve this?"

---

## Working Principles

### Before writing any code
1. Read `CLAUDE.md` (project root)
2. Read the relevant section of `knowledge/temari.et-school-x.et-knowledge.md` **as context only** — understand the domain and past mistakes, do not replicate old patterns
3. Design the solution from first principles: ask "what is the best way to solve this?" not "how did School-X do this?"
4. Check existing Temari migrations/models for established patterns — be consistent with what has already been built in *this* codebase
5. If the task touches the DB schema, think through the design independently before referencing any old schema

### After writing any code
1. Run backend tests: `php artisan test --compact`
2. Run Pint: `vendor/bin/pint --dirty --format agent`
3. Verify UI at both mobile (375px) and desktop (1280px) widths
4. Update role-specific in-app documentation if a user-facing feature was added
5. Update `knowledge/` files if an architectural decision was made

---

## Backend Patterns

### Controller → Action flow
Controllers are thin. Business logic lives in `app/Actions/`:
```
POST /api/v1/students/enroll
  → StudentController@enroll
    → EnrollStudentAction::execute($data, $school)
      → dispatches EnrollmentCreatedEvent
```

### Policy enforcement
Every controller method must call `$this->authorize()` or use `->authorizeResource()`:
```php
public function store(EnrollStudentRequest $request): JsonResponse
{
    $this->authorize('enroll', Student::class);
    ...
}
```

### Multi-tenancy scoping
The BRANCH is the tenant boundary. Never query operational tables without a `branch_id` (or an explicit school scope for principals). Resolve the branch from the validated context (`Controller::activeBranch()` / `activeBranchOrNull()`), never from raw headers, and authorize with the kernel:

```php
// Row-level (unspoofable — judged in the DATA's own scope):
$user->hasPermissionForScope('students.update', $student->school_id, $student->branch_id);

// List-level (judged in the request's validated active context):
$user->hasContextPermission('students.view');
```

Do NOT use `auth()`-based global model scopes (hidden coupling, breaks jobs/CLI) and NEVER use global permission checks (`can('x')`, `hasPermissionTo`) for tenant data — see ADR-010.

### API Resources
Always wrap responses in API Resources:
```php
return StudentResource::collection($students);
```

### Pagination
Default 25 per page. Always paginate list endpoints:
```php
$students = Student::paginate(request('per_page', 25));
```

---

## Frontend Patterns

> **Normative sources:** `frontend/AGENTS.md` (architecture) and `frontend/DESIGN.md` (design
> system) — added July 2026 with the frontend foundation redesign (design tokens + dark mode
> in `app/globals.css`, Outfit display font, PageHeader/EmptyState/StatCard/Logo primitives,
> app-shell + auth redesign, role-aware dashboard). The sections below stay as component
> API reference; where they conflict with those two files, the files win.

### DataTable — standard for all list views

Every list/index page **must** use `components/ui/data-table.tsx` (`<DataTable>`). No custom table implementations. This component provides:

- Full-width layout with `rounded-md border bg-card`
- Client-side **search** (pass `searchKeys` to enable)
- **Filter popover** with pill-style toggles (pass `filters` array)
- Active filter **chip** display with one-click removal
- **Sorting** on any column marked `sortable: true` (click header, click again to reverse, cancel button clears)
- **Export CSV** and **Export Excel** (from filtered/sorted view, UTF-8 BOM for Amharic)
- **Row checkboxes** with select-all
- **Row actions** dropdown (`...` button, pass `actions` array)
- **Skeleton** loading state (pass `loading={true}`)
- Record count display
- Optional `summary` line above the toolbar

**Column definition:**
```tsx
import { DataTable, type DataTableColumn, type DataTableFilter, type DataTableAction } from "@/components/ui/data-table"

const columns: DataTableColumn<MyType>[] = [
  {
    key: "name",         // must match a key on T (or be custom with render/exportValue)
    label: "Name",
    sortable: true,
    render: (row) => <span className="font-medium">{row.name}</span>,
    exportValue: (row) => row.name,   // plain text for CSV/Excel
    className: "w-48",  // optional TH+TD className
  },
]
```

**Filter definition:**
```tsx
const filters: DataTableFilter[] = [
  {
    key: "is_active",        // must be a key on T for client-side filtering
    label: "Status",
    options: [
      { label: "Active",   value: "true" },
      { label: "Inactive", value: "false" },
    ],
  },
]
```

**Row actions:**
```tsx
const actions: DataTableAction<MyType>[] = [
  { label: "Edit",   onClick: (row) => openEdit(row) },
  { label: "Delete", onClick: (row) => handleDelete(row), destructive: true },
  { label: "View",   onClick: (row) => router.push(`/items/${row.id}`), hidden: (row) => !row.is_active },
]
```

**Full usage:**
```tsx
<DataTable
  columns={columns}
  data={items ?? []}
  loading={items === null}
  searchKeys={["name", "code"]}
  searchPlaceholder="Search items…"
  filters={filters}
  actions={actions}
  onRowClick={(row) => router.push(`/items/${row.id}`)}
  summary={<>Total: <strong>{items?.length ?? 0}</strong> items</>}
  emptyMessage="No items found."
  exportFilename="items"
/>
```

**Rules:**
- Page layout must be `<div className="space-y-6">` (no `max-w-5xl` — DataTable is full-width)
- `loading` = `data === null` (null = fetching, [] = fetched empty)
- `exportFilename` should be descriptive (e.g. `"schools"`, `"branches-42"`)
- Export includes only the currently filtered/sorted rows, not the full dataset
- `is_active` filter values must be `"true"` / `"false"` (string) because filter compares with `String(row[key])`
- For server-side pagination: pass `total={meta.total}` and handle page state externally (the component handles client-side filtering of the current page)

### i18n setup
Translation files live in `lib/i18n/[lang]/[domain].json`:
```
lib/i18n/
  en/
    common.json
    students.json
    teachers.json
    fees.json
  am/
    common.json
    ...
  om/
    common.json
    ...
```

Use a single hook:
```tsx
const { t } = useTranslation('students');
// <span>{t('enroll.title')}</span>
```

Adding a new language = add the folder + JSON files + seed `name_XX` columns.

### Forms — React Hook Form + Zod
All forms must use React Hook Form with Zod v3 validation (never plain `useState` for form fields).

**Stack:**
- `react-hook-form` — form state, submission, `setError`
- `@hookform/resolvers/zod` — bridges Zod schemas to RHF (requires **Zod v3**, not v4)
- `zod@3` — validation schemas
- `components/ui/form.tsx` — shadcn Form component (`Form`, `FormField`, `FormItem`, `FormLabel`, `FormControl`, `FormMessage`)

**Pattern:**
```tsx
const schema = z.object({ name: z.string().min(1, "Required") })
type FormValues = z.infer<typeof schema>

const form = useForm<FormValues>({ resolver: zodResolver(schema), defaultValues: { name: "" } })

async function onSubmit(values: FormValues) {
  try {
    await apiFetch(...)
  } catch (error) {
    if (error instanceof ApiError) {
      for (const [field, messages] of Object.entries(error.errors)) {
        form.setError(field as keyof FormValues, { message: messages[0] })
      }
      if (Object.keys(error.errors).length === 0) toast.error(error.message)
    }
  }
}

<Form {...form}>
  <form onSubmit={form.handleSubmit(onSubmit)}>
    <FormField control={form.control} name="name" render={({ field }) => (
      <FormItem>
        <FormLabel>Name</FormLabel>
        <FormControl><Input {...field} /></FormControl>
        <FormMessage /> {/* shows Zod errors AND backend field errors */}
      </FormItem>
    )} />
    <Button disabled={form.formState.isSubmitting}>Submit</Button>
  </form>
</Form>
```

**Rules:**
- `<FormMessage />` renders inline under each field — both Zod validation and backend `ApiError.errors` field errors
- `toast.error` only for generic errors with no field-level errors (e.g. 500s)
- `form.formState.isSubmitting` replaces a separate `submitting` useState
- Reset the form on dialog close: `form.reset()` in `onOpenChange`
- **Do not upgrade to zod v4** — `@hookform/resolvers` is incompatible with zod v4

### Mobile-first layout pattern
```tsx
// Bottom nav for mobile, sidebar for desktop
<div className="md:hidden">
  <BottomNav />
</div>
<div className="hidden md:block">
  <Sidebar />
</div>
```

### Loading states
Every data fetch must show a skeleton, not a spinner:
```tsx
if (isLoading) return <StudentTableSkeleton />;
```

### School context
Active school context must be accessible from any component via context/store:
```tsx
const { activeSchool, activeRole } = useSchoolContext();
```

---

## Database Migration Rules

1. **Never modify existing migrations** — always create new ones
2. Migrations must have a working `down()` method
3. Column naming: `snake_case`, consistent with existing tables
4. Every FK must have an index
5. Timestamps: always include `$table->timestamps()` and `$table->softDeletes()`
6. Amounts: `$table->decimal('amount', 12, 2)` — never `string`

---

## Documentation Rule (Detailed)

When a feature is added, the in-app documentation page for the affected role must be updated.

### Role → Doc page mapping
| Role | Documentation page route |
|------|--------------------------|
| Student | `/student/docs` |
| Teacher | `/teacher/docs` |
| Parent | `/parent/docs` |
| Director | `/director/docs` |
| Principal | `/principal/docs` |
| School Admin | `/admin/docs` |
| Super Admin | `/super-admin/docs` |

### Doc content format
Each feature section:
```
## [Feature Name]
**Who:** [which role]
**What it does:** One sentence.
**How to use:**
1. Step one
2. Step two
...
```
Available in all 3 languages (tabs or language switcher on the docs page).

---

## Naming Reference

| Concept | DB column | UI label |
|---------|-----------|----------|
| Student first name | `first_name` | First Name / ስም |
| Student father's name | `father_name` | Father's Name / የአባት ስም |
| Student grandfather's name | `grandfather_name` | Grandfather's Name / የአያት ስም |
| School year | `academic_year_id` | Academic Year |
| Time period | `term_id` | Semester / Term |
| School branch | `school_id` (on branch record) | Branch |
| Main school | `main_school_id = null` | Main Campus |

---

## Common Pitfalls (to avoid)

- **Don't use `last_name`** — patronymic system, use `grandfather_name`
- **Don't hardcode school-scoped grade levels** — `grade_levels` is platform-wide seed data
- **Don't close a term without marking previous term data read-only**
- **Don't query across school boundaries** — always include `school_id` in queries
- **Don't skip i18n** — even quick admin screens need translations
- **Don't design for desktop only** — mobile is primary for Ethiopian users
- **Don't assume internet** — key flows must work offline (attendance, marks)

---

## Architecture Decisions Log

### ADR-001 — School ↔ Branch model (supersedes knowledge base §7)
A **school is only an identity holder** (`schools`: `name`, `is_active`). All operational data is scoped to **`branches`** (a separate table with `school_id` FK), **not** the self-referential `schools.main_school_id` design described in the knowledge base §7. Branches carry the address (`country/state/city/sub_city/woreda/house_no`) plus temari-only geo (`longitude/latitude`) and the Ministry `code` (unique).

- Provisioning flow: super-admin creates a school + **Principal** (legal contact) and optional **School Admin** (technical/IT contact). Both are **school-scoped** (no branch → see all branches). The principal/school-admin (or super-admin) then create branches; each branch optionally provisions a **Director** (branch-scoped).
- `branches.longitude/latitude` are returned **only** to users with `branches.view_geo` (Temari staff); never to principals/school-admins. Enforced in `BranchResource`.

### ADR-002 — Identity, roles & scoping
- `users` uses a **single `name`** field (full name); **all other** profile tables (e.g. `employees`, future `students`/`teachers`) use patronymic `first_name`/`father_name`/`grandfather_name`. Login is **phone + password** (email nullable).
- Role catalog (`App\Enums\Role`): platform (`super_admin`, `support_agent`, `finance_admin`, `sales_agent`, `content_admin`), school (`principal`, `school_admin`), branch (`director`, `registrar`, `finance_officer`, `teacher`), and context-derived (`student`, `parent`, `tutor`, `vendor`). `SYSTEM_MANAGER` from early notes is named **`school_admin`** (same permissions as principal).
- **`memberships`** (`user × school? × branch? × role × scope`) is the **authoritative** record of *where* a user holds a role. ~~Users are also assigned their Spatie roles globally for coarse UI checks~~ — **superseded by ADR-010**: users are never assigned Spatie roles at all; every check flows through the kernel.

### ADR-003 — SMS
`App\Services\Sms\SmsClient` (Tiltek impl). `config('sms.enabled')` (env `SMS_ENABLED`) gates real sending: false → **log only** (local default), true → send. New school/branch contacts receive a single-use **password setup link** (`password_setup_tokens`, hashed) by SMS.

### ADR-004 — Academic structure (grade levels, academic years, terms, sections)
The academic foundation that all student/teaching records hang off:

- **`grade_levels`** is **platform seed data** (`GradeLevelSeeder`: KG-1, KG-2, Grade 1–12), never school/branch-scoped. Columns: `code` (stable key, e.g. `G8`), `name`, `cycle` (`App\Enums\Cycle`: kindergarten/lower_primary/upper_primary/secondary/preparatory), `sort_order`, `has_national_exam` (Grade 6/8/12 — Grade 10 sits no national exam). Read-only `GET /grade-levels`, cached 1 day; no soft deletes.
- **`academic_years` and `terms` are branch-scoped** (not school-scoped) — each branch runs its own calendar, consistent with "all operational data hangs off branches" (ADR-001). Years carry `school_id` + `branch_id`; terms denormalize both plus `academic_year_id`. **At most one `is_current` year and one `is_current` term per branch** (enforced in `SaveAcademicYearAction` / `TermController`).
- Creating an academic year **auto-provisions the two Ethiopian semesters** (sequence 1 & 2); directors only edit their dates. `terms` are not created/deleted via API in this slice — only updated (`PUT /terms/{term}`).
- **`sections`** are stable identity = `branch_id × grade_level_id × name` (unique), **not** year-scoped (year scoping will live on future `student_enrollments`). Optional `capacity` and `homeroom_employee_id` (→ `employees`).
- Branch-scoped endpoints (`academic-years`, `sections`) resolve the active branch from `X-Branch-Id` via `Controller::activeBranch()` → **422 "Select a branch"** when absent. Authorization: `academic_years.*` / `sections.*` permissions × `User::operatesInBranch()` (platform OR school manager OR branch member). Principal/SchoolAdmin/Director get full CRUD; Registrar manages sections + views years; Teacher/FinanceOfficer view only.
- Frontend: `/academic` (years list + `/academic/[id]` term detail) and `/sections`, gated on an active branch context, i18n domain `academic` (en/am/om), nav section `academic`.

### ADR-005 — Students & enrollment
- **`students`** = permanent identity, **branch-scoped** (`school_id` + `branch_id` = home branch). Patronymic `first_name` (req) / `father_name` (req) / `grandfather_name` / `mother_name`; `gender` (`App\Enums\Gender`), `date_of_birth`, `national_student_id` (nullable unique), `primary_phone`. **Fayda IDs are stored hashed only** (`fayda_hash`, SHA-256) — never plaintext, accepted as `fayda_id` input and hashed in the action; `user_id` nullable (young children have no login).
- **`student_enrollments`** = time-scoped: one row per student per academic year, `unique(student_id, academic_year_id)`. Anchors student → `section_id` (carries grade) + `academic_year_id`; `grade_level_id` is **denormalized** from the section for fast queries/promotions. `status` = `App\Enums\EnrollmentStatus` (active/promoted/repeated/transferred_out/withdrawn/graduated).
- **Business rules in `EnrollStudentAction`** (throw `ValidationException` → 422 with field errors): year & section must belong to the student's branch; one active enrollment per student per year; section `capacity` not exceeded. `RegisterStudentAction` creates the student and optionally enrolls in one step.
- Endpoints (branch-scoped via `X-Branch-Id`): `apiResource students` + `POST students/{student}/enrollments`. `Student::currentEnrollment` (`hasOne`, latest active) drives the list's grade/section columns. Permissions `students.*` + `enrollments.create`; Principal/SchoolAdmin/Director/Registrar manage, Teacher/FinanceOfficer/Support view. `StudentPolicy` (+ `enroll` ability) × `operatesInBranch`.
- Frontend `/students` (i18n domain `students`, nav section `people`): register/edit via `StudentSheet` (optional one-step enrollment on create), enroll existing via `EnrollStudentSheet`, search by name/national id.

### ADR-006 — Parents & guardians
- **`parents`** (model `ParentProfile` — `Parent` is a PHP reserved word) = NOT school-scoped profile; `user_id` unique. Primary phone lives on the linked `users` row (login id); profile holds `secondary_phone`, occupation/employer, verification, and SMS-first notify prefs. **A parent never gets a membership** — their access is derived from the child's enrollment.
- **`student_guardians`** links student ↔ parent with per-relationship permissions (`can_view_grades/attendance/pay_fees/receive_sms`), `is_primary` (one per student), `emergency_contact`, `priority_order`, `relationship` (`App\Enums\GuardianRelationship`). `unique(student_id, parent_id)`.
- **`AddGuardianAction`** provisions/reuses the parent user (find-or-create by phone, assign `parent` role), upserts the `ParentProfile`, creates the link (422 on duplicate), enforces single primary, and texts a setup link **for new accounts only**.
- **`PasswordSetupService`** was extracted from `ProvisionContactUserAction`; both staff provisioning and guardian provisioning now share it (issues hashed `password_setup_tokens` + SMS link).
- Endpoints: `GET/POST students/{student}/guardians`, `PUT/DELETE guardians/{guardian}`. Permissions `guardians.view` / `guardians.manage`; `StudentPolicy::viewGuardians/manageGuardians` (index/store) + `StudentGuardianPolicy` (update/delete), all × `operatesInBranch`.
- Frontend: guardians managed on the **`/students/[id]` detail page** (identity + enrollment history + guardian cards) via `GuardianSheet` (add/edit with permission toggles); the list row links to the detail page.

### ADR-007 — Staff / Teachers (employees UI)
- The `employees` table + `employees.*` / `staff.manage` permissions existed from the foundation (ADR-002) but had no API/UI; this slice exposes them. Employees here are **branch staff** (`branch_id` = active branch).
- **`CreateEmployeeAction`** provisions/reuses the user (find-or-create by phone), assigns a **branch-scoped role** + membership, stores the full employment profile, and texts a setup link (via shared `PasswordSetupService`) for new accounts. **Only `teacher`/`registrar`/`finance_officer`/`director` may be created here** (enforced in `StoreEmployeeRequest`); school-scoped roles (principal/school_admin) are provisioned via the school flow only.
- Endpoints: `apiResource employees` (branch-scoped via `Controller::activeBranch()`). `EmployeePolicy::canTouch` gates by branch (or school management when `branch_id` is null). Update edits the profile only (not login phone or role); delete is principal/school-admin level (`employees.delete`), directors have create/update but not delete.
- Frontend `/staff` (i18n domain `staff`, nav section `people`) with `EmployeeSheet` using `Combobox` over the `lib/data` reference lists (designation, education level, field of graduation, professional level, institution). `Combobox.options` widened to `readonly string[]` so `as const` data lists pass directly.

### ADR-008 — Context switching (school → branch selection)
Branch-scoped pages need an active branch (`X-Branch-Id`), but school-scoped users (principal/school_admin) and platform users (super_admin) have no branch *membership*, so they previously couldn't reach those pages.

- **`GET /auth/contexts`** (`ContextController`) returns the schools + branches the user may operate in: platform users get **all** active schools/branches; a school-level membership unlocks **all** branches of that school (`can_manage: true`); a branch-level membership unlocks only that branch. (The `SetActiveContext` middleware already permits any branch for users `relatedToSchool`.)
- Frontend `school-context.tsx` fetches this and exposes flat `options` (`ContextOption`: platform / school-level / branch-level), `activeOption`, and `switchTo`. It **auto-selects the first concrete branch** when nothing valid is stored, so a director lands ready-to-work. Both switchers (`sidebar.tsx` inline, `context-switcher.tsx`) render these; labels via `common.context.*` (en/am/om).
- **Operational gotcha:** new migrations + permissions must be applied to the running DB — `php artisan migrate && php artisan db:seed --class=RolePermissionSeeder --class=GradeLevelSeeder` (seeders are idempotent). Permission-gated nav stays hidden until the new permissions are seeded.

### ADR-009 — Attendance (daily, per section)
- **`attendance_records`**: one row per `(student, section, date)` — `unique(student_id, section_id, date)`. Denormalized `school_id`/`branch_id`/`academic_year_id` for scoped reporting; `recorded_by` → users. v1 ships **daily (homeroom) mode**; per-period can layer on later via a nullable period/teaching_assignment FK.
- **`date` is intentionally NOT cast** to a date object on the model — it is a pure calendar day matched by exact equality and as part of the unique key + `updateOrCreate`. Casting to `'date'` stores `Y-m-d H:i:s` and breaks equality lookups (sqlite especially). Keep DATE columns used as keys uncast (or use `whereDate`).
- **`SaveAttendanceAction`** bulk-upserts a section's marks for a date (idempotent), recording only students with an **active enrollment** in that section; derives `academic_year_id` from those enrollments. `AttendanceStatus` enum: present/absent/late/excused.
- Endpoints: `GET sections/{section}/attendance?date=` (roster of enrolled students + their mark, null when not taken) and `POST sections/{section}/attendance` (bulk save). Authorized inline by `attendance.view`/`attendance.record` × `operatesInBranch(section.branch)` (no model policy — keyed off the section). Teacher/Director/Registrar/Principal/SchoolAdmin record; Support views.
- Frontend `/attendance` (i18n domain `attendance`, nav section `operations`): section + date pickers → roster where everyone defaults to **present** (mark exceptions), segmented status buttons, "Mark all present", bulk Save. Uses local `useState` (bulk roster grid, not a field form).

### ADR-010 — Authorization kernel (single source of role truth)
The dual system (global Spatie role assignment + scoped memberships) caused systematic cross-tenant leaks (`can('x') && operatesInBranch()` allowed a director at School A to act at School B where they held a weaker role). Replaced by ONE kernel in `App\Models\User` + `App\Support\Authorization\PermissionCatalog`:

- Spatie tables hold ONLY the role → permission catalog (seeded); `model_has_roles`/`model_has_permissions` stay **empty**. `assignRole`/`syncRoles`/`hasPermissionTo`/`hasRole` must never be called on users.
- Effective authority = `allowedTo(permission, schoolId, branchId)`: union of catalog permissions for the ACTIVE memberships applying to that scope (platform → everywhere; school → its school incl. branches; branch → exact branch). **Deny-by-default, no global fallback** — context-less requests grant school/branch roles nothing.
- `SetActiveContext` VALIDATES the full header pair: the branch must belong to the school and be one the user can operate in; anything invalid resolves to null (forged headers can only reduce access).
- `hasPlatformPermission()` for platform-only capabilities; `rolePermissionsMap()`/`permissions` in `/auth/me` are derived from memberships so the frontend context-narrowing keeps working unchanged.
- Relationship roles (`student`/`parent`/`tutor`/`vendor`) have `RoleScope::Relationship` and are **never assignable** as memberships.
- Contract enforced end-to-end by `tests/Feature/CrossTenantIsolationTest.php` — every new endpoint must keep it green.

### ADR-011 — Global persons, scoped engagements, term lifecycle, ownership
- **`users` and `students` are global persons.** `users` lost its `school_id`/`branch_id` "home" columns; `students.school_id/branch_id` became nullable REGISTRATION PROVENANCE (B2C students have neither; transfers don't duplicate identity). Tenancy lives on engagement rows: `memberships`, `employees` (now `user_id` required), `student_enrollments`. Staff-lane authority over a student is judged across `Student::adminScopes()` (provenance + enrollment branches).
- **`school_programs`** (branch-scoped; default `Regular` auto-created per branch) + `student_enrollments.school_program_id` with a partial unique on ACTIVE (student, year, program) → dual enrollment works, duplicates within a program are blocked.
- **`student_promotions`** table (schema-ready) records year-end decisions immutably.
- **Terms carry `status` (`planned/active/closed`)**; `App\Support\TermGate::assertWritable()` is the single write-gate for term-anchored mutations (attendance, assessments, results, timetable, subject assignments). `attendance_records.term_id` anchors attendance to its term (derived from the date).
- **`subject_assignments`** now carry denormalized `school_id`/`branch_id`/`academic_year_id` + `is_active`; partial uniques allow team teaching (one row per teacher per section×subject×term + at most one unassigned placeholder). Teacher OWNERSHIP: `grades.manage_own` / `attendance.record_own` (teachers, own assignments / homeroom-or-taught sections only) vs `grades.manage` / `attendance.record` (supervisory).
- Cross-aggregate FKs are `restrictOnDelete` (no silent cascade wipes); `memberships` uniqueness is enforced with NULL-safe partial unique indexes per scope shape.
- Deliberate deferrals: `section_enrollments` (year-scoped homeroom/capacity — introduce with the first year-rollover feature), `media`/`notifications`/`school_settings` tables (branches carry a `settings` JSON column for config like `attendance_mode` until a consumer exists).

### ADR-012 — Relationship access lane (/me)
Parents/students/tutors get access via RELATIONSHIPS, never grants. Separate namespace `/api/v1/me/*` (`MeController`) so self queries never share a code path with staff queries: `GET me/children` (guardian links + per-link `can_view_grades`/`can_view_attendance`/`can_pay_fees` flags honored per endpoint), `me/children/{student}/result-card|attendance-summary|invoices`, `me/student`, `me/student/result-card`. Report computation shared with the staff lane via `App\Services\Reports\StudentReportService`. `AddGuardianAction` no longer assigns any role — `ParentProfile` + `student_guardians` ARE the record; `UserResource` exposes `is_parent`/`is_student` flags.

### ADR-013 — Student & guardian enrichment (public ids, directory, health, waivers, preferences, /me UI)
- **Public person codes.** `users.public_id` + `students.public_id` (char(6), unique, alphabet `A-Z2-9` minus `0/O/1/I` via `App\Support\PublicId`), assigned in model `creating` hooks (seeders run `WithoutModelEvents` and must backfill). Stored uppercase; search always uppercases input. These are the ONLY person identifiers ever shown publicly — never DB ids. Cross-school parent lookup: `GET guardians/search` (requires `guardians.manage` in the active context; returns name + public id + MASKED phone + children count only).
- **School directory.** Platform-wide `school_directory` catalog (name/region/zone/city, `school_id` set + auto-synced for Temari-hosted schools via `CreateSchoolAction`/`SchoolController@update`, `is_verified`, `created_by_school_id` provenance; seeded starter list). `student_enrollments.previous_school_id` FK's into it. Inline unverified adds by staff with `students.create`; verify/edit/delete is platform-only (`platform.access`). One async-searchable combobox in the UI (`components/ui/async-combobox.tsx`) — never two pickers.
- **Student profile.** Students gained: `citizenship`, `email`, `marital_status`, `photo_path` (avatar pattern, `POST students/{id}/photo`), `languages` (jsonb codes validated against `App\Support\Languages`, default `["am"]`), `blood_type`, `health_notes`, birthplace (`birth_country/state/city/sub_city/woreda`) + current address (employee 6-field convention). `student_attachments` + `parent_attachments` clone the employee_attachments pattern (private R2, signed URLs). Parents gained the patronymic trio + address + `photo_path` (name trio syncs `users.name`).
- **Health conditions.** Platform seed catalog `health_conditions` (categorised) + `student_health_conditions` pivot (severity/notes/medication). SENSITIVE: serialized only on the student detail endpoint (`whenLoaded`), never in lists. A dedicated `students.view_health` permission is the planned v2 hardening.
- **Enrollment.** `student_enrollments.section_id` is now NULLABLE — registration takes year + grade, sections are assigned later; with a section the grade still derives from it (capacity + branch integrity enforced). `EnrollStudentAction` derives school/branch from the year when no section is given.
- **Fees at registration + scholarships.** Invoices gained `discount_type` (`none/percentage/fixed/full_waiver`), `discount_value`, `waiver_reason` and a `Waived` status; `Invoice::netAmount()` is the payable truth (payments/balances judge against it — `RecordPaymentAction`, `ApplyInvoiceDiscountAction`, `POST invoices/{id}/discount` gated by `fees.manage`). A scholarship is a full waiver WITH a reason — never a student boolean, never a deleted invoice. Registration accepts `fee_structure_ids` (validated against year + grade pivot; `GET fee-structures/applicable`), `pay_now` rows (inline `RecordPaymentAction`) and `waivers` rows. `GenerateInvoicesAction::executeForEnrollment()` is the per-student path (bulk `execute()` unchanged).
- **Notification preferences live on users** (`notify_via_sms/email/push`, defaults true; `preferred_language` pre-existing) — moved OFF `parents` (channel prefs belong to the person; per-child SMS gating stays on `student_guardians.can_receive_sms`). `GET/PUT me/preferences` for every account; the frontend locale switcher persists `preferred_language`.
- **Registration comms.** `App\Services\RegistrationNotifier` (all sends deferred `DB::afterCommit`; failures logged, never thrown): new guardian/student account → password-setup SMS with registration context; existing account → contextual notice (gated by `notify_via_sms` + link `can_receive_sms`); parallel plain-text `ChildRegisteredMail` when email + `notify_via_email`. Localized per recipient via `lang/{en,am,om}/registration.php` (first `lang/` usage in the repo). Student logins are an EXPLICIT registrar choice (`create_user_account`) — never automatic (primary_phone is usually a parent's).
- **Frontend.** Registration = full-page 6-step wizard (`app/(app)/students/new`, per-step zod validation, DraftFile staging uploaded after save, `savedId` retry guard); student detail = tabs (overview/guardians/documents/health/fees); students list = server-driven (`useServerTable` + `/students/export`); guardian add = search-existing-first. `/me` lane UI: `me/children` (child switcher — own provider pattern, NOT school-context), `me/student`, `/settings`; pure relationship-hat users (no active memberships) land on their `/me` surface from `/dashboard`; nav/docs gate these on `user.is_parent/is_student` only (`relationship` flags in nav-config + docs content).

### ADR-014 — Parents register, check.et payment verification, switcher hats
- **Parents register (staff).** `GET parents[/export]/{parent}` (`ParentController` + `ParentResource`): visible parents = guardians of a student the active context administers (guardianship → student → provenance-or-enrollment scope). List gate `guardians.view` via context; `show` loops `ParentProfile::adminScopes()`. Frontend `/parents` (nav under People) — server table with `PersonAvatar` (shared photo-or-initials cell, `components/ui/person-avatar.tsx`), row click opens a profile sheet (children links, photo upload, attachments manage). Guardian photo/files also editable in `GuardianSheet` (staged, uploaded post-save with the returned `parent_id`).
- **Payment verification (parents, check.et).** `payment_verifications` table = every proof submission + full provider `response` snapshot; statuses `verified` (auto-`RecordPaymentAction`, `payment_id` linked, method mapped telebirr/cbe_birr/bank_transfer, receiver bank account snapshotted) / `failed` (final: not found, not completed, duplicate receipt) / `needs_review` (amount > balance, receiver account not the school's, provider unavailable — finance resolves with the normal record/waive tools via `GET invoices/{invoice}/verifications`). `App\Services\CheckEt\{CheckEtClient,HttpCheckEtClient,CheckEtResult}` (bound in AppServiceProvider; tests bind a fake) — POST `{base}/verify`, Bearer `CHECK_ET_API_KEY` (env only), inputs bank+transaction_number (+receiver `account_number` when the claimed bank pins down exactly one school collection account), receipt_url, or receipt_file (uploaded copy kept privately at `payment-receipts/{invoice}`). Bank-code mapping lives on the interface (`SUPPORTED_BANKS`, `LOCAL_CODE_MAP`: our `siinqee` → their `sinqee`). Parent lane: `POST me/children/{student}/invoices/{invoice}/verify-payment` gated by the link's `can_pay_fees`; UI `app/(app)/me/payments` (child tabs + per-invoice verify sheet: reference / link / file). Receiver-account matching tolerates masked numbers (4-digit tail).
- **Switcher hats.** Relationship hats live ONLY in the workspace switcher UI (`workspace-switcher.tsx`): "My family"/"My learning" entries navigate to the `/me` lane and NEVER touch the stored school/branch context (a child is not a permission context). Per-child switching = `ChildTabs` + `useChildren` (`components/me/child-tabs.tsx`, selection persisted in `temari_active_child`), shared by `/me/children` and `/me/payments`. Rationale vs. competitors: PowerSchool forces separate accounts across districts, ClassDojo separate teacher/parent accounts — Temari's global person + memberships/guardian-links model needs neither.
- **UX invariants added.** Sheets: forms inside `ResponsiveSheetContent` must be `flex min-h-0 flex-1 flex-col` (never `h-full`) so the footer stays pinned. Wizards: never flip one button between `type=button`/`type=submit` across steps — keyed remount + a step guard in onSubmit. Theme defaults to LIGHT (`defaultTheme="light"`, system still selectable; toggle also in Settings).

### ADR-015 — Grading module (scales, policies, grade books, marklists, report cards, transcripts)
- **Letters are display, never storage.** Marks are stored numeric everywhere; `grading_scales` + `grading_scale_bands` (platform-seeded `et-percentage`/`et-letter`/`et-early-grade` via `App\Support\GradingDefaults::provision()` — idempotent, self-provisioned by the resolver so tests/new DBs never break — plus school-custom rows, LeaveType catalog pattern) map a 0–100 score to letter/label/GPA/pass. Bands never overlap (validated). Delete → deactivate when referenced by a policy.
- **`grading_policies`** decide which scale + display (`numeric|letter|both`) applies per grade window (`min/max_grade_sort`, subjects-style): school-wide row (branch_id null) is the default, branch row overrides — resolved by `App\Services\GradingPolicyResolver` (branch → school → platform percentage/numeric fallback). Windows can't overlap within one scope. This is how one branch letters KG while another stays numeric.
- **`grade_books` + `grade_book_items`** = the principal/director-defined assessment plan per branch+term+grade window (optional single subject; subject-specific beats general). Item weights must sum to EXACTLY 100. `App\Services\GradeBookMaterializer` lazily materialises items into `assessments` rows (unique `subject_assignment_id × grade_book_item_id`, re-synced on every marklist open so the plan stays authoritative). Where a grade book governs, NOBODY adds/edits/deletes assessments on the assignment (fix the plan instead); item removal is refused once marks exist. Free-form gradebooks (no plan) keep teacher-defined assessments with a Σweight ≤ 100 guard, and scores are validated ≤ max_score.
- **`marklists`** (one per subject assignment, lazily created) digitise the teacher-signs/director-countersigns ritual: `draft → submitted → approved` (`grades.approve`, granted to principal/school_admin/director). Any non-draft marklist is READ-ONLY for marks + structure; reopen (approver, or the submitter while still submitted) returns it to draft. `AssessmentController` enforces the lock on every mutation. New permission `grades.approve`; registrar gained `grades.view`+`reports.view`.
- **Freeze enrichment.** `ComputeTermResultsAction` resolves the grading policy per enrollment grade and SNAPSHOTS bands into `student_term_results`: per-subject `letter/band_label/is_passing` inside `breakdown`, plus a `grading` jsonb (`scale`, `display`, `overall` band). `absence_days` derives from attendance_records (term-anchored or date-window). `conduct` (ሥነ ምግባር) + homeroom `comment` are staff-entered (`POST terms/{term}/conduct`, grades.manage or the year's homeroom teacher via section_homerooms) and survive recomputes. Editing a scale later never rewrites issued results.
- **Documents read ONLY frozen rows.** `StudentReportService::reportCard()` (official card: snapshot + conduct + absences + rank) and `::transcript()` (multi-year, grouped per year with annual average). Staff: `reports/students/{id}/report-card|transcript`; relationship lane: `me/children/{student}/report-card|transcript` (gated `can_view_grades`) + `me/student/...`. Frontend print pages live at `app/print/report-card/[student]` + `app/print/transcript/[student]` (outside the shell, `print:hidden` chrome, transfer-letter pattern).
- **Frontend.** `/academic/grading` (scales cards + policy rules), `/academic/grade-books` (builder sheet, live weight total), `/marklists` (register: teacher sees own, supervisors see all + status filter = approval queue) → `/marklists/[id]` (desktop spreadsheet grid + MOBILE per-assessment entry mode: assessment chips with fill progress, 44px touch rows, sticky save bar; CSV export of the grid), `/academic/report-cards` (term+section picker + BranchScopePicker for school-wide, name search + conduct filter, inline conduct entry — homeroom teachers too, backend-verified —, recompute, print links, pages through ALL frozen rows so export is complete). i18n domain `grading` (en/am/om); docs section `grading` (7 guides). Guard rail: `tests/Feature/GradingModuleTest.php` (16 tests incl. registrar/parent/student/school-wide-principal/homeroom role coverage).
- **Analytics.** `GET terms/{term}/grading-report` (`GradingReportController`, gate grades.view) aggregates the FROZEN rows in one payload: totals (avg, pass rate, absence), grade-band distribution, per-section + per-subject stats (unpacked from breakdown snapshots), marklist submission progress (assignments without a marklist row count as draft) and top-10 students. Frontend `/academic/grading-reports`: stat cards + 4 recharts panels (dynamic-imported), CSV export of the aggregates. `terms/{term}/results` gained student name/public-id `search` + per_page 200.
- **/me results.** `me/children/{student}/report-cards` + `me/student/report-cards` (`StudentReportService::reportCardIndex`) list the frozen cards (term, avg, rank, letter) so parents/students never need term ids; `ResultsCard` (components/me) renders them on `/me/children` (gated per-link `can_view_grades`) and `/me/student`. The print pages (`/print/report-card|transcript/[student]`) resolve lanes with a fallback chain staff → parent → own, so one URL serves every role.

---

*Update this file when new patterns are established or old patterns are deprecated.*

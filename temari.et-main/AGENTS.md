# Temari.et — AI Agent Instructions

> This file is the **primary source of truth** for any AI agent (Codex or otherwise) working on this project.
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
| AI | Google Gemini 1.5 Flash (primary), Codex Haiku 3.5 (secondary) |
| SMS | Primary parent notification channel (provider TBD) |
| Offline | PWA + SQLite + background sync API |

**Backend lives in `./backend/`. Frontend lives in `./frontend/`.**

---

## 2. Non-Negotiable Quality Rules

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
- Never expose other schools' data. Every school-scoped query must include `school_id` from the authenticated context.
- Previous year data: read-only once term closes. Enforce at API level.
- A teacher at School A must have zero visibility into School B's data.
- Fayda IDs stored hashed only.
- All media access through signed Cloudflare R2 URLs, never direct public links for private files.

---

## 3. Database Principles

- **PostgreSQL only** — leverage JSONB, arrays, full-text search, UUID where appropriate.
- **Auto-increment bigint PKs** — no UUIDs as PKs (performance).
- **Multi-tenant via `school_id` FK** — single DB, every school-scoped table has `school_id`.
- **Soft deletes everywhere** — `deleted_at` on all core tables.
- **`term_id` is the universal time anchor** — never mix `semester_id`, `period_id`, `academic_year_id` arbitrarily.
- **No school-scoped grade levels** — `grade_levels` is platform seed data (seeded once).
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

Roles are managed via Spatie RBAC. Role-to-profile mapping:
- `parent` → `parents` table
- `student` → `students` table (may have no `user_id` for young children)
- `teacher` / `school_admin` → `teachers` table + `school_staff` (per school)
- `tutor` → `tutor_profiles`
- `vendor` → `vendors`
- `super_admin` → `users` only

### Multi-school context switching
The UI must provide a **school/role switcher**. When a user is logged in, they select their active school context. All subsequent actions are scoped to that context. UI must show clearly which school/role is active.

### Time-scoped access
- Current term: full read + write
- Previous terms (same school): read-only
- Previous school (no longer employed): read own entries only
- Another school (not employed): zero access

---

## 5. UI/UX Standards

### Design Philosophy
- **Modern, clean, professional** — think Notion meets Linear meets a mobile banking app.
- **Ethiopian users first** — consider low-literacy scenarios, RTL isn't needed (Amharic is LTR), but text must render correctly.
- **Density:** Dashboard = information-dense. Forms = one task at a time, generous spacing.
- **Color:** Use a consistent design system. No random color choices. Define a palette in the Tailwind config.
- **Icons:** Use a single icon library consistently (Lucide preferred).
- **Feedback:** Every action gets visual feedback (loading, success, error). Never leave the user guessing.

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
- **Policy-gated:** every controller action must go through a Laravel Policy
- **No raw SQL** in controllers — use Eloquent + query scopes

---

## 8. Code Style & Conventions

### Backend (Laravel)
- Follow `backend/AGENTS.md` (Laravel Boost guidelines)
- Run `vendor/bin/pint --dirty --format agent` after every PHP change
- Tests with Pest — feature tests > unit tests
- No logic in controllers — use Actions or Services
- Migrations: always reversible (`down()` implemented)
- Never break existing migrations — create new ones

### Frontend (Next.js)
- Follow `frontend/AGENTS.md`
- Read `node_modules/next/dist/docs/` before using Next.js APIs
- Components in `components/`, pages in `app/`
- i18n strings in `lib/i18n/` translation files (one file per language per domain)
- No hardcoded strings in JSX
- Use `dynamic()` for heavy dashboard widgets

---

## 9. What is Being Built (v1 Scope)

1. **School Management System (SMS):** Enrollment, attendance, fees, payroll, timetable, reports
2. **Learning Management System (LMS):** Gradebook, assignments, course materials, results
3. **AI Exam Prep (B2C):** Grade 6/8/12 national exam questions, AI tutor, learning paths

Pillars 4–7 (tutor marketplace, online courses, ecommerce, vendor portal) are **schema-ready** but not built in v1.

---

## 10. Ethiopian Context Specifics

- **Calendar:** Ethiopian (Ge'ez) calendar — 13 months. Handle at application layer. Never assume Gregorian.
- **Academic year:** 2 semesters, starts September (Ethiopian calendar).
- **Payments:** Telebirr (dominant), CBE Birr, bank transfer. Stripe = international only.
- **Grade levels:** Nationally fixed (KG-1, KG-2, Grade 1–12). Seeded at platform level.
- **Naming:** Patronymic — `first_name + father_name + grandfather_name`. No family surnames.
- **SMS:** Primary parent comms. Many parents have no smartphone or reliable internet.
- **Bandwidth:** Design for 3G. Compress assets. Lazy-load everything non-critical.
- **National exams:** Grade 6 (primary completion), Grade 8 (middle school completion), Grade 12 (EUEE/university entrance). **Grade 10 has NO national exam** — never list it as an exam grade.

---

## 11. Monetization Context (don't break this)

- Primary model: **2% of fee payments processed** (no upfront cost to schools)
- B2B tiers: Free (≤50 students) / Pro (10 ETB/student/month) / Enterprise (custom)
- B2C AI Exam Prep: Free (limited) / Pro (199 ETB/month)
- Never expose pricing logic in frontend without going through the subscription/billing service

---

*Last updated: June 2026. Update this file when major decisions change.*

# Temari.et — Platform Knowledge Base
> Claude Project context document. Last updated: June 2026.
>
> **IMPORTANT FOR AI AGENTS:** This document is **reference material only** — not a blueprint.
> Use it to understand the problem domain, business context, Ethiopian education system, and lessons from School-X.
> **Never copy School-X architecture, schema, or patterns directly.** Always design Temari's solutions from first principles, using this knowledge only to inform better decisions.
> The primary instruction file is `CLAUDE.md` at the project root.

---

## 1. Project Overview

### What is Temari?
**Temari.et** is a unified Ethiopian education platform being built from scratch by Abdul (Empire Technological Solutions PLC, Addis Ababa). It replaces and evolves the previous **School-X (school-x.et)** product.

The name **Temari** (ተማሪ) means "student" in Amharic — positioning the platform as student/learner-centric rather than institution-centric.

### What happened to School-X?
School-X.et was the first version. It had 140 tables, ran on MySQL, Laravel backend + Next.js frontend. It worked but was:
- Buggy and slow due to schema design issues
- Unnecessarily bulky (duplicate tables, poorly named concepts)
- Too school-scoped (grades re-created per school, etc.)
- Missing 4–5 major new pillars needed for the vision

**Temari is a full rewrite.** Nothing is copied from School-X. The logic is redesigned from first principles.

### Company & Legal Context
- **Owner:** Abdul (majority owner)
- **General Manager:** Abdul's friend and fellow shareholder (met June 2026 to review old software and plan Temari direction)
- **Legal entity:** Empire Technological Solutions PLC (Ethiopian entity)
- **Previous entity:** PostSyncer LLC (Delaware) — separate product, not related
- **Domain:** temari.et
- **Previous domain:** school-x.et (deprecated)

---

## 2. Platform Vision & Pillars

Temari is a **7-pillar platform** — all designed in the schema from day one, but shipped incrementally.

| # | Pillar | Type | Status |
|---|--------|------|--------|
| 1 | School Management System (SMS) | B2B | **v1 — build first** |
| 2 | Learning Management System (LMS) | B2B | **v1 — build first** |
| 3 | AI Tutoring & Exam Prep | B2C | **v1 — build first** |
| 4 | Tutor Marketplace | B2C | v2 |
| 5 | Online Course Creator Platform | B2C | v2 |
| 6 | Ecommerce (parents/students/teachers) | B2C | v3 |
| 7 | Vendor/Supplier Portal (school procurement) | B2B | v3 |

### v1 Launch Focus
The wedge is **SMS + LMS + AI Exam Prep**. Schools subscribe for SMS/LMS. Students use AI exam prep (grade 6, 8, 12 national exams). These are the daily-use hooks before expanding to marketplace and commerce.

### One-Line Pitch
> **"Temari collects your school fees automatically, works without internet, and tells you exactly what to say to your bank. Everything else is free."**

### Competitive Position
No single platform globally combines all 7 pillars. Closest comparables:
- **Teachmint** (India, $118M) — SMS + LMS only, no marketplace/ecommerce/vendor
- **Classplus** (India) — creator/tutor storefront only, no school management
- **D2L Brightspace** (global) — LMS + course commerce, no SMS or marketplace
- **Bank portals (Awash, CBE)** — fee collection only, no academic features, poor UX
- **Excel** — the real dominant competitor in most Ethiopian schools
- **Temari's moat:** Ethiopian payment rails (Telebirr, CBE), Amharic language, low-bandwidth optimization, national exam bank (Grade 6/8/12), offline-first, SMS-primary, bank loan report, and all 7 pillars in one platform

---

## 3. Go-To-Market Strategy

### The Three Hard Objections & How to Beat Them

#### Objection 1 — "We already use our bank's system"
Banks (Awash, CBE, Abyssinia) offer schools basic fee portals tied to loan relationships. Schools use them as lock-in.

**Strategy: Don't fight the bank. Integrate with it.**
- Temari works WITH existing bank accounts — funds still flow to the school's existing account
- Banks don't offer: attendance, gradebook, parent comms, timetable, payroll, AI exam prep, analytics
- Pitch: *"Your bank handles the money. We handle everything else — and we connect to your bank."*
- Technically: Temari generates invoices → parent pays via Telebirr/CBE → funds go to school's existing account → Temari marks invoice paid via webhook/reconciliation

#### Objection 2 — "Our internet is unreliable"
**Strategy: Offline-first is the core product, not a workaround.**
- PWA with service workers — works on 2G, works fully offline
- Teacher takes attendance offline → syncs when connected (even 5 minutes of internet)
- SMS is the PRIMARY parent channel, not app notifications
- Parent with no smartphone still gets: absence alerts, fee reminders, results via SMS
- No other Ethiopian school software does SMS-first

#### Objection 3 — "We're not interested / we already have a system"
**Strategy: Zero migration friction. Parallel-run model.**
- Don't ask schools to commit to a full switch on day one
- Import student list from Excel in 10 minutes
- Month 1–2: Temari runs alongside old system, teachers only do ONE new thing (attendance)
- Month 3–4: Value becomes obvious, gradual migration
- Month 5–6: Old system becomes redundant

### The Revolutionary Angle — School Financial OS
Temari is positioned not as "school software" but as a **financial operating system for Ethiopian schools**:

**Automated fee collection:**
- Auto-generates invoices each semester
- SMS reminders 7 days before due date
- Parent pays via Telebirr in 30 seconds
- Real-time collection dashboard
- Schools currently lose 15–30% of fee revenue to manual tracking errors and forgotten payments

**Bank Loan Report feature (unique differentiator):**
- One-click PDF showing exactly what banks need for school loan assessment
- Total enrolled students, fee collection rate, monthly revenue, outstanding receivables, YoY growth
- No other software in Ethiopia does this
- Turns Temari into a tool that helps schools GET MORE from their bank relationship

**Teacher salary advance via Telebirr:**
- Teacher requests advance in app → admin approves → sent to Telebirr instantly
- Auto-deducted from next payroll with audit trail
- Makes teachers internal advocates for adoption

### Pricing Model — SUPERSEDED (July 2026): no per-transaction fee
> **Decision (Abdul, July 2026):** Temari takes NO cut of school fee payments.
> The earlier "2% of fee payments processed" idea below is DEAD — kept only as
> history. School fee payments happen outside the platform (bank/wallet/cash,
> verified via check.et); no payment gateway sits on school money.

Current model (per the Elon Academy proposal, June 2026):
- **Core platform: 200 ETB/student/year, parent-paid at registration** — no software cost to the school
- **School Plan (optional, school-paid): 10 ETB/student/month** — check.et payment verification, electronic revenue receipts (when live), School AI
- **B2C AI upgrade: 199 ETB/month** (parents/students) — via Chapa or Telebirr gateway (Temari's own subscriptions only)
- **NFC hardware:** card 300 ETB one-time, replacement 500 ETB, one free reader/branch, extra 30,000 ETB
- Tutor marketplace commissions (v2)

<details><summary>Historical (rejected) idea</summary>
- ~~Temari takes 2% of fee payments processed through the platform~~
- ~~Upsell: flat monthly fee for SMS/LMS/payroll once they're hooked~~
</details>

**B2C (AI Exam Prep):**
- Free: limited questions/day, no AI chat
- Pro: 199 ETB/month, unlimited questions, AI tutor, learning paths, certificates

### Distribution — The Cascade Model
```
1 school signs up
      ↓
Parents register (school sends SMS invite)
      ↓
Parents love fee payment + grade visibility
      ↓
Parents mention Temari to parents at other schools
      ↓
Those parents pressure their schools to adopt
      ↓
New school signs up (demand came from parents, not sales)
```

Secondary channels:
- **Teacher network:** Teachers who love Temari move schools and advocate for it
- **Ministry angle:** If 50,000+ students use AI exam prep for EUEE, Ministry notices → potential endorsement

---

## 4. Technical Stack

### Backend
- **Framework:** Laravel (PHP)
- **Database:** PostgreSQL (new — was MySQL in School-X)
- **Auth:** Laravel Sanctum / personal access tokens
- **Permissions:** Spatie Laravel Permission (RBAC)
- **Queue:** Laravel Jobs/Horizon
- **Storage:** Cloudflare R2 / S3-compatible

### Frontend
- **Framework:** Next.js (React)
- **Styling:** Tailwind CSS
- **Architecture:** Monorepo

### Infrastructure
- **Hosting:** Coolify (self-hosted VPS)
- **CDN/Proxy:** Cloudflare
- **Payments:** Telebirr, CBE Birr, Stripe (international)
- **Offline:** PWA with SQLite local storage + background sync API
- **SMS:** Primary parent communication channel (offline fallback)

### AI
- **Primary:** Google Gemini 1.5 Flash (free tier, Amharic support)
- **Secondary:** Claude Haiku 3.5 (for complex reasoning)
- **Constraints:** Must work on low-bandwidth (3G), Android-dominant, mobile-first

---

## 5. Database Architecture Decisions

### Core Principles
1. **PostgreSQL** — better JSON support, full-text search, UUID native, array types
2. **Auto-increment bigint PKs** — simple, fast, Laravel default
3. **Single DB, `school_id` tenancy** — multi-tenant via FK on every school-scoped table (not schema-per-school)
4. **One `users` table** — roles determine context (student, teacher, parent, tutor, vendor, admin, etc.)
5. **Soft deletes everywhere** — `deleted_at` timestamp on all core tables
6. **`term_id` as the universal time anchor** — not a mix of `semester_id`, `period_id`, `academic_year_id` depending on table
7. **Single `name` field** — models have one `name` column. i18n is for UI/interface text only, not DB content. No `name_en`/`name_am`/`name_om` columns.
8. **Audit log** — unified `activity_logs` table, not scattered per-table
9. **Role-to-profile pattern** — every role has a dedicated extended profile table; `users` stays clean for auth only

### Role-to-Profile Map
> **Amended by ADR-010/012**: staff roles are granted via `memberships`; `student`/`parent`/`tutor`/`vendor` are RELATIONSHIP-derived (never assignable) and served via the `/me` lane.
| Role | Auth | Extended Profile | Employment |
|------|------|-----------------|------------|
| `parent` | `users` | `parents` | — |
| `student` | `users` (nullable) | `students` | — |
| `teacher` | `users` | `teachers` | `school_staff` |
| `school_admin` | `users` | `teachers` (nullable) | `school_staff` |
| `tutor` | `users` | `tutor_profiles` | — (self-employed) |
| `vendor` | `users` | `vendors` | — |
| `super_admin` | `users` | — | — |

### What NOT to do (lessons from School-X)
- ❌ Do NOT create `grades` as school-scoped rows — grade levels are platform seed data
- ❌ Do NOT store penalty amounts as `varchar` — use `decimal` with a type enum
- ❌ Do NOT use 7 weekday FK columns on timetable (`monday_class_id`, etc.) — use one row per slot
- ❌ Do NOT split student enrollment across two tables with different time anchors
- ❌ Do NOT create `education_programs`, `education_levels`, `pgdts` as three identical mystery tables
- ❌ Do NOT use `period_id: varchar(20)` as a pseudo-FK
- ❌ Do NOT store JSON blobs for data that needs to be queried (e.g., `each_subject_total JSON`)
- ❌ Do NOT have both `timetables` AND `class_time_tables` for the same concept
- ❌ Do NOT scope sections to academic years — sections are stable identity, year-scoped via `section_enrollments`

---

## 6. Ethiopian Education System Context

### National Curriculum Structure (grade levels — platform seed data)
```
KG-1, KG-2                          → Kindergarten
Grade 1 – Grade 4                   → Lower Primary
Grade 5 – Grade 8                   → Upper Primary (national exams at Grade 6 and Grade 8)
Grade 9 – Grade 10                  → Secondary (no national exam — Grade 10 sits none)
Grade 11 – Grade 12                 → Preparatory (national university entrance at Grade 12)
```
Grade levels are **nationally fixed**. Seed once at platform level. Never school-scoped.

### Academic Calendar
- **2 semesters per year** (Semester 1 and Semester 2)
- Ethiopian calendar (Ge'ez calendar, ~7 years behind Gregorian)
- School year typically starts in September (Ethiopian calendar)
- National exams (Grade 6, 8, 12) administered by Ministry of Education — Grade 10 has no national exam

### Attendance Modes
Schools differ on how attendance is taken:
- **Daily mode** — homeroom teacher takes attendance once per morning
- **Per-period mode** — each subject teacher takes attendance per period
- Stored in `school_settings.attendance_mode`
- A homeroom teacher can manage attendance for multiple sections

### Dual Enrollment (edge case from School-X notes)
A student can be enrolled in different school program types simultaneously:
- Example: Regular program + Winter/evening program
- Handled via multiple `student_enrollments` rows with different `school_program_id`
- Each enrollment is tracked independently

### Naming Convention (Ethiopian)
Ethiopians use **patronymic naming** — not family surnames:
```
Given name + Father's name + Grandfather's name
Example: Abebe Kebede Girma
```
Schema stores: `first_name`, `father_name`, `grandfather_name`, `mother_name` (optional).
Never use `last_name`.

### Payments
- **Telebirr** — dominant mobile money (Ethio Telecom)
- **CBE Birr** — Commercial Bank of Ethiopia mobile wallet
- **Bank transfer** — used by institutions
- **Stripe** — for international/diaspora users only
- All amounts stored in **ETB (Ethiopian Birr)**

### Infrastructure Constraints
- **3G networks dominant** — optimize for low bandwidth
- **Android-dominant** — mobile-first design
- **SMS fallback** — primary channel for parent notifications
- **Offline-capable** — PWA + SQLite for key features

---

## 7. Schema Architecture

### The Core Separation Principle
Every domain separates **permanent identity** from **time-scoped engagement**:

```
students     → WHO they are (permanent)
student_enrollments → WHERE/WHEN they study (yearly)

teachers     → professional identity (permanent)
school_staff → employment at a specific school (per job)
teaching_assignments → what they teach (per term)

parents      → who they are (permanent)
student_guardians → relationship to each child (permanent)
[access to schools derived from child's active enrollment]
```

### Academic Structure (foundation)
```
PLATFORM LEVEL (seeded once)
  grade_levels         id, code, name_en, name_am, sort_order, cycle
  national_subjects    id, code, name_en, name_am, grade_level_id

SCHOOL LEVEL
  schools
    └── school_programs      (regular|evening|distance|special)
         └── academic_years  (e.g., "2016 E.C.")
              └── terms       (Semester 1, Semester 2)

SECTION LEVEL (stable identity — NOT year-scoped)
  sections             id, school_id, grade_level_id, name, name_am
    └── section_enrollments  (year-scoped: homeroom teacher, capacity, is_active)

STUDENT LEVEL
  students             (permanent identity: name, DOB, gender, national_student_id)
    └── student_enrollments  (one row per student per year, links section + term)
         └── student_promotions (year-end decision: promoted|repeated|transferred|graduated)

TEACHING LEVEL
  teachers             (professional profile: qualifications, license, specialization)
    └── school_staff   (employment per school: hired_on, designation, salary)
         └── teaching_assignments (teacher + subject + section + term = atomic work unit)
              ├── timetable_slots
              ├── grade_book_items → student_marks
              ├── course_materials
              └── assignments → assignment_submissions
```

### Branch/Group Structure
> **SUPERSEDED by ADR-001** (see `dev-guidelines.md`): built as a separate `branches` table under identity-only `schools`; the branch is the tenant boundary. The sketch below is School-X-era context only.
```sql
schools
  id
  name_en, name_am
  code (unique)
  main_school_id (nullable FK → schools.id)  -- null = this IS the main school
  is_active
  subscription_plan_id  -- only on main school; branches inherit
  logo

-- Rules:
-- Only 1 level deep. No sub-branches.
-- Subscription lives on main school. Student count = sum across branches.
-- Staff belong to one specific branch (school_users.school_id)
-- Sections, enrollments, timetables, fees all scoped to specific school_id
```

Branch admin roles:
> **SUPERSEDED by ADR-002/ADR-010**: implemented as `memberships` — the sole record of roles, powering the authorization kernel.
```sql
school_users
  id, user_id, school_id, role_id
  scope (group|branch)  -- group admin sees all branches; branch admin sees only theirs
  is_active, joined_at
```

School setup rules (from School-X review):
- Principal (Academic Manager) creates branches, must link a bank account to each
- Each branch has its own unique school code
- Bank accounts: branches can use different bank accounts; one school can have multiple
- Director creates academic years, adds fees, sets up schedule
- Principal must add a bank account BEFORE Director can create an academic year

### User & Identity Model
> **SUPERSEDED by ADR-002/ADR-011**: `users` has a single `name` (patronymic fields live on profile tables), phone-first login, and NO school/branch columns — users are global persons; tenancy lives on memberships/enrollments/employments.
```sql
users
  id
  first_name, father_name, grandfather_name, mother_name
  first_name_am, father_name_am, grandfather_name_am
  email, phone_number, password
  preferred_language (en|am)
  avatar, is_active
  email_verified_at, phone_verified_at
  created_at, updated_at, deleted_at
```

### Students
```sql
students
  id
  user_id (nullable)        -- young children may not have a login
  first_name, father_name, grandfather_name, mother_name
  first_name_am, ...
  date_of_birth, gender
  national_student_id       -- MoE issued
  fayda_id (hashed)
  primary_phone             -- usually parent's phone
  created_at, updated_at, deleted_at

student_enrollments
  id
  student_id, school_id, academic_year_id, term_id
  grade_level_id, section_id
  school_program_id         -- supports dual enrollment (regular + evening simultaneously)
  status (active|promoted|repeated|transferred_out|withdrawn|graduated)
  previous_school_id (nullable)
  transfer_document_media_id (nullable)
  enrolled_on, exited_on
  UNIQUE (student_id, academic_year_id, school_program_id) WHERE status = 'active'

student_promotions
  id
  student_id
  from_enrollment_id, to_enrollment_id (nullable)
  from_grade_level_id, to_grade_level_id
  from_school_id, to_school_id
  decision (promoted|repeated|transferred|graduated|withdrawn)
  decided_by, decided_at, notes
```

### Parents
```sql
-- parents is a dedicated profile table, NOT scoped to any school
-- Access to a school is DERIVED from child's active enrollment

parents
  id
  user_id (unique FK → users.id)
  gender, date_of_birth, nationality
  occupation, employer (nullable)
  primary_phone, secondary_phone (nullable)
  address_id (nullable)
  is_verified, verified_at
  notify_via_sms (default true)   -- Ethiopian parents prefer SMS
  notify_via_push (default true)
  notify_via_email (default false)
  profile_completed_at (nullable)
  created_at, updated_at, deleted_at

student_guardians
  id
  student_id FK → students.id
  parent_id  FK → parents.id     -- points to parents table, not users
  relationship (father|mother|grandfather|grandmother|uncle|aunt|sibling|legal_guardian|other)
  relationship_am
  can_view_grades, can_view_attendance, can_pay_fees
  can_receive_sms, can_receive_push
  can_authorize_pickup
  is_primary, emergency_contact
  priority_order
  is_active, notes
  UNIQUE (student_id, parent_id)

guardian_invitations
  id
  student_id, invited_by
  phone_number, relationship
  token (unique), status (pending|accepted|expired)
  expires_at, accepted_at
```

### Teachers
```sql
-- teachers = professional identity (permanent, not school-scoped)
-- school_staff = employment at a specific school
-- teaching_assignments = what they actually teach (per term)

teachers
  id
  user_id (unique FK → users.id)
  gender, date_of_birth, nationality
  highest_qualification (certificate|diploma|degree|masters|phd)
  field_of_study, graduation_year
  teaching_license_no, license_issued_at, license_expires_at
  license_media_id
  primary_subject_ids (int[])    -- PostgreSQL array, national_subject ids
  grade_level_ids (int[])        -- grade levels qualified to teach
  years_of_experience
  is_verified, verified_at
  notify_via_sms, notify_via_push, notify_via_email
  profile_completed_at

teacher_certificates
  id, teacher_id
  type (degree|teaching_license|training|award|other)
  title, issued_by, issued_at, expires_at
  media_id, is_verified

school_staff
  id
  user_id FK → users.id
  school_id FK → schools.id
  teacher_id FK → teachers.id (nullable — non-teaching staff won't have this)
  staff_number, designation_id
  employment_type (permanent|contract|part_time|volunteer|substitute)
  hired_on, contract_ends_on (nullable)
  exited_on (nullable), exit_reason (nullable)
  salary_level_id, is_active
  UNIQUE (user_id, school_id) WHERE is_active = true

teaching_assignments
  id
  school_id, teacher_id (user_id), school_subject_id
  section_id, term_id, academic_year_id
  periods_per_week
  is_active
  UNIQUE (teacher_id, section_id, school_subject_id, term_id)

teaching_assignment_substitutes
  id
  teaching_assignment_id    -- the original teacher's assignment
  substitute_teacher_id     -- temp teacher (no new assignment row)
  from_date, to_date
  reason (sick_leave|emergency|training)
  approved_by
```

### Teaching Assignment Rules (CRITICAL)
`teaching_assignment` is the **atomic unit** of teaching. It represents exactly:
- ONE teacher
- ONE subject
- ONE section
- ONE term

Everything hangs off this row. New term = new rows, even if same teacher/subject/section.

| Scenario | Result |
|----------|--------|
| Same teacher, same subject, 3 sections, same term | 3 assignment rows, 3 separate gradebooks |
| Same teacher, 2 subjects, same section, same term | 2 assignment rows, 2 gradebooks, 1 shared attendance |
| Same teacher, same section, Sem1 then Sem2 | 2 assignment rows; Sem1 closes → read only |
| Teacher replaced mid-year | 2 assignment rows (one per teacher per term); each owns their term |
| Substitute teacher (temporary) | `teaching_assignment_substitutes` row only; no new assignment |
| Same teacher, 2 schools, same term | 2 school contexts, fully isolated; school switcher in UI |

### Access Rules Across Time
| Data | Access |
|------|--------|
| Current term | Full read + write |
| Previous term (same school) | Read only |
| Previous school (teacher's own gradebook) | Read only, own entries only |
| Another school (not employed there) | Zero access |
| Student who transferred out | Teacher can see marks they entered; cannot see current school/info |

---

## 8. Exam & Gradebook Logic (from School-X review)

### Who Creates What
- **Exams:** Created by the Director only
- **Exam questions:** Written/contributed by both teachers AND the Director
- **Semester:** Created by the Director (auto-assigned to current academic year)
- **Section-to-teacher assignments:** Done by the Director

### Exam Types Supported
- Multiple Choice (MCQ)
- True/False
- Short Answer

### Grading System
- Standard and common across the entire platform (not school-defined)
- Ministry of Education grading scale applies

### Results Calculation
```
student_marks (raw scores per assessment)
      ↓
subject_results (per subject per term)
  Sem1 weight + Sem2 weight = final (usually 50/50, configurable per school)
      ↓
student_results (annual: average, rank, promotion decision)
```

### Gradebook UI Principles (from School-X cleanup notes)
- Remove broad "Category" label — replace with clean UI showing Name, Subject, Section, Grade level
- Drag-and-drop reordering of assessments
- At-risk students flagged visually (below passing threshold)
- Term switcher clearly shows which term is active vs. read-only

### Reports Required
- **Roster Report** — semester or quarterly reporting
- **Average Semester Report** — yearly and cumulative roster reports
- **Mark List Report** — per individual subject
- **Certificates** — each school prints corrected versions independently if errors found

---

## 9. Shared Foundation Tables

```sql
-- Payments (cross-pillar)
payment_methods     id, name, code (telebirr|cbe|stripe|bank), logo, is_active
payments            id, payer_id, amount, currency(ETB), method_id,
                    payable_type, payable_id (polymorphic)
                    status(pending|completed|failed|refunded), reference_no,
                    gateway_response JSON, paid_at

wallets             id, owner_id, owner_type (user|school|vendor), balance, currency
wallet_transactions id, wallet_id, type(credit|debit), amount, description,
                    reference_type, reference_id, created_at

-- Notifications (cross-pillar)
notifications       id, user_id, type, title_en, title_am, body_en, body_am,
                    channel(push|sms|email|in_app), data JSON,
                    read_at, sent_at, created_at

-- Ethiopian address hierarchy
states → zones → woredas → kebeles
addresses           polymorphic (addressable_type, addressable_id)

-- Audit
activity_logs       id, causer_id, causer_type, event, subject_type, subject_id,
                    old_values JSON, new_values JSON, ip_address, created_at

-- Settings
school_settings     school_id, attendance_mode (daily|per_period), ...
feature_flags       id, key, is_enabled, school_id (null=platform-wide), config JSON

-- Media
media               id, owner_id, owner_type, disk, path, mime_type,
                    size_bytes, original_name, created_at
```

---

## 10. Pillar-by-Pillar Schema Overview

### Pillar 1: School Management System (SMS)
```sql
schools, school_profiles, school_contacts
school_programs, academic_years, terms
school_staff, designations, salary_levels
leave_types, leave_requests
payroll_periods, payroll_entries
attendance_types, student_attendances, staff_attendances
fee_categories, fees, fee_penalties, invoices
school_bank_accounts, school_wallets, withdrawal_requests
announcements, school_events
id_card_templates, certificate_layouts
```

### Pillar 2: Learning Management System (LMS)
```sql
grade_levels, national_subjects, school_subjects, grade_subjects
sections, section_enrollments
student_enrollments
teaching_assignments, teaching_assignment_substitutes
period_slots, timetable_slots
lesson_plans, course_materials
assignments, assignment_submissions
grade_book_items, student_marks
student_results, subject_results
transcripts
```

### Pillar 3: AI Tutoring & Exam Prep
```sql
questions (bank_type: national|school|ai_generated)
question_choices, question_tags
exam_sets, exam_questions
exam_sessions, exam_answers
ai_sessions, ai_messages
learning_paths, learning_path_items
user_subject_stats
```

### Pillars 4–7 (v2/v3)
- Tutor Marketplace: `tutor_profiles`, `tutor_availability`, `booking_requests`, `tutor_contracts`, `tutor_sessions`, `tutor_reviews`, `tutor_earnings`
- Course Platform: `courses`, `course_sections`, `course_lessons`, `enrollments`, `lesson_progress`, `certificates`
- Ecommerce: `products`, `product_variants`, `carts`, `orders`, `order_items`, `coupons`
- Vendor Portal: `vendors`, `rfq_requests`, `rfq_quotations`, `purchase_orders`

---

## 11. School-X Legacy Notes (Do Not Repeat These Mistakes)

### From the June 2026 GM Meeting Review

These are specific bugs and anti-patterns identified in School-X:

| # | Issue | Old (wrong) | New (correct) |
|---|-------|-------------|---------------|
| 1 | Grades school-scoped | `grades: school_id` recreated per school | `grade_levels` seeded once at platform level |
| 2 | Timetable weekday columns | `monday_class_id`, `tuesday_class_id`... | One row per slot in `timetable_slots` |
| 3 | Dual timetable tables | `timetables` + `class_time_tables` (same concept) | Single `timetable_slots` table |
| 4 | Enrollment time anchor split | `school_students` used `academic_year_id`; `section_student` used `semester_id` | Single `student_enrollments` with both |
| 5 | Three mystery lookup tables | `education_programs`, `education_levels`, `pgdts` (identical shape, unclear purpose) | Single `school_programs` with type enum |
| 6 | Penalty as varchar | `fees.penality_amount VARCHAR(255)` | `fee_penalties` table with `decimal` amount |
| 7 | Sections bleed across years | `section_student` had no `academic_year_id` | `section_enrollments` is year-scoped |
| 8 | JSON blob for subject marks | `each_subject_total JSON` | `subject_results` table (one row per subject per student per term) |
| 9 | Grade-subject at wrong granularity | Linked grade + subject + section (10 sections × 8 subjects = 80 rows) | `grade_subjects` links grade + subject only |
| 10 | Two overlapping period tables | `periods` + `period_schedules` | Single `period_slots` |
| 11 | Invoice missing academic_year | Must join invoices → fees → academic_years to find year | `invoices.academic_year_id` direct FK |
| 12 | `period_id` as varchar pseudo-FK | `period_id: varchar(20)` | Proper FK with int |

### School-X Business Logic Worth Keeping
- User account auto-created when school profile is created
- Principal creates branches; each branch has unique code + Google Maps location
- Branches treated as separate entities with own bank accounts
- Principal must add bank account before Director can create academic year
- Student status defaults to "Pending" until payment confirmed or manually approved
- Homeroom teacher can manage attendance for multiple sections
- Dual enrollment supported (student in regular + winter program simultaneously)

---

## 12. Fayda (Ethiopian National Digital ID) Integration

- **Fayda** is Ethiopia's national digital identity system (similar to India's Aadhaar)
- Used for: student enrollment verification, staff identity, age verification
- **API:** PKI-based, requires mTLS (mutual TLS)
- **Status:** PKI certificate generation was in progress at School-X; Temari must complete

```sql
fayda_verifications
  id, user_id, fayda_id (hashed), verification_status,
  verified_at, raw_response JSON (encrypted),
  certificate_fingerprint, created_at
```

---

## 13. Subscription & Monetization Model

### Primary Model — Subscriptions, NEVER a cut of school money (updated July 2026)
- **Temari takes NO percentage of school fee payments.** Fee payments are made
  directly to the school's own accounts and only VERIFIED via check.et.
  No payment gateway handles school fees (if one is ever added it still takes no cut).
- **Core platform: 200 ETB/student/year, parent-paid at registration** — the school
  carries no cost for the core software
- **School Plan: 10 ETB/student/month, school-paid, optional** — unlocks automated
  payment verification, electronic revenue receipts (Ministry of Revenue, when live)
  and School AI; core features are never paywalled
- **NFC hardware:** ID card 300 ETB one-time lifetime (parent-paid for students),
  replacement 500 ETB, one free attendance reader per branch, extra 30,000 ETB
- Chapa/Telebirr gateways exist ONLY for Temari's own subscription collection
  (parent AI plan etc.), never for school fees

### B2C Student Tiers (AI Exam Prep)
- **Free:** limited questions/day, no AI chat
- **Pro:** 199 ETB/month — unlimited questions, AI tutor, learning paths, certificates

### Marketplace/Platform Fees (v2/v3)
- Tutor marketplace: 15–20% commission per session
- Online courses: 30% platform / 70% creator
- Ecommerce: 5–10% commission per order
- Vendor portal: 3–5% on PO value

---

## 14. What Has Been Decided vs What Is Open

### Decided ✅
- PostgreSQL (not MySQL)
- Auto-increment bigint PKs
- Single DB, school_id tenancy
- One users table + RBAC roles (Spatie)
- Role-to-profile pattern (parents table, teachers table, etc. — not just users)
- Laravel backend + Next.js frontend
- Temari.et as the brand (not School-X)
- grade_levels seeded at platform level (not school-scoped)
- Sections are stable identity; section_enrollments is year-scoped
- One student_enrollments table (not school_students + section_student)
- student_promotions as audit trail for year-end decisions
- parents as dedicated table (not school-scoped, access derived from child's enrollment)
- teachers as dedicated professional profile table
- teaching_assignment as the atomic work unit (teacher + subject + section + term)
- One timetable_slots table, row-per-slot (not weekday FK columns)
- term_id as universal time anchor + academic_year_id on teaching_assignments for easy querying
- fee_penalties as proper table (not varchar)
- subject_results normalized (not JSON blob)
- Telebirr + CBE + Stripe as payment methods
- Offline-first PWA with SQLite + background sync
- SMS as primary parent communication channel
- "Pay from what we collect" as adoption pricing model
- Bank Loan Report as key differentiator feature
- Teacher salary advance via Telebirr as internal advocacy feature
- Parallel-run onboarding model (no forced migration)
- All 7 pillars in schema from day one; v1 ships SMS + LMS + AI exam prep
- Ethiopian naming convention (first_name + father_name + grandfather_name)
- Attendance modes: daily or per-period (school setting)
- Dual enrollment supported (student can be in multiple school programs simultaneously)
- Substitute teachers via teaching_assignment_substitutes (not new assignment rows)
- Previous term data is read-only once term closes

### Open / To Decide 🔲
- Ethiopian calendar: native DB support vs. application layer handling
- Video hosting strategy (self-hosted vs YouTube vs Mux vs Cloudflare Stream)
- Offline/PWA scope — which features work offline vs. require connection
- AI-generated questions: stored in main `questions` table (bank_type=ai_generated) vs. shadow table
- Fayda API implementation (PKI cert status TBD)
- Multi-language full-text search for Amharic in PostgreSQL (pg_trgm vs custom tokenizer)
- Course enrollment expiry policy (lifetime vs. time-limited)
- Exact commission percentages for marketplace, courses, ecommerce, vendor portal (school fee payments stay commission-free — decided July 2026)
- Google Maps integration for school branch locations (from School-X notes — deferred)
- SMS provider selection for Ethiopia

---

*End of knowledge base. Update this document when major architectural decisions are made.*
*Last session: June 2026 — GM meeting review of School-X, go-to-market strategy finalized, parent/teacher/student architecture designed.*

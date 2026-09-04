# ADR-016 — LMS: Course Materials, Assignments, Online Exams & the National Exam Prep Lane

Status: **accepted** (July 2026, approved by Abdul)

## Decision summary

Temari's LMS is **two lanes over one shared engine** — never two parallel systems:

1. **School lane** (Google-Classroom-shaped): teachers post materials, assignments and
   quizzes/exams to their classes. The class container IS the existing
   `subject_assignments` row (teacher × subject × section × term) — no separate
   "course shell" bureaucracy.
2. **Platform lane** (Khan-shaped): Temari.et publishes national past-paper banks and
   open mock exams that ANY registered user can take — including B2C users with no
   school. Managed by platform staff through the Catalog Studio pattern.

The shared engine: **question banks → questions → quizzes → attempts → answers**, plus
**assignments → submissions** and **course materials**. One grading pipeline, one exam
player, one autosave protocol.

## Key decisions (all confirmed)

### 1. Gradebook integration — the differentiator
A quiz or assignment that "counts toward grade" links to an `assessments` row
(`assessments.quiz_id` / `assessments.assignment_id` back-references live on the LMS
side: `quizzes.assessment_id`, `assignments.assessment_id`). When grading completes,
`assessment_results` are filled by `App\Services\Lms\GradebookSync` → marklists → frozen
term results → report cards. **No double entry.** The link may materialise a planned CA
slot (grade-book plan) exactly like marklists do, or create a free-form assessment where
the branch allows teacher-defined structure. TermGate applies to every linked write.
Scores are rescaled: `assessment score = (earned / quiz total) × assessments.max_score`.

### 2. Question bank
- `question_banks`: scope = platform (`school_id` null) or school/branch owned.
  Subject + grade window (`grade_levels.sort_order` terms), like every catalog.
- `questions.type`: `mcq_single`, `mcq_multi`, `true_false`, `short_answer`,
  `numeric`, `fill_blank`, `matching`, `essay`.
- `questions.body` JSONB carries the stem/options/pairs; `questions.answer_key` JSONB
  carries correct answers + tolerances + keyword rubric. **`answer_key` never leaves the
  server** — resources strip it for takers; graders see it.
- Per-row `language` (`en`/`am`/`om`) — questions are content, not UI strings.
- `source` marks provenance (`national:2016:g12:natural`); national bank rows are
  platform-owned, deactivate-not-delete once referenced.

### 3. Quizzes (quiz / exam / mock, one table)
- Anchor: `subject_assignment_id` (class quiz/exam) XOR platform scope
  (`is_platform` + grade_level/subject targeting for exam prep).
- `settings` JSONB: duration_minutes, availability window (opens_at/closes_at),
  attempts_allowed, shuffle_questions/shuffle_options, navigation (`free`/`sequential`),
  results_policy (`immediately`/`after_close`/`manual`), access_code (hashed),
  question selection: fixed pivot rows (`quiz_questions`) or random draw rules
  (count per bank + difficulty/tag filter) resolved at attempt start.
- Lifecycle: `draft → published → closed` (+ archived). Editing questions after first
  attempt is blocked; publish snapshots point totals.

### 4. Exam security (v1, Ethiopia-realistic — no webcam/lockdown promises)
- **Server-authoritative timing**: `deadline_at` computed at attempt start
  (min(now+duration, closes_at) + grace); late answers rejected server-side.
- **One live attempt**, bound to the starting token/device fingerprint; a second
  device opening it flags + invalidates per settings.
- **Per-attempt shuffle seed** and/or random draw → neighbours get different papers.
- **Access code** for supervised in-room exams — presence in the room is the identity
  check for high-stakes exams.
- **Integrity log, not auto-punish**: blur/fullscreen-exit/paste/reconnect events are
  logged to `quiz_attempts.integrity_log` and surfaced as flags for teacher review.
- Correct answers never serialized to takers; grading is server-side only.

### 5. Assignments
- Anchored to `subject_assignment_id`; submission modes: `text`, `file`, `offline`.
- `assignment_submissions`: files on R2 (signed URLs), late flag vs due_at + late
  policy (accept/reject/penalty %), statuses `submitted → graded → returned`.
- Grading manual (teacher queue); score syncs to the gradebook like quizzes.

### 6. Materials
- School lane: `course_materials` posted by the teacher to their class(es) (targets =
  subject_assignment ids) or by director/principal subject+grade-window wide
  (branch/school scope via `targetBranch()`).
- Types: `file` (R2, signed URLs), `link`, `youtube`, `text`.
- **v1 video = YouTube embed + R2 file download.** Native HLS streaming is a later
  project (3G reality).
- Platform courses (modules → lessons → progress) are Phase 3; schema is designed to
  extend, not pre-built.

### 7. Access model — existing rails only
- New permission family: `lms.view`, `lms.manage` (supervisory), `lms.manage_own`
  (teachers, ownership-gated via `SubjectAssignment::isOwnedBy`), `lms.questions.manage`
  (school bank curation), and platform `exam_prep.manage` (national bank + mock exams,
  Catalog Studio).
- Students/parents: **exclusively `/api/v1/me/*`** (`/me/lms/*`) per ADR-012; parent
  visibility rides the existing `can_view_grades` link flag.
- **National exam prep** endpoints (`/me/exam-prep/*`) are open to any authenticated
  user — school students, B2C, adults with no school. Attempts hang off `user_id`
  (nullable `student_id`), so no-school users are first-class.
- Cross-tenant isolation: every staff query scoped by the anchor row's
  school/branch; guard rail test `LmsAccessTest`.

## Anti-patterns to avoid here
- ❌ separate quiz + exam tables (one `quizzes` table, `kind` column)
- ❌ correct answers in taker payloads (strip `answer_key` in resources, always)
- ❌ client-computed deadlines or scores
- ❌ per-section copies of materials (targets pivot, one row of truth)
- ❌ auto-failing on tab-switch (flag for human review instead)
